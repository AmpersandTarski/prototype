# The migration of "Data Migration under a Changing Schema in Ampersand" (RAMiCS 2024), as three contexts.
# The existing and the desired system are two versions of the context Kurk, each on a database of its own.
# The migration context includes both, copies the data, and shows what users have to repair.

signals() { api "$1" admin/ruleengine/evaluate/all | jq -r '[.signals[] | .violations[] | .message] | sort | join("; ")'; }
invariants() { api "$1" admin/ruleengine/evaluate/all | jq -r '[.invariants[] | .message] | join("; ")'; }
atoms() { sql "$1" "SELECT GROUP_CONCAT(\"$2\" ORDER BY 1) FROM \"$2\""; }
pairs() { sql "$1" "SELECT GROUP_CONCAT(CONCAT(\"A\", '-', \"r\") ORDER BY 1) FROM \"a\" WHERE \"r\" IS NOT NULL"; }

expect "the two versions of Kurk get two databases" "kurk_1 kurk_2" "$(db_of old) $(db_of new)"

install_all

# Step 1 and 2 of the paper: the atoms and the pairs of the existing system are brought to the desired system.
expect "the existing system keeps its atoms" "a1,a2,a3" "$(atoms old a)"
expect "the classification brought the atoms to the database of the desired system" "a1,a2,a3" "$(atoms new a)"
expect "the pairs of r have been copied to the database of the desired system" "a1-b1" "$(pairs new)"
expect "the migration context stores what it copied in its own database" "a1-b1" \
  "$(sql migration "SELECT GROUP_CONCAT(CONCAT(\"old.A\", '-', \"old.B\")) FROM \"copyr\"")"
expect "the existing system is untouched" "a1-b1" "$(pairs old)"

# Step 5: the new invariant is a business constraint in the migration context,
# and a blocking invariant in the desired system: one rule, written once, in desired.adl.
expect "the rule is a signal in the model of the migration context" "new.totalR" \
  "$(jq -r '[.signals[].name | select(test("totalR"))] | join(",")' "$work/migration/generics/rules.json")"
expect "and an invariant in the model of the desired system" "totalR" \
  "$(jq -r '[.invariants[].name | select(test("totalR"))] | join(",")' "$work/new/generics/rules.json")"
expect "the migration context signals what users have to repair" \
  "Pair a2 with an (any) atom from B.; Pair a3 with an (any) atom from B." "$(signals migration)"

# Steps 3 and 4: the compiler added a relation that registers what satisfies the rule,
# and a blocking invariant that keeps what is registered from violating the rule again.
fixed() { sql migration 'SELECT GROUP_CONCAT("new.SrcA" ORDER BY 1) FROM "fixedtotalr"'; }
unpair() { api_patch migration "resource/SESSION/1/Desired/$1" "[{\"op\":\"remove\",\"path\":\"/r\",\"value\":\"$2\"}]"; }
expect "the atom that satisfies the rule is registered" "a1" "$(fixed)"
expect "taking its pair away is refused" \
  "false: A violation of new.totalR that has been repaired cannot return." \
  "$(unpair a1 b1 | jq -r '(.isCommitted | tostring) + ": " + ([.notifications.invariants[].ruleMessage] | join("") | gsub("\n"; ""))')"
expect "and its pair is still there" "a1-b1" "$(pairs new)"

# A user of the migration system removes an atom from the desired system that the existing system still has.
# The migration context writes the desired system, and never the existing one.
expect "the user's transaction is committed" "true" \
  "$(api_delete migration resource/SESSION/1/Desired/a1 | jq -r '.isCommitted')"
expect "the existing system keeps the atom" "a1,a2,a3" "$(atoms old a)"
expect "and its pair" "a1-b1" "$(pairs old)"
expect "the classification brings the atom back to the desired system" "a1,a2,a3" "$(atoms new a)"
expect "the pair that the user removed is not copied again" "NULL" "$(pairs new)"

# The database agrees: the user of the migration application has no right to write the existing system.
expect "the database refuses the migration application a write in the existing system" "refused" \
  "$(sql_as migration old "DELETE FROM \"a\" WHERE \"A\" = 'a3'")"
expect "and lets it read the existing system" "a1,a2,a3" \
  "$(sql_as migration old 'SELECT GROUP_CONCAT("A" ORDER BY 1) FROM "a"')"
expect "the database refuses the existing application every access to the desired system" "refused" \
  "$(sql_as old new 'SELECT COUNT(*) FROM "a"')"

# Traffic in the existing system goes on during the migration: a new atom and a new pair.
sql old "INSERT INTO \"a\" (\"A\", \"r\") VALUES ('a4', 'b3')" >/dev/null
api migration admin/execengine/run >/dev/null
expect "a new atom of the existing system reaches the desired system" "a1,a2,a3,a4" "$(atoms new a)"
expect "and so does its pair" "a4-b3" "$(pairs new)"

# Users repair the violations in the desired system.
sql new "UPDATE \"a\" SET \"r\" = 'b2' WHERE \"A\" IN ('a1', 'a2', 'a3')" >/dev/null
api migration admin/execengine/run >/dev/null
expect "no violation of the new invariant is left" "" "$(signals migration)"

# Once the last violation is repaired, the rule holds for every atom as an invariant does:
# all of them are registered, and none of them can lose its pair.
expect "every atom is registered" "a1,a2,a3,a4" "$(fixed)"
expect "a repaired violation cannot return" "false" "$(unpair a2 b2 | jq -r '.isCommitted')"
expect "the pair of the repaired atom is still there" "a1-b2,a2-b2,a3-b2,a4-b3" "$(pairs new)"

# The moment of completion: the desired system keeps its blocking invariant on the database as it is.
# Its application has run on the model of desired.adl all along: nothing is compiled or installed again.
expect "the desired system satisfies its invariant on its own database" "" "$(invariants new)"
expect "no data was moved at the moment of completion" "a1-b2,a2-b2,a3-b2,a4-b3" "$(pairs new)"
