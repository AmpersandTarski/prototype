#!/usr/bin/env bash
#
# Runtime regression runner (issue #402, phase 1).
#
#   test/run-regression.sh <project>   # verify one project in test/projects/
#   test/run-regression.sh all         # verify every project, then print the report
#   test/run-regression.sh --list      # show what each project guards
#
# Per project it brings up an isolated stack, compiles the project's model into it,
# installs the application and runs the project's spec (test/projects/<project>/e2e/),
# then tears the stack down. The exit code is the verdict.
#
# A project without a spec is reported as "no spec" and does not count as verified:
# bringing a stack up only shows that the model compiles and installs.
#
set -uo pipefail

REPO_ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
PROJECTS_DIR="$REPO_ROOT/test/projects"
COMPOSE_FILE="$REPO_ROOT/test/runtime-stack.compose.yaml"
REG_PORT="${REG_PORT:-9400}"
BASE_URL="http://localhost:${REG_PORT}"

# The stack claims a port, container names and the working copy's generated files, so two
# runs cannot overlap. flock(1) is not available on macOS, so we lock with mkdir, which is
# atomic on every POSIX filesystem.
LOCK_DIR="${TMPDIR:-/tmp}/ampersand-regression.lock.d"

log()  { printf '%s\n' "$*"; }
step() { printf '  → %s\n' "$*"; }

acquire_lock() {
  if ! mkdir "$LOCK_DIR" 2>/dev/null; then
    log "Another regression run holds the lock ($LOCK_DIR)."
    log "Wait for it to finish, or remove the directory if no run is active."
    exit 3
  fi
  trap 'rm -rf "$LOCK_DIR"' EXIT
}

# The entry model of a project. Defaults to model/main.adl; a project whose entry file has
# another name states it in regression.conf (e.g. ENTRY=model/app.adl).
entry_model() {
  local project="$1" entry="model/main.adl"
  local conf="$PROJECTS_DIR/$project/regression.conf"
  [ -f "$conf" ] && . "$conf"
  printf '%s' "$entry"
}

# What a project guards, taken from the "Guards:" line of its README (phase 0 format).
guards_of() {
  local readme="$PROJECTS_DIR/$1/README.md"
  [ -f "$readme" ] || { printf 'no README — what does this project guard?'; return; }
  local line
  line=$(grep -m1 -i '^\*\*Guards:\*\*\|^- \*\*Guards:\*\*\|^Guards:' "$readme" 2>/dev/null \
         | sed -E 's/^-? ?\*\*Guards:\*\*[[:space:]]*//I; s/^Guards:[[:space:]]*//I')
  printf '%s' "${line:-no Guards: line in README}"
}

# --wait blocks until the database reports healthy (see the healthcheck in the compose file),
# so the application is never installed against a database that is still starting.
stack_up() {
  local project="$1"
  REG_STACK="reg-$project" REG_PORT="$REG_PORT" \
    docker compose -f "$COMPOSE_FILE" -p "reg-$project" up -d --build --wait >/dev/null 2>&1
}

stack_down() {
  local project="$1"
  REG_STACK="reg-$project" REG_PORT="$REG_PORT" \
    docker compose -f "$COMPOSE_FILE" -p "reg-$project" down -v >/dev/null 2>&1
}

# Wait until Apache answers. Any response will do (the application is not installed yet),
# so do not request the installer here: that would install before the model is compiled.
wait_for_web() {
  local deadline=$((SECONDS + 90))
  while [ $SECONDS -lt $deadline ]; do
    curl -s -o /dev/null "$BASE_URL/" && return 0
    sleep 2
  done
  return 1
}

