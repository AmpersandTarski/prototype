#!/usr/bin/env bash
#
# Build and run a system of contexts from what `ampersand deploy` generates, and nothing else.
#
#   test/multi-context/run-deployment.sh <scenario>
#
# The runner asks the compiler for the deployment of the scenario (a compose file and a Dockerfile
# per context), builds the images, starts the stack, installs the applications with the generated
# install.sh, and checks that every application answers and has a database of its own.
#
# The generated Dockerfiles compile the scripts inside the image, so the framework image has to
# contain a compiler that knows systems of contexts. Build one with:
#
#   docker buildx build --platform linux/amd64 --load -t ampersandtarski/ampersand:local <Ampersand repository>
#   docker buildx build --platform linux/amd64 --load -t ampersandtarski/prototype-framework:local \
#     --build-arg COMPILER_IMAGE=ampersandtarski/ampersand:local <this repository>
#
# FRAMEWORK_IMAGE names the framework image (default: ampersandtarski/prototype-framework:local).
# AMPERSAND names the compiler on this machine that writes the deployment (default: ampersand).
# KEEP=1 leaves the stack running.
set -uo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
AMPERSAND="${AMPERSAND:-ampersand}"
export FRAMEWORK_IMAGE="${FRAMEWORK_IMAGE:-ampersandtarski/prototype-framework:local}"
name="${1:?give the name of a scenario}"
dir="$HERE/scenarios/$name"
out="$dir/.deploy"
stack="mcdeploy-$name"
failures=0

log() { printf '%s\n' "$*"; }
expect() {
  if [ "$2" = "$3" ]; then log "  ok    $1"
  else log "  FAIL  $1"; log "        expected: $2"; log "        actual:   $3"; failures=$((failures + 1)); fi
}

rm -rf "$out"
"$AMPERSAND" deploy "$dir/main.adl" --output-dir "$out" >/dev/null 2>&1 || { log "ampersand deploy failed"; exit 1; }
compose() { docker compose -f "$out/compose.yaml" -p "$stack" "$@"; }

# Ports of its own, so that the stack does not collide with another stack on this machine.
# The generated compose file and install.sh take them from the environment.
base_port="${MC_DEPLOY_BASE_PORT:-9600}"
i=0
while read -r variable; do
  export "${variable%_DBNAME}_PORT=$((base_port + i))"
  i=$((i + 1))
done < <(jq -r '.contexts[].databaseVariable' "$out/system.json")
port_of() { jq -r --arg s "$1" --argjson base "$base_port" '.contexts | map(.service) | index($s) + $base' "$out/system.json"; }

log "== $name: build the images of $(jq -r '[.contexts[].service] | join(", ")' "$out/system.json")"
if ! compose build >"$out/build.txt" 2>&1; then
  log "  the images did not build:"; tail -30 "$out/build.txt" | sed 's/^/    /'; exit 1
fi
compose up -d --wait >"$out/up.txt" 2>&1 || { log "  the stack did not start:"; tail -20 "$out/up.txt" | sed 's/^/    /'; compose down -v >/dev/null 2>&1; exit 1; }

if ! (cd "$out" && bash ./install.sh) >"$out/install.txt" 2>&1; then
  log "  FAIL  install.sh"; sed 's/^/        /' "$out/install.txt" | tail -20; failures=$((failures + 1))
else
  log "  ok    install.sh installed: $(grep -c '^Installing' "$out/install.txt") applications"
fi

while IFS=$'\t' read -r service database; do
  port="$(port_of "$service")"
  answer="$(curl -sS "http://localhost:$port/api/v1/admin/ruleengine/evaluate/all" | jq -r 'if .invariants == [] then "no invariant violated" else "invariants violated" end' 2>&1)"
  expect "the application of $service answers" "no invariant violated" "$answer"
  tables="$(compose exec -T db mysql -uroot -pampersand -N -B -e "SELECT COUNT(*) > 0 FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$database'" 2>/dev/null </dev/null)"
  expect "$service has the database $database" "1" "$tables"
  page="$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:$port/")"
  expect "the frontend of $service is served" "200" "$page"
done < <(jq -r '.contexts[] | [.service, .defaultDatabase] | @tsv' "$out/system.json")

if [ "${KEEP:-}" = "1" ]; then
  log "  stack $stack keeps running; files in $out"
else
  compose down -v >/dev/null 2>&1
  rm -rf "$out"
fi
[ "$failures" -eq 0 ]
