#!/usr/bin/env bash
#
# Run a system of contexts on one database server, each context on a database of its own,
# and check what its scenario expects.
#
#   test/multi-context/run.sh <scenario>      one scenario from test/multi-context/scenarios
#   test/multi-context/run.sh all             every scenario
#
# A scenario is a directory with the scripts of a system (main.adl is its root) and a file checks.sh.
# The runner asks the compiler which contexts the system has (`ampersand deploy`), compiles each of
# them (`ampersand proto --context`), starts one application per context on the framework in this
# working copy, installs them in the order the compiler gives, and runs checks.sh.
#
# The compiler is the one in $AMPERSAND (default: `ampersand` on the path). It runs on this machine,
# so that a compiler under development can be tried without building an image of it.
# KEEP=1 leaves the stack running after the checks, for inspection.
set -uo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$HERE/../.." && pwd)"
AMPERSAND="${AMPERSAND:-ampersand}"
BASE_PORT="${MC_BASE_PORT:-9500}"

log() { printf '%s\n' "$*"; }

run_scenario() {
  local name="$1" dir="$HERE/scenarios/$1"
  [ -f "$dir/main.adl" ] || { log "unknown scenario: $name"; return 1; }
  local work; work="$(mktemp -d "${TMPDIR:-/tmp}/mc-$name.XXXXXX")"
  local stack="mc-$name"
  log "== $name"

  # 1. Which contexts does the system have?
  if ! "$AMPERSAND" deploy "$dir/main.adl" --output-dir "$work/deploy" >"$work/deploy.txt" 2>&1; then
    log "  ampersand deploy failed:"; sed 's/^/    /' "$work/deploy.txt" | tail -20; return 1
  fi
  local system="$work/deploy/system.json"

  # 2. Compile every context.
  local service label
  while IFS=$'\t' read -r service label; do
    local args=(proto "$dir/main.adl" --all-concept-tables --no-frontend --crud-defaults cRud --proto-dir "$work/$service")
    [ -n "$label" ] && args+=(--context "$label")
    if ! "$AMPERSAND" "${args[@]}" >"$work/$service.txt" 2>&1; then
      log "  context $service did not compile:"; sed 's/^/    /' "$work/$service.txt" | tail -20; return 1
    fi
  done < <(jq -r '.contexts[] | [.service, .label] | @tsv' "$system")

  # 3. One application per context, on the framework of this working copy.
  python3 "$HERE/compose.py" "$system" "$REPO_ROOT" "$work" "$stack" "$BASE_PORT" >"$work/compose.yaml"
  compose() { docker compose -f "$work/compose.yaml" -p "$stack" "$@"; }
  if ! compose up -d --build --wait >"$work/up.txt" 2>&1; then
    log "  the stack did not start:"; tail -20 "$work/up.txt" | sed 's/^/    /'; compose down -v >/dev/null 2>&1; return 1
  fi
  local first; first="$(jq -r '.contexts[0].service' "$system")"
  docker exec "$stack-$first" sh -c 'cd /var/www && composer install --no-interaction --no-progress' >/dev/null 2>&1
  docker exec "$stack-$first" sh -c 'mkdir -p /var/www/html && cp -r /var/www/backend/public/. /var/www/html/' >/dev/null 2>&1

  # Helpers for checks.sh ---------------------------------------------------------------------
  port_of() { jq -r --arg s "$1" --argjson base "$BASE_PORT" '.contexts | map(.service) | index($s) + $base' "$system"; }
  db_of() { jq -r --arg s "$1" '.contexts[] | select(.service == $s) | .defaultDatabase' "$system"; }
  # api <service> <path>: a GET on the API of the application of a context
  api() { curl -sS "http://localhost:$(port_of "$1")/api/v1/$2"; }
  # sql <service> <query>: a query on the database of a context, as tab-separated rows
  sql() { docker exec "$stack-db" mysql -uroot -pampersand -N -B --default-character-set=utf8mb4 "$(db_of "$1")" -e "SET sql_mode='ANSI,TRADITIONAL'; $2" 2>&1; }
  failures=0
  # expect <what> <expected> <actual>
  expect() {
    if [ "$2" = "$3" ]; then log "  ok    $1"
    else log "  FAIL  $1"; log "        expected: $2"; log "        actual:   $3"; failures=$((failures + 1)); fi
  }
  # recompile <service> <script> [options]: give the application of a context the model of another script,
  # as a new release of that application would
  recompile() {
    local service="$1" script="$2"; shift 2
    "$AMPERSAND" proto "$dir/$script" --all-concept-tables --no-frontend --crud-defaults cRud --proto-dir "$work/$service" "$@" >"$work/$service.txt" 2>&1 \
      || { log "  context $service did not compile:"; sed 's/^/    /' "$work/$service.txt" | tail -20; failures=$((failures + 1)); }
  }
  # install <service> [options]: install the application of a context; prints the answer of the installer
  install() {
    local deadline=$((SECONDS + 90))
    until curl -s -o /dev/null "http://localhost:$(port_of "$1")/"; do
      [ $SECONDS -lt $deadline ] || { echo "the application of $1 does not answer"; return 1; }
      sleep 2
    done
    curl -sS "http://localhost:$(port_of "$1")/api/v1/admin/installer${2:-}"
  }
  # install_all [options]: install every application, a context after every context it reaches
  install_all() {
    local s out
    for s in $(jq -r '.installOrder[]' "$system"); do
      out="$(install "$s" "${1:-}")"
      if ! printf '%s' "$out" | jq -e '.errors == [] or (.errors | not)' >/dev/null 2>&1; then
        log "  FAIL  install $s"; printf '%s\n' "$out" | head -c 1500 | sed 's/^/        /'; failures=$((failures + 1))
      else
        log "  ok    install $s"
      fi
    done
  }

  # 4. The checks of the scenario.
  # shellcheck disable=SC1091
  . "$dir/checks.sh"

  if [ "${KEEP:-}" = "1" ]; then
    log "  stack $stack keeps running; files in $work"
  else
    compose down -v >/dev/null 2>&1
    rm -rf "$work"
  fi
  [ "$failures" -eq 0 ]
}

case "${1:-}" in
  "" ) sed -n '2,15p' "$0" | sed 's/^# \{0,1\}//'; exit 64 ;;
  all)
    rc=0
    for d in "$HERE"/scenarios/*/; do run_scenario "$(basename "$d")" || rc=1; done
    exit $rc ;;
  *) run_scenario "$1" ;;
esac