# Verify one project. Prints its own progress; returns 0 = pass, 1 = fail, 2 = no spec.
run_project() {
  local project="$1"
  local dir="$PROJECTS_DIR/$project"
  local model; model=$(entry_model "$project")

  [ -d "$dir" ] || { log "unknown project: $project"; return 1; }
  [ -f "$dir/$model" ] || { log "no entry model $model in $project (see regression.conf)"; return 1; }

  step "stack up (port $REG_PORT)"
  stack_up "$project" || { log "  stack failed to start"; stack_down "$project"; return 1; }
  # shellcheck disable=SC2064
  trap "stack_down '$project'; rm -rf '$LOCK_DIR'" EXIT

  wait_for_web || { log "  web server did not come up"; stack_down "$project"; return 1; }

  step "dependencies"
  docker exec "reg-$project-prototype" sh -c \
    '[ -f /var/www/backend/lib/autoload.php ] || (cd /var/www && composer install --no-interaction)' \
    >/dev/null 2>&1

  step "compile $model"
  if ! docker exec "reg-$project-prototype" sh -c \
      "ampersand proto --no-frontend /var/www/test/projects/$project/$model \
       --proto-dir /var/www/backend --crud-defaults cRud" >/dev/null 2>&1; then
    log "  model did not compile"
    stack_down "$project"
    return 1
  fi

  # Expose the API (the front controller); specs that need the built frontend build it themselves.
  docker exec "reg-$project-prototype" sh -c \
    'mkdir -p /var/www/html && cp -r /var/www/backend/public/. /var/www/html/' >/dev/null 2>&1

  step "install application"
  local installer_out curl_rc
  # -sS keeps curl quiet but reports transport errors (e.g. connection refused) on stderr,
  # so a failure states its cause instead of an empty body.
  installer_out=$(curl -sS -f "$BASE_URL/api/v1/admin/installer?ignoreInvariantRules=true" 2>&1)
  curl_rc=$?
  if [ $curl_rc -ne 0 ]; then
    log "  installer failed (curl exit $curl_rc):"
    printf '%s\n' "${installer_out:-(no output)}" | head -20 | sed 's/^/    /'
    stack_down "$project"
    return 1
  fi

  local rc=2 # no spec, until we find one
  if compgen -G "$dir/e2e/*.spec.ts" >/dev/null; then
    step "spec (playwright)"
    ( cd "$REPO_ROOT/test" && PROTOTYPE_URL="$BASE_URL" PROTOTYPE_CONTAINER="reg-$project-prototype" \
        npx playwright test "$dir/e2e" ) && rc=0 || rc=1
  elif compgen -G "$dir/e2e/*.mjs" >/dev/null; then
    step "spec (node)"
    rc=0
    for spec in "$dir"/e2e/*.mjs; do
      PROTOTYPE_URL="$BASE_URL" PROTOTYPE_CONTAINER="reg-$project-prototype" \
        node "$spec" || rc=1
    done
  fi

  stack_down "$project"
  trap 'rm -rf "$LOCK_DIR"' EXIT
  return $rc
}

verdict_text() {
  case "$1" in
    0) printf 'PASS' ;;
    2) printf 'no spec' ;;
    *) printf 'FAIL' ;;
  esac
}

case "${1:-}" in
  --list)
    log "What each project guards:"
    for dir in "$PROJECTS_DIR"/*/; do
      p=$(basename "$dir")
      printf '  %-24s %s\n' "$p" "$(guards_of "$p")"
    done
    ;;

  all)
    acquire_lock
    declare -a names=() verdicts=() times=()
    for dir in "$PROJECTS_DIR"/*/; do
      p=$(basename "$dir")
      log ""
      log "=== $p ==="
      start=$SECONDS
      run_project "$p"; rc=$?
      names+=("$p"); verdicts+=("$rc"); times+=("$((SECONDS - start))")
    done

    # Contribution report: what did this run guard, and what did it cost?
    log ""
    log "================ regression report ================"
    printf '%-24s %-8s %6s  %s\n' "project" "verdict" "sec" "guards"
    fails=0; nospec=0
    for i in "${!names[@]}"; do
      printf '%-24s %-8s %6s  %s\n' \
        "${names[$i]}" "$(verdict_text "${verdicts[$i]}")" "${times[$i]}" "$(guards_of "${names[$i]}")"
      [ "${verdicts[$i]}" = "1" ] && fails=$((fails + 1))
      [ "${verdicts[$i]}" = "2" ] && nospec=$((nospec + 1))
    done
    log "---------------------------------------------------"
    log "${#names[@]} projects, $fails failed, $nospec without a spec (not verified)"
    [ "$fails" -eq 0 ] || exit 1
    ;;

  ""|-h|--help)
    sed -n '2,16p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
    exit 2
    ;;

  *)
    acquire_lock
    log "=== $1 ==="
    run_project "$1"; rc=$?
    log "verdict: $(verdict_text "$rc") — guards: $(guards_of "$1")"
    [ "$rc" = "1" ] && exit 1
    exit 0
    ;;
esac
