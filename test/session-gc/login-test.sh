#!/usr/bin/env bash
# Experiment: werkt login/logout (SIAM-mechanisme + framework-loginpad) correct
# met de sessie-GC en de gewijzigde transactiegrenzen (resetSession commit vroeg)?
# Vereist: sessgc-stack met het model test/projects/session-gc-login,
# session.loginEnabled=true en session.expirationTime=5 (backend/config/project.yaml).
set -u

BASE="${BASE:-http://localhost:9280/api/v1}"
DB=sessiongclogin
JARDIR=$(mktemp -d /tmp/sessgc-login.XXXX)
SQL() { docker exec sessgc-db mysql -uampersand -pampersand $DB -N -e "$1" 2>/dev/null; }

reinstall() {
  curl -s "$BASE/admin/installer" > /dev/null
  SQL 'DELETE FROM session'
  SQL 'DELETE FROM "prototypecontext.sessionallowedroles"'
}

nav() { # nav <jar> : navbar-request; print http-status
  curl -s -o "$JARDIR/last-nav.json" -w "%{http_code}" -c "$JARDIR/$1.jar" -b "$JARDIR/$1.jar" "$BASE/app/navbar"
}
nav_field() { # nav_field <veldpad zoals loggedIn of id>
  python3 -c "import json;d=json.load(open('$JARDIR/last-nav.json'));print(d['session']['$1'])"
}
activate_role() { # activate_role <jar> <rol>
  curl -s -o /dev/null -w "%{http_code}" -b "$JARDIR/$1.jar" -c "$JARDIR/$1.jar" -X PATCH \
    -H "Content-Type: application/json" -d "[{\"id\":\"$2\",\"active\":true}]" "$BASE/app/roles"
}
login_ifc_path() { # login_ifc_path <jar> <sessionId> : haal het _path_ van de Login-interface op (skill-conventie: volg _path_)
  curl -s -b "$JARDIR/$1.jar" -c "$JARDIR/$1.jar" "$BASE/resource/SESSION/$2/Login" -o "$JARDIR/last-get.json"
  python3 -c "import json;print(json.load(open('$JARDIR/last-get.json'))['_path_'])"
}
patch_login_ifc() { # patch_login_ifc <jar> <ifc-path> <json-patch-body> ; print status; body in last-patch.json
  curl -s -o "$JARDIR/last-patch.json" -w "%{http_code}" -b "$JARDIR/$1.jar" -c "$JARDIR/$1.jar" -X PATCH \
    -H "Content-Type: application/json" -d "$3" "$BASE/$2"
}
patch_committed() {
  python3 -c "import json;d=json.load(open('$JARDIR/last-patch.json'));print(str(d.get('isCommitted')).lower(),str(d.get('invariantRulesHold')).lower())"
}

count_sessions()  { SQL 'SELECT COUNT(*) FROM session'; }
count_loggedin()  { SQL 'SELECT COUNT(*) FROM session WHERE "sessionAccount" IS NOT NULL'; }

check() { # check <verwacht> <werkelijk> <omschrijving>
  if [ "$1" == "$2" ]; then echo "  PASS: $3 (=$2)"; else echo "  FAIL: $3 (verwacht $1, werkelijk $2)"; FAILED=1; fi
}

FAILED=0

case "${1:-all}" in

