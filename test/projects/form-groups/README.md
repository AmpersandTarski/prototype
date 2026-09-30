# form-groups — regression vehicle

**Guards:** what a FORM shows. A field with a value appears; an empty field appears only
when the user may fill it, when the box says `showSubOnNoRecords`, or when it is a group
whose box says `showOnNoRecords` (then with a dash). A group, a FORM box on `I`, is left
out when all of its fields are empty. The FORM gives each field a kind (group, meta) and a
field with several values shows their number. A screen on one item carries that item as
its title. The New menu leaves out an API with create rights.

**Origin:** DesignChoices OK-20 (the fields a FORM shows), OK-21 (the item as title) and
OK-23 (the New menu). The request came from an interview with the modeler of a large
Ampersand application, a map of project artefacts, whose detail screens listed every
field, filled or not.

**Run:** `test/run-regression.sh form-groups`, or by hand: `./generate.sh form-groups` and
open `http://localhost/requirement/R1`.

**Green means:** every assertion in `e2e/test.mjs` passes. The spec builds the frontend
itself and drives it with Puppeteer.

The model (`model/main.adl`) has one detail interface, `Requirement`, with groups
(Properties, Content, Mentioned by, Trace, History), an editable field (Note), an empty
read-only field (Reviewers) and an empty group with and without `showOnNoRecords`.
`RequirementAll` says `showSubOnNoRecords`. `API NewRequirement` has create rights.
