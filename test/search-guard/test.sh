#!/usr/bin/env bash
# Toetst dat de zoekmodule alleen data teruggeeft die de sessie mag lezen.
#
# Model: test/projects/search-guard (Dossier is alleen leesbaar FOR Caseworker;
# dossierNote is non-UNI, dus opgeslagen in een eigen binaire tabel).
#
# Opzet (zie README.md): stack op poort 9380, backend gegenereerd uit het model,
# app geinstalleerd. Beide fasen draaien met een eigen instelling van
# session.loginEnabled in backend/config/project.yaml.
set -u

BASE="${BASE:-http://localhost:9380/api/v1}"
JARDIR=$(mktemp -d /tmp/searchguard.XXXX)
FAILED=0

check() { # check <verwacht> <werkelijk> <omschrijving>
  if [ "$1" == "$2" ]; then echo "  PASS: $3 (=$2)"; else echo "  FAIL: $3 (verwacht $1, werkelijk $2)"; FAILED=1; fi
}

n_results() { # n_results <jar> <term>
  curl -s -b "$JARDIR/$1.jar" -c "$JARDIR/$1.jar" "$BASE/search?q=$2" \
    | python3 -c "import json,sys;print(len(json.load(sys.stdin)['results']))"
}

case "${1:-all}" in

anonymous)
  echo "=== S1: sessie zonder leesrecht vindt vertrouwelijke data niet ==="
  echo "  (vereist session.loginEnabled: true in backend/config/project.yaml)"
  curl -s -c "$JARDIR/A.jar" "$BASE/app/navbar" -o "$JARDIR/nav.json"
  loggedin=$(python3 -c "import json;print(json.load(open('$JARDIR/nav.json'))['session']['loggedIn'])")
  check "False" "$loggedin" "sessie is anoniem"
  check 0 "$(n_results A Vertrouwelijk)" "geen resultaten op 'Vertrouwelijk'"
  check 0 "$(n_results A Loonbeslag)" "geen resultaten op 'Loonbeslag'"
  ;;

authorized)
  echo "=== S2: sessie met rol Caseworker vindt de dossiers wel ==="
  echo "  (vereist session.loginEnabled: false in backend/config/project.yaml)"
  curl -s -c "$JARDIR/B.jar" "$BASE/app/navbar" -o /dev/null
  curl -s -b "$JARDIR/B.jar" -c "$JARDIR/B.jar" -X PATCH -H "Content-Type: application/json" \
    -d '[{"id":"Caseworker","active":true}]' "$BASE/app/roles" -o /dev/null
  check 1 "$(n_results B Vertrouwelijk)" "1 resultaat op 'Vertrouwelijk'"
  # het resultaat draagt de interface waarin het geopend kan worden
  ifcs=$(curl -s -b "$JARDIR/B.jar" "$BASE/search?q=Vertrouwelijk" \
    | python3 -c "import json,sys;d=json.load(sys.stdin);print(','.join(i['id'] for i in d['results'][0]['_ifcs_']))")
  check "Dossiers" "$ifcs" "resultaat draagt de leesbare interface"
  ;;

all)
  echo "Draai de fasen los: eerst 'anonymous' met loginEnabled=true, daarna"
  echo "'authorized' met loginEnabled=false (zie README.md)."
  exit 2
  ;;

*)
  echo "gebruik: $0 {anonymous|authorized}"; exit 2
  ;;
esac

rm -rf "$JARDIR"
exit $FAILED