execengine-login)
  echo "=== L1: SIAM-login via ExecEngine (InsPair sessionAccount in de formulier-transactie) ==="
  reinstall
  s=$(nav A); check 200 "$s" "sessie A gestart"
  sid=$(nav_field id)
  s=$(activate_role A Anonymous); check 200 "$s" "rol Anonymous geactiveerd"
  ifcpath=$(login_ifc_path A "$sid")
  s=$(patch_login_ifc A "$ifcpath" '[{"op":"replace","path":"/userid","value":"alice"},{"op":"replace","path":"/password","value":"secret123"}]')
  check 200 "$s" "inlogformulier gevuld (userid+password)"
  s=$(patch_login_ifc A "$ifcpath" '[{"op":"replace","path":"/loginReq","value":true}]')
  check 200 "$s" "loginReq gezet"
  check "true true" "$(patch_committed)" "login-transactie gecommit en invarianten gelden"
  check 1 "$(count_loggedin)" "sessie heeft sessionAccount"
  check "alice" "$(SQL 'SELECT "sessionAccount" FROM session WHERE "sessionAccount" IS NOT NULL')" "sessionAccount = alice"
  check "" "$(SQL 'SELECT "loginPassword" FROM session WHERE "loginPassword" IS NOT NULL')" "wachtwoord gewist na login (SIAM-regel)"
  s=$(nav A); check 200 "$s" "navbar na login"
  check "True" "$(nav_field loggedIn)" "navbar meldt loggedIn"
  check "1" "$(SQL 'SELECT COUNT(*) FROM "prototypecontext.sessionallowedroles" WHERE "PrototypeContext.Role"='"'"'User'"'"'')" "rol User toegekend (GrantUserRole)"
  ;;

execengine-logout)
  echo "=== L2: SIAM-logout via ExecEngine (DelAtom SESSION: sessie verwijdert zichzelf) ==="
  # bouwt voort op L1: log opnieuw in met jar B
  reinstall
  nav B > /dev/null; sid=$(nav_field id)
  activate_role B Anonymous > /dev/null
  ifcpath=$(login_ifc_path B "$sid")
  patch_login_ifc B "$ifcpath" '[{"op":"replace","path":"/userid","value":"alice"},{"op":"replace","path":"/password","value":"secret123"}]' > /dev/null
  patch_login_ifc B "$ifcpath" '[{"op":"replace","path":"/loginReq","value":true}]' > /dev/null
  check 1 "$(count_loggedin)" "voorbereiding: B is ingelogd"
  s=$(patch_login_ifc B "$ifcpath" '[{"op":"replace","path":"/logoutReq","value":true}]')
  check 200 "$s" "logoutReq gezet (sessie-atoom verwijdert zichzelf)"
  check "true true" "$(patch_committed)" "logout-transactie gecommit en invarianten gelden"
  check 0 "$(count_sessions)" "sessie-atoom is weg na logout"
  s=$(nav B); check 200 "$s" "volgende request maakt verse anonieme sessie"
  check "False" "$(nav_field loggedIn)" "navbar meldt uitgelogd"
  check 0 "$(count_loggedin)" "geen sessionAccount meer"
  ;;

framework-login)
  echo "=== L3: framework-login via resetSession (het gewijzigde pad: delete oude atoom apart gecommit) ==="
  reinstall
  s=$(nav C); check 200 "$s" "anonieme sessie C gestart"
  oldsid=$(nav_field id)
  s=$(curl -s -o /dev/null -w "%{http_code}" -b "$JARDIR/C.jar" -c "$JARDIR/C.jar" "$BASE/admin/test/login/alice")
  check 200 "$s" "test-login endpoint (AmpersandApp::login -> resetSession)"
  s=$(nav C); check 200 "$s" "navbar na framework-login"
  check "True" "$(nav_field loggedIn)" "navbar meldt loggedIn"
  newsid=$(nav_field id)
  if [ "$oldsid" != "$newsid" ]; then echo "  PASS: session-id vernieuwd (OWASP)"; else echo "  FAIL: session-id niet vernieuwd"; FAILED=1; fi
  check 1 "$(count_sessions)" "oude anonieme atoom is weg; alleen de ingelogde sessie over"
  check 1 "$(count_loggedin)" "ingelogde sessie heeft sessionAccount"
  check "alice" "$(SQL 'SELECT "sessionAccount" FROM session')" "sessionAccount = alice"
  check "1" "$(SQL 'SELECT COUNT(*) FROM acclogintimestamps')" "login-timestamp geregistreerd"
  ;;

