#!/usr/bin/env bash
#
# End-to-end regression test for runtime multi-value (multi-column) spreadsheet import.
#
# This exercises the database-level behaviour that the standalone unit test cannot cover:
#   - RELATION approach: target multi-value, source multi-value (cartesian product), flipped (~) + multi
#   - INTERFACE approach: multi-value sub-interface column
#
# It is NOT part of CI (it needs a running prototype). Run it on demand.
#
# Prerequisites (from the repository root):
#   1. docker compose up -d                          # framework dev stack must be running
#   2. ./generate.sh import-multivalue main.adl      # generate + build this project into the prototype
#
# Then:
#   test/projects/import-multivalue/e2e/run.sh
#
# Exits 0 when every assertion passes, 1 otherwise.

set -u

BASE="${BASE:-http://localhost}"
DB="${DB:-importmv}"                 # database name = lower-cased CONTEXT name (ImportMV)
PROTO="${PROTO:-prototype}"          # prototype (web) container name
PROTO_DB="${PROTO_DB:-prototype-db}" # MariaDB container name
EXPECTED_CONTEXT="ImportMV"          # CONTEXT name of this test model (model/main.adl)
DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$DIR/../../../.." && pwd)" # repository root (holds compose.yaml)
CJ="$(mktemp)"
FAILED=0
KEEP=0

for arg in "$@"; do
  case "$arg" in
    --keep) KEEP=1 ;; # keep the dev stack + its volume running after the test (skip teardown)
    *) echo "Unknown option: $arg" ; exit 2 ;;
  esac
done

mysql_exec() { docker exec "$PROTO_DB" mysql -uampersand -pampersand "$DB" "$@"; }

committed() { # $1 = response file -> prints "true"/"false"
  python3 -c "import json,sys;print(str(json.load(open('$1')).get('isCommitted')).lower())" 2>/dev/null
}

assert_rel() { # $1 table  $2 srcCol  $3 tgtCol  $4 expected(sorted)  $5 label
  local actual
  actual="$(mysql_exec -N -e "SELECT CONCAT(\`$2\`,',',\`$3\`) FROM \`$1\` ORDER BY 1;" 2>/dev/null | sort)"
  if [ "$actual" == "$4" ]; then
    echo "  PASS  $5"
  else
    echo "  FAIL  $5"
    echo "    expected:"; echo "$4" | sed 's/^/      /'
    echo "    actual:";   echo "$actual" | sed 's/^/      /'
    FAILED=1
  fi
}

echo "== 0. Safety guard: confirm the import-multivalue model is deployed =="
# Step 1 below runs the installer, which RECREATES the deployed model's database. Refuse to run
# unless the prototype really serves this test model (CONTEXT ImportMV), so we can never wipe a
# different running prototype (e.g. NVWA). The deployed context name lives in settings.json.
ctx="$(docker exec "$PROTO" cat /var/www/backend/generics/settings.json 2>/dev/null \
  | python3 -c 'import sys,json; print(json.load(sys.stdin).get("global.contextName",""))' 2>/dev/null)"
if [ "$ctx" != "$EXPECTED_CONTEXT" ]; then
  echo "  ABORT: deployed model is '${ctx:-<none/unreachable>}', expected '$EXPECTED_CONTEXT'."
  echo "  Run from the repository root first:"
  echo "      docker compose up -d && ./generate.sh import-multivalue main.adl"
  echo "  Refusing to run the destructive installer against another prototype."
  exit 1
fi
echo "  OK: deployed model is '$ctx'"

echo "== 0b. Generate .xlsx fixtures =="
php "$DIR/make-fixtures.php" || { echo "Could not generate fixtures"; exit 1; }

echo "== 1. (Re)install database =="
code=$(curl -s "$BASE/api/v1/admin/installer?ignoreInvariantRules=true" -o /dev/null -w "%{http_code}")
echo "  installer HTTP $code"
[ "$code" == "200" ] || { echo "Installer failed — did you run ./generate.sh import-multivalue main.adl ?"; exit 1; }

echo "== 2. Provision interface access for the test =="
# The INTERFACE approach checks interface access. The generated meta-population does not load role
# atoms into the database in this minimal setup, so we provision access for the test: register the
# role atom, assign the import interface to it, then activate the role for our session. This must
# happen BEFORE the first import so the role is active on the session that does the importing.
mysql_exec -e "INSERT IGNORE INTO \`prototypecontext.role\` (\`PrototypeContext.Role\`) VALUES ('Importer');
INSERT IGNORE INTO \`prototypecontext.ifcroles\` (\`PrototypeContext.Interface\`,\`PrototypeContext.Role\`) VALUES ('PersonSkills','Importer');" 2>/dev/null
curl -s -c "$CJ" -b "$CJ" -X PATCH -H "Content-Type: application/json" \
  -d '[{"id":"Importer","active":true}]' "$BASE/api/v1/app/roles" -o /dev/null -w "  activate role HTTP %{http_code}\n"

echo "== 3. RELATION approach: import mv-rel.xlsx =="
curl -s -c "$CJ" -b "$CJ" -F "file=@$DIR/mv-rel.xlsx" "$BASE/api/v1/admin/import" -o /tmp/e2e_rel.out -w "  import HTTP %{http_code}\n"
echo "  committed: $(committed /tmp/e2e_rel.out)"
assert_rel skills   Person   Skill    $'john,reading\npete,cooking\npete,diving\npete,flying' "skills (target multi-value, trim, drop-empty)"
assert_rel related  SrcSkill TgtSkill $'alpha,gamma\nbeta,gamma'                                "related (source multi-value, cartesian product)"
assert_rel enrolled Project  Person   $'p1,pete\np2,pete'                                       "enrolled (flipped ~ + multi-value)"

echo "== 4. INTERFACE approach: import mv-ifc.xlsx (persons pete/john now exist) =="
curl -s -c "$CJ" -b "$CJ" -F "file=@$DIR/mv-ifc.xlsx" "$BASE/api/v1/admin/import" -o /tmp/e2e_ifc.out -w "  import HTTP %{http_code}\n"
echo "  committed: $(committed /tmp/e2e_ifc.out)"
assert_rel skills Person Skill \
  $'john,coding\njohn,hiking\njohn,reading\npete,cooking\npete,dancing\npete,diving\npete,flying\npete,singing' \
  "skills after INTERFACE import (multi-value column added to existing persons)"

rm -f "$CJ"

echo
echo "== 5. Teardown =="
# Clean up the throwaway dev stack and its database volume so nothing lingers. This is scoped to the
# 'prototypeframework' compose project (compose.yaml in the repo root): it removes ONLY the framework
# volume 'prototypeframework_db-data'. It cannot touch other prototypes' volumes (e.g. 'fc5_db-data',
# 'bfdd_db-data'). The safety guard above guarantees we only get here for our own test stack.
if [ "$KEEP" == "1" ]; then
  echo "  --keep given: leaving the dev stack and its volume in place"
else
  ( cd "$REPO_ROOT" && docker compose down -v ) >/dev/null 2>&1 \
    && echo "  removed framework dev stack + volume 'prototypeframework_db-data'" \
    || echo "  WARNING: teardown (docker compose down -v) did not complete cleanly"
  echo "  Note: the prototype container is now down. Bring your own stack (e.g. NVWA) back up if needed."
fi

echo
if [ "$FAILED" == "0" ]; then
  echo "==== E2E PASSED ===="
  exit 0
else
  echo "==== E2E FAILED ===="
  exit 1
fi
