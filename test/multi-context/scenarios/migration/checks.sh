# The migration of "Data Migration under a Changing Schema in Ampersand" (RAMiCS 2024), as three contexts.
# The existing and the desired system are two versions of the context Kurk, each on a database of its own.
# The migration context includes both, copies the data, and shows what users have to repair.
# The checks follow the phases of the paper: pre-deployment, the moment of transition (MoT),
# the repair by users, and the moment of completion (MoC).

# The signals as a user gets them: read from the violations that the database of the application holds.
signals() { api "$1" admin/ruleengine/evaluate/all | jq -r '[.signals[] | .violations[] | .message] | sort | join("; ")'; }
stored() { sql "$1" 'SELECT GROUP_CONCAT("src" ORDER BY 1) FROM "__conj_violation_cache__"'; }
invariants() { api "$1" admin/ruleengine/evaluate/all | jq -r '[.invariants[] | .message] | join("; ")'; }
atoms() { sql "$1" "SELECT GROUP_CONCAT(\"$2\" ORDER BY 1) FROM \"$2\""; }
pairs() { sql "$1" "SELECT GROUP_CONCAT(CONCAT(\"A\", '-', \"r\") ORDER BY 1) FROM \"a\" WHERE \"r\" IS NOT NULL"; }
# What a user of the migration system does in the desired system; each prints "<committed>: <messages>".
outcome() { jq -r '(.isCommitted | tostring) + ": " + ([.notifications.invariants[]?.ruleMessage] | join("") | gsub("\n"; ""))'; }
pair() { api_patch migration "resource/SESSION/1/Desired/$1" "[{\"op\":\"replace\",\"path\":\"/r\",\"value\":\"$2\"}]" | outcome; }
unpair() { api_patch migration "resource/SESSION/1/Desired/$1" "[{\"op\":\"remove\",\"path\":\"/r\",\"value\":\"$2\"}]" | outcome; }
create() { api_post migration "resource/SESSION/1/Desired" '{}' | outcome; }

expect "the two versions of Kurk get two databases" "kurk_1 kurk_2" "$(db_of old) $(db_of new)"

# Pre-deployment --------------------------------------------------------------------------------
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
expect "the compiler says that its violations can only disappear" "true" \
  "$(jq -r '.signals[] | select(.name == "new.totalR") | .hardens' "$work/migration/generics/rules.json")"
expect "and an invariant in the model of the desired system" "totalR" \
  "$(jq -r '[.invariants[].name | select(test("totalR"))] | join(",")' "$work/new/generics/rules.json")"
expect "the migration context has no table of its own to register violations" "0" \
  "$(sql migration "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE '%fixed%'")"

# Users still work in the existing system. Whatever that system allows, is allowed:
# a new atom with a pair, and a new atom without one.
sql old "INSERT INTO \"a\" (\"A\", \"r\") VALUES ('a4', 'b3'), ('a5', NULL)" >/dev/null

# The moment of transition ----------------------------------------------------------------------
# The migration system catches up with the existing system, and from here on users work in the migration system.
api migration admin/execengine/run >/dev/null
expect "the atoms that the existing system got meanwhile reach the desired system" "a1,a2,a3,a4,a5" "$(atoms new a)"
expect "and so do their pairs" "a1-b1,a4-b3" "$(pairs new)"
expect "the migration context signals what users have to repair" \
  "Pair a2 with an (any) atom from B.; Pair a3 with an (any) atom from B.; Pair a5 with an (any) atom from B." "$(signals migration)"
expect "these violations are in the database of the migration system, where the framework keeps them" "a2,a3,a5" "$(stored migration)"

# After the transition a user has the desired system, except for the violations that are left to repair.
expect "taking the pair of a1 away is refused, as the desired system would" "false: Relation r must be total." "$(unpair a1 b1)"
expect "and its pair is still there" "a1-b1,a4-b3" "$(pairs new)"
expect "a new atom without a pair is refused, as the desired system would" "false: Relation r must be total." "$(create)"
expect "and no atom was added" "a1,a2,a3,a4,a5" "$(atoms new a)"
expect "a user gives a1 another pair" "true: " "$(pair a1 b2)"
expect "and the copy does not bring the old pair back" "a1-b2,a4-b3" "$(pairs new)"

# A user repairs a violation. From then on it cannot return.
expect "a user repairs a2" "true: " "$(pair a2 b2)"
expect "the violation of a2 is gone from the database" "a3,a5" "$(stored migration)"
expect "and from the signals" \
  "Pair a3 with an (any) atom from B.; Pair a5 with an (any) atom from B." "$(signals migration)"
expect "the repaired violation cannot return" "false: Relation r must be total." "$(unpair a2 b2)"
expect "while a3 can still be saved without a pair" "true" \
  "$(api_patch migration resource/SESSION/1/Desired/a3 '[]' | jq -r '.isCommitted')"

# A user of the migration system removes an atom from the desired system that the existing system still has.
# The migration context writes the desired system, and never the existing one.
expect "the user's transaction is committed" "true" \
  "$(api_delete migration resource/SESSION/1/Desired/a3 | jq -r '.isCommitted')"
expect "the existing system keeps the atom" "a1,a2,a3,a4,a5" "$(atoms old a)"
expect "and its pair" "a1-b1,a4-b3" "$(pairs old)"
expect "the classification brings the atom back to the desired system" "a1,a2,a3,a4,a5" "$(atoms new a)"

# The database agrees: the user of the migration application has no right to write the existing system.
expect "the database refuses the migration application a write in the existing system" "refused" \
  "$(sql_as migration old "DELETE FROM \"a\" WHERE \"A\" = 'a3'")"
expect "and lets it read the existing system" "a1,a2,a3,a4,a5" \
  "$(sql_as migration old 'SELECT GROUP_CONCAT("A" ORDER BY 1) FROM "a"')"
expect "the database refuses the existing application every access to the desired system" "refused" \
  "$(sql_as old new 'SELECT COUNT(*) FROM "a"')"

# Users repair the last violations.
expect "a user repairs a3" "true: " "$(pair a3 b1)"
expect "a user repairs a5" "true: " "$(pair a5 b3)"
expect "no violation of the new invariant is left in the database" "NULL" "$(stored migration)"
expect "nor in the signals" "" "$(signals migration)"

# Once the last violation is repaired, the rule works as the invariant of the desired system.
expect "no atom can lose its pair" "false: Relation r must be total." "$(unpair a5 b3)"
expect "the pairs are as the users left them" "a1-b2,a2-b2,a3-b1,a4-b3,a5-b3" "$(pairs new)"

# The moment of completion ----------------------------------------------------------------------
# The desired system keeps its blocking invariant on the database as it is.
# Its application has run on the model of desired.adl all along: nothing is compiled or installed again.
expect "the desired system satisfies its invariant on its own database" "" "$(invariants new)"
expect "no data was moved at the moment of completion" "a1-b2,a2-b2,a3-b1,a4-b3,a5-b3" "$(pairs new)"