gc-loggedin)
  echo "=== L4: GC ruimt een verlopen INGELOGDE sessie op (cascade over sessionAccount e.d.) ==="
  reinstall
  nav D > /dev/null
  curl -s -o /dev/null -b "$JARDIR/D.jar" -c "$JARDIR/D.jar" "$BASE/admin/test/login/alice"
  check 1 "$(count_loggedin)" "voorbereiding: D is ingelogd"
  echo "  ... 7s wachten (expiry = 5s) ..."; sleep 7
  s=$(nav E); check 200 "$s" "nieuwe bezoeker E triggert GC"
  check 1 "$(count_sessions)" "verlopen ingelogde sessie is opgeruimd; alleen E over"
  check 0 "$(count_loggedin)" "geen sessionAccount-restanten"
  ;;

login-race)
  echo "=== L5: gelijktijdig: framework-login + nieuwe bezoekers + terugkerende verlopen sessies ==="
  reinstall
  # voorbereiding: 1 ingelogde en 1 anonieme sessie, beide laten verlopen
  nav F > /dev/null
  curl -s -o /dev/null -b "$JARDIR/F.jar" -c "$JARDIR/F.jar" "$BASE/admin/test/login/alice"
  nav G > /dev/null
  check 2 "$(count_sessions)" "voorbereiding: ingelogde F + anonieme G"
  echo "  ... 7s wachten ..."; sleep 7
  pids=(); rm -f "$JARDIR"/lr-*.status
  ( curl -s -o /dev/null -w "%{http_code}" -c "$JARDIR/H.jar" "$BASE/app/navbar" > "$JARDIR/lr-H.status" ) & pids+=($!)
  ( curl -s -o /dev/null -w "%{http_code}" -c "$JARDIR/I.jar" "$BASE/app/navbar" > "$JARDIR/lr-I.status" ) & pids+=($!)
  ( curl -s -o /dev/null -w "%{http_code}" -b "$JARDIR/F.jar" -c "$JARDIR/F.jar" "$BASE/app/navbar" > "$JARDIR/lr-F.status" ) & pids+=($!)
  ( curl -s -o /dev/null -w "%{http_code}" -b "$JARDIR/G.jar" -c "$JARDIR/G.jar" "$BASE/app/navbar" > "$JARDIR/lr-G.status" ) & pids+=($!)
  ( sj=$(mktemp "$JARDIR/J.jar.XXX"); curl -s -o /dev/null -c "$sj" "$BASE/app/navbar"; \
    curl -s -o /dev/null -w "%{http_code}" -b "$sj" -c "$sj" "$BASE/admin/test/login/alice" > "$JARDIR/lr-J.status" ) & pids+=($!)
  wait "${pids[@]}"
  ok=$(grep -l '^200$' "$JARDIR"/lr-*.status | wc -l | tr -d ' ')
  statuses=$(cat "$JARDIR"/lr-*.status | sort | uniq -c | tr -s ' ' | tr '\n' ';')
  echo "  http-statussen (2 nieuw, 2 terugkerend, 1 login): $statuses"
  check 5 "$ok" "alle 5 gelijktijdige requests kregen 200"
  check 5 "$(count_sessions)" "2 verlopen atomen weg; 5 verse sessies over"
  check 1 "$(count_loggedin)" "precies 1 ingelogde sessie (J)"
  ;;

all)
  "$0" execengine-login || FAILED=1
  "$0" execengine-logout || FAILED=1
  "$0" framework-login || FAILED=1
  "$0" gc-loggedin || FAILED=1
  for i in 1 2 3; do "$0" login-race || FAILED=1; done
  ;;

*)
  echo "gebruik: $0 {all|execengine-login|execengine-logout|framework-login|gc-loggedin|login-race}"; exit 2
  ;;
esac

rm -rf "$JARDIR"
exit $FAILED
