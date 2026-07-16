#!/usr/bin/env bash
# Tests: het prototype ruimt verlopen sessies op zodra een nieuwe sessie start,
# ook onder gelijktijdige requests (zie README.md voor de opzet).
# Vereist: sessgc-stack draait (compose.yaml hiernaast), hello-world backend
# gegenereerd en geïnstalleerd, session.expirationTime = 5 (backend/config/project.yaml).
set -u

BASE="${BASE:-http://localhost:9280/api/v1}"
DB="${DB:-helloworld}" # databasenaam = contextnaam van het gegenereerde model
JARDIR=$(mktemp -d /tmp/sessgc-jars.XXXX)
SQL() { docker exec sessgc-db mysql -uampersand -pampersand $DB -N -e "$1" 2>/dev/null; }

reinstall() {
  curl -s "$BASE/admin/installer" > /dev/null
  # de installer-request maakt zelf een sessie aan; verwijder die voor een schone start
  SQL 'DELETE FROM session'
  SQL 'DELETE FROM "prototypecontext.sessionallowedroles"'
}

# start/verleng een sessie met cookie-jar $1; print http-status
touch_session() {
  curl -s -o /dev/null -w "%{http_code}" -c "$JARDIR/$1.jar" -b "$JARDIR/$1.jar" "$BASE/app/navbar"
}

count_sessions() { SQL 'SELECT COUNT(*) FROM session'; }
list_sessions()  { SQL 'SELECT "SESSION", "PrototypeContext.lastAccess" FROM session'; }

check() { # check <verwacht> <werkelijk> <omschrijving>
  if [ "$1" == "$2" ]; then echo "  PASS: $3 (=$2)"; else echo "  FAIL: $3 (verwacht $1, werkelijk $2)"; FAILED=1; fi
}

FAILED=0

case "${1:-all}" in

fixed)
  echo "=== TEST 1: verdwijnt de verlopen sessie zodra een nieuwe start? ==="
  reinstall
  s=$(touch_session A);              check 200 "$s" "sessie A gestart"
  echo "  ... 7s wachten (expiry = 5s) ..."; sleep 7
  s=$(touch_session B);              check 200 "$s" "sessie B gestart (nieuw)"
  check 1 "$(count_sessions)" "alleen B blijft over; verlopen A is opgeruimd"
  ;;

alive)
  echo "=== TEST 2: overleeft een ACTIEVE sessie de GC? ==="
  reinstall
  touch_session A > /dev/null
  # A blijft actief: elke 2s een request, 4x (8s totaal > expiry 5s)
  for i in 1 2 3 4; do sleep 2; touch_session A > /dev/null; done
  s=$(touch_session B);              check 200 "$s" "sessie B gestart (nieuw, na 8s)"
  check 2 "$(count_sessions)" "actieve A is NIET opgeruimd; A en B bestaan beide"
  ;;

return)
  echo "=== TEST 3: verlopen sessie komt zelf terug ==="
  reinstall
  touch_session A > /dev/null
  touch_session C > /dev/null
  check 2 "$(count_sessions)" "A en C gestart"
  echo "  ... 7s wachten (beide verlopen) ..."; sleep 7
  s=$(touch_session A);              check 200 "$s" "verlopen A komt terug: geen error"
  check 1 "$(count_sessions)" "oude A en verlopen C weg; alleen verse sessie van A over"
  ;;

race)
  echo "=== TEST 4: 6 gelijktijdige nieuwe sessies + verlopen atomen ==="
  reinstall
  touch_session C1 > /dev/null; touch_session C2 > /dev/null; touch_session C3 > /dev/null
  check 3 "$(count_sessions)" "3 sessies gestart die gaan verlopen"
  echo "  ... 7s wachten ..."; sleep 7
  pids=(); rm -f "$JARDIR"/race-*.status
  for i in 1 2 3 4 5 6; do
    ( curl -s -o /dev/null -w "%{http_code}" -c "$JARDIR/R$i.jar" "$BASE/app/navbar" > "$JARDIR/race-$i.status" ) &
    pids+=($!)
  done
  wait "${pids[@]}"
  ok=$(grep -l '^200$' "$JARDIR"/race-*.status | wc -l | tr -d ' ')
  check 6 "$ok" "alle 6 gelijktijdige nieuwe sessies kregen 200"
  check 6 "$(count_sessions)" "3 verlopen sessies weg; precies de 6 nieuwe over"
  ;;

mixedrace)
  echo "=== TEST 5: 3 verlopen sessies keren terug + 3 nieuwe, gelijktijdig ==="
  reinstall
  touch_session E1 > /dev/null; touch_session E2 > /dev/null; touch_session E3 > /dev/null
  check 3 "$(count_sessions)" "3 sessies gestart die gaan verlopen"
  echo "  ... 7s wachten ..."; sleep 7
  pids=(); rm -f "$JARDIR"/mixed-*.status
  for i in 1 2 3; do
    ( curl -s -o /dev/null -w "%{http_code}" -c "$JARDIR/E$i.jar" -b "$JARDIR/E$i.jar" "$BASE/app/navbar" > "$JARDIR/mixed-E$i.status" ) &
    pids+=($!)
    ( curl -s -o /dev/null -w "%{http_code}" -c "$JARDIR/N$i.jar" "$BASE/app/navbar" > "$JARDIR/mixed-N$i.status" ) &
    pids+=($!)
  done
  wait "${pids[@]}"
  ok=$(grep -l '^200$' "$JARDIR"/mixed-*.status | wc -l | tr -d ' ')
  check 6 "$ok" "alle 6 requests kregen 200"
  check 6 "$(count_sessions)" "3 oude atomen weg; 3 verse + 3 nieuwe sessies over"
  ;;

all)
  # races zijn probabilistisch: draai ze meermaals
  "$0" fixed && "$0" alive && "$0" return || FAILED=1
  for i in 1 2 3; do "$0" race || FAILED=1; done
  for i in 1 2 3; do "$0" mixedrace || FAILED=1; done
  ;;

*)
  echo "gebruik: $0 {all|fixed|alive|return|race|mixedrace}"; exit 2
  ;;
esac

rm -rf "$JARDIR"
exit $FAILED
