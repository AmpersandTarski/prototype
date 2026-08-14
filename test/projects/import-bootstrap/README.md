# import-bootstrap — regression vehicle

**Guards:** import-bootstrap mode (DesignChoices OK-09): a prototype with
`global.importMode` boots locked into the import screen (server-side 423 on
everything else, navigation hidden in the UI); imports commit with deferred
invariant checking; "Start checking" runs the one-time full check — red keeps
the app locked and shows the violations, green unlocks it permanently and a
(re)install re-locks it.

**Origin:** DesignChoices OK-09 (built on the B2 defer primitive, OK-07).

**Run:** `test/run-regression.sh import-bootstrap`, or by hand:
`./generate.sh import-bootstrap main.adl` with `global.importMode: true` in
`backend/config/project.yaml` (or env `AMPERSAND_IMPORT_MODE=true`), then open
`http://localhost`.

**Green means:** with import mode on, every route lands on the import screen
and the menu is hidden; importing a Person without a name (violating the TOT
invariant) commits anyway; "Start checking" reports the violation and keeps
the app locked; after importing the name, "Start checking" unlocks the app,
the menu returns, and other routes answer normally again.

The e2e spec builds the Angular frontend into `html/` itself (the regression
runner only prepares the backend API).
