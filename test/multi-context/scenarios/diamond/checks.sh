# A diamond: City includes Registry and Shops, and both include Towns.
# Towns is one context with one database, however many contexts include it.

signals() { api city admin/ruleengine/evaluate/all | jq -r '[.signals[] | .message as $m | .violations[] | $m + ": " + .message] | join("; ")'; }
kind() { sql "$1" "SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$2'"; }

install_all

expect "Towns stores the towns in a table of its own" "BASE TABLE" "$(kind towns town)"
expect "City knows the towns by a view on that table" "VIEW" "$(kind city towns.town)"
expect "Registry knows the towns by a view on that table" "VIEW" "$(kind registry towns.town)"
expect "City stores its own relation" "BASE TABLE" "$(kind city customer)"
expect "Registry has no table of City" "" "$(kind registry customer)"

expect "City reads the towns of Towns" "Enschede,Heerlen" "$(sql city 'SELECT GROUP_CONCAT("Town" ORDER BY 1) FROM "towns.town"')"
expect "a rule of City composes relations of Shops and Registry over the towns of Towns" \
  "Violation of rule 'local': s2[Shop],p1[Person]" "$(signals)"

# A fact is updated in one place, and every context that reaches it sees the change.
sql shops "UPDATE \"shop\" SET \"located\" = 'Enschede' WHERE \"Shop\" = 's2'" >/dev/null
expect "a change in the database of Shops removes the violation in City" "" "$(signals)"

sql towns "INSERT INTO \"town\" (\"Town\") VALUES ('Zwolle')" >/dev/null
expect "a new town in Towns is a town in Registry" "3" "$(sql registry 'SELECT COUNT(*) FROM "towns.town"')"
expect "and in Shops" "3" "$(sql shops 'SELECT COUNT(*) FROM "towns.town"')"
