# Two contexts on one database: the school includes its library, and both are named Campus.
# The town has a database of its own and reads the school and its library through views.

kind() { sql "$1" "SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$2'"; }
column() { sql "$1" "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$2' AND COLUMN_NAME = '$3'"; }
members() { sql "$1" "SELECT GROUP_CONCAT(\"Library.Member\" ORDER BY 1) FROM \"$2\""; }

expect "the school and its library have one application, the town another" "campus town" \
  "$(jq -r '[.contexts[].service] | sort | join(" ")' "$system")"
expect "the application of the school installs the database Campus" "Campus" "$(db_of campus)"

install_all

expect "the library stores its members in a table of the database Campus" "BASE TABLE" "$(kind campus library.member)"
expect "a univalent relation of the school on a member is a column in that table" "1" "$(column campus library.member school)"
expect "the town knows the persons of the school by a view" "VIEW" "$(kind town school.person)"
expect "and the members of the library" "m1,m2" "$(members town school.library.member)"

# A user of the school deletes a person who is a member of the library. The table of the members is a
# table of the library, with a name of the library, on the database of the school: the member goes too.
expect "the user's transaction is committed" "true" \
  "$(api_delete campus resource/SESSION/1/Persons/m2 | jq -r '.isCommitted')"
expect "the member leaves the table of the library on the shared database" "m1" "$(members campus library.member)"
expect "and the town sees that" "m1" "$(members town school.library.member)"
