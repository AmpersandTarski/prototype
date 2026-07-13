# any-role — regression vehicle

**Guards:** the "Any"-role access model: an interface without a FOR-clause is
coupled to the wildcard role "Any" and reachable for every session; interfaces
with an explicit FOR-clause are reachable only when that role is active.

**Origin:** v2.4.4 (compiler v5.9.1/v5.9.2: `sessionAllowedRoles` was the full
`SESSION*Role` cartesian, granting every session every role; role-less
interfaces were missing from the navigation menu).

**Run:** `./generate.sh any-role main.adl` (containers up), then open
`http://localhost` and run the installer.

**Green means:** with active role `SomeScriptRole`: `PublicIfc` and
`ScriptRoleIfc` reachable, `AnonIfc` not (403). With active role `Anonymous`:
`AnonIfc` reachable, `ScriptRoleIfc` not. `PublicIfc` appears in the navigation
menu in both cases.
