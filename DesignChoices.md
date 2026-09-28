# Design choices

Living register of design decisions for the FC5 data-import work and the framework
changes it drove. Newest decisions keep their number forever; a superseded choice is
marked and points to its replacement. Format per global CLAUDE.md §10.

---

**Import via interface-as-schema (resource-API), not the atoms/links dump**
OK-01 · geldig · 2026-07-20 · herkomst: import-sessie, Ampersand #1673

A dataset is imported by driving the resource-API (`POST /resource/SESSION/1/<Interface>`)
with the nested document, so the INTERFACE maps every label to a relation and `Resource::put()`
creates the atoms/links. Rejected: hand-converting each file to the flat `atoms`/`links`
dump and uploading via `/admin/import` — that atom-pair approach does not scale to a corpus
and does not generalise. Rejected: extending the YAML/JSON file importer to be interface-guided
— unnecessary once we saw the resource-API already is.
Technisch: interface `"_SESSION";V[SESSION*Document]`, full CRUD; labels == data labels.

**Root concept `Document` per file**
OK-02 · geldig · 2026-07-20 · herkomst: import-sessie

One `Document` atom per source file. Rejected: rooting at `Land`, because a country recurs
across files (Albanië in AA/DIV/GF…); keying on Land would merge unrelated documents and lose
the document boundary. `Document` also leaves room to hang metadata (version, source) later.

**No recursive interface; unroll the requirement nesting to depth 5**
OK-03 · geldig · 2026-07-20 · herkomst: import-sessie

The `requirements` tree self-nests; modelled with a self-referential relation
`requirements[Eis*Eis]` and an interface unrolled to depth 5. Rejected: a self-referencing
(recursive REF) interface — unproven that the resource-API walks it, and unnecessary.
Verified corpus max depth = 4, so 5 covers all with margin (no leaf dropped).

**Thin, temporary normalizer for migration quirks**
OK-04 · geldig · 2026-07-20 · herkomst: import-sessie

A thin normalizer canonicalises known quirks (crop→crops, mixed `importBans.appliesTo`,
`requirement`/`group` wrappers, nulls) so every label matches an interface label. Treated as
migration defects to be fixed upstream; the normalizer is removable afterwards. Rejected: a
larger interface that swallows every raw variant — it would bake the defects into the model.

**Structural path-signature metric (not value-multiset)**
OK-05 · geldig · 2026-07-20 · herkomst: import-sessie

"100%" is measured by path signatures: each leaf's relation path from the root plus its value,
compared exactly against the reconstructed population (missing, mis-routed and extra all fail).
Rejected: value-only multiset counting — a leaf under the wrong relation would still "match".
The expected counter is cross-checked with a second implementation (Python vs JS).

**`Herkomst` and `Kop` as BIGALPHANUMERIC**
OK-06 · geldig · 2026-07-20 · herkomst: 406-sweep

`origin` (Herkomst) and `sourceHeadingPath` (Kop) carry strings up to ~940 chars; the default
OBJECT atom-id is capped at 254 ("Data entry is too long", 8 files). BIGALPHANUMERIC lifts the
cap. Evidence: the 406-sweep failed on exactly those 8 files; the change closed 94.82%→100%.

**Bulk import via deferred conjunct evaluation (B2)**
OK-07 · geldig · 2026-07-22 · herkomst: performance-analyse (O(n²) import)

Importing n records record-by-record is O(n²) because each transaction's `close()` re-evaluates
the affected conjuncts with a full-population SQL query. B2 keeps the record-by-record load
(bounded memory: each POST is its own request/transaction, so `atomCache` stays per-document)
but **defers conjunct evaluation**: each commit skips the invariant check, and one final pass
(`GET /admin/ruleengine/evaluate/all`) validates the whole result once → O(n).

Rejected alternatives (see the import-perf comparison):
- **A1** (all documents in one request/transaction) — makes large files impossible (whole
  payload in memory). Previously rejected for the same reason; still holds.
- **A2 / A3 / B1** (one giant transaction, streaming input) — trade the conjunct O(n²) for a
  *second* O(n²): the in-memory `atomCache` (`Concept::atomExists` scans it with `in_array`,
  cleared only at commit) grows to O(n), and the MariaDB transaction holds O(n) undo/locks.
  Elegant but not actually linear for large files without a separate atomCache fix.
- **E1** (batches of B per transaction) — O(n²/B); a tunable stopgap, not linear.

Afweging: B2 gives up all-or-nothing atomicity *during* the bulk load — data is committed
durably before the final validation, so a failing invariant leaves committed data, reported as
violations rather than rolled back. Acceptable for a controlled bulk import; documented.
Technisch: `?defer=true` on the resource-POST (read in `ResourceController`) → a defer branch in
`Transaction::close()` that commits without evaluating conjuncts and without persisting their
(unevaluated) cache; the caller runs `evaluate/all` once at the end.

**Incremental invariant maintenance (C1) — separate epic on the backlog**
OK-08 · geldig (backlog) · 2026-07-22 · herkomst: performance-analyse, Ampersand #1675

The principled root-cause fix — evaluate each conjunct incrementally over the delta, not the
full population (DBSP-style IVM) — is scoped as a separate compiler+runtime epic
(AmpersandTarski/Ampersand #1675, subsumes #535). Out of scope for the import work; B2 is the
pragmatic interim.

**Import-bootstrap mode: a locked import screen with a one-time check**
OK-09 · geldig · 2026-08-14 · herkomst: gebruikerswens, voortbouwend op B2 (OK-07)

A prototype configured with `global.importMode` (set by the compiler's defer flag) boots
**locked** into the import screen. Uploads commit with deferred conjunct evaluation (OK-07), so
data loads across many files through inconsistent intermediate states. A "Start checking" action
evaluates all invariants once: **green** unlocks the app permanently (from then on every
transaction checks invariants as usual, so they stay satisfied); **red** keeps it locked with a
message that the application cannot start while invariants are violated. The user may resolve red
by importing more data and checking again.

The lock is enforced **server-side** by a middleware (423 on every request except the
import/check/session endpoints), not only in the UI, so the application's functionality is
genuinely unreachable until the check passes.

Rejected: a UI-only lock (hiding the navigation) — bypassable through the API. Rejected: treating
the import endpoint itself as the whole mechanism — the lock is a product-level phase, not a
per-request flag. The unlock is a flag file on the data volume: it survives restarts and is
one-way (once unlocked, the bootstrap phase is over).

Technisch: setting `global.importMode`; `AmpersandApp::isImportLocked()`; `ImportLockMiddleware`;
`POST /admin/importmode/check` (`ImportModeController`); nav-response fields `importMode` /
`appLocked`. The setting can also be enabled from the environment (`AMPERSAND_IMPORT_MODE`),
which complements — and reduces the dependency on — the compiler `--defer` flag (a separate
Ampersand change). A (re)install re-locks (`relockImportMode`), so a fresh database is checked
again before it unlocks. Verified end-to-end (test/projects/import-bootstrap).

In the frontend, `ImportModeService` mirrors the server-side lock. While the app is locked,
every navigation lands on the import screen, the navigation menu stays hidden, and the import
screen carries the "Start checking" action with the violations of a red check shown inline.
The service watches router events instead of a per-route `canActivate` guard: the generated
routes come from the compiler, so the framework has no place to attach a guard to them, while
router events cover every route uniformly. The regression spec
(`test/projects/import-bootstrap/e2e/`) drives the red→green scenario through the real UI.

**sortByAndHide: the hidden sort column is dropped by a generated constant *ngIf**
OK-12 · geldig · 2026-08-25 · herkomst: gebruikerswens (variant op sortBy)

The `BOX<TABLE sortByAndHide="<column>">` annotation sorts the rows on the named column
while that column is absent from the rendered table. The annotation reaches the template
without compiler changes (the compiler passes every BOX-header key/value generically).
StringTemplate has no string comparison, so the template does not drop the column itself:
every `<th>`/`<td>` carries an Angular `*ngIf` that compares two compile-time literals
(`$any('<columnName>') !== '<sortByAndHide>'`), and Angular leaves the one constant-false
column out of the DOM. The `$any()` keeps strict template type checking from rejecting a
comparison of two distinct literals as unintentional (TS2367). The backend delivers `_sortValues_` on the strength of `sortByAndHide`
alone (`BoxHeader::isSortable()`), so the annotation works without `sortable`.

Rejected: hiding at the component level (app-box-table) — the header and row cells arrive
as projected templates, so the component has no handle on which cell belongs to which
column. Rejected: filtering the column out in StringTemplate — the template language
cannot compare strings, and teaching the compiler a special-cased attribute would breach
the generic key/value contract. Rejected: `BOX<NOVIEW>` on the sub-interface — that hides
the field but leaves nothing to sort on in the table template.

Technisch: `frontend/src/app/generated/.templates/Box-TABLE.html` (the sortBy binding and
the per-cell `*ngIf`), `backend/src/Ampersand/Interfacing/BoxHeader.php` (isSortable),
docs in `docs/reference-material/built-in-box-templates.md`, regression in
`test/projects/box-annotations` (API sort values + rendered component HTML). The hidden
column's values still travel to the browser: presentation, not access control.

**A regression project can declare a failure it understands as "known red"**
OK-13 · geldig · 2026-08-30 · herkomst: regressiesuite, issue #415

A project in `test/projects/` may state `known_fail="<issue>: <reason>"` in its
`regression.conf`. The runner then reports a failing run of that project as "known red"
instead of FAIL, does not let it fail the suite, and reports "no longer failing" the moment
the project passes, so the declaration gets removed. The report keeps showing every known
red with its reason.

Rejected: leaving the project FAIL — a suite that is always red trains its readers to
ignore red, and the exit code of `run-regression.sh all` stops meaning anything. Rejected:
removing or shrinking the project (ifc45) until it installs — the model exists to reach the
scale at which the compiler's broad-table layout breaks (#415); shrinking it hides exactly
that. Rejected: skipping the project silently — the limit must stay visible in every report.

Technisch: `known_fail_of()` and return code 3 in `test/run-regression.sh`; the report line
counts "known red" separately from "failed". First use: `test/projects/ifc45/regression.conf`.

**A service key in a request header lifts the production-mode gate for a machine**
OK-14 · geldig · 2026-09-10 · herkomst: uitrolstraat VoedselVeurElkaar (twee ongewilde herinstallaties op 9 september)

The production-mode gate of `AbstractController::preventProductionMode()` lets a request
through when it carries the value of `global.serviceKey` (environment variable
`AMPERSAND_SERVICE_KEY`) in the `X-Ampersand-Service-Key` header. Every endpoint behind that
gate — the installer, the population exporter, the reports and the test login — is reachable
for a machine that holds the key, while it stays closed for every other request. The decision
lives in one place, `Ampersand\Misc\ServiceKey::productionGuardApplies()`, which the guard
consults with the settings object and `$_SERVER`.

Overwegingen:

1. The purpose is a deployment pipeline that migrates the population when the model changes:
   it calls the exporter and the installer itself. Before this choice an administrator had to
   pick between a pipeline that works (`AMPERSAND_PRODUCTION_MODE` off, and one stray GET on
   `/api/v1/admin/installer` wipes the database) and an application that is protected (the
   production mode on, and the pipeline broken).

2. The mechanism fails closed, which is what makes it safe to add. Without a configured key —
   the default `null` — production mode refuses every request to these endpoints, exactly as
   before; an empty or whitespace-only key counts as no key. A prototype that never sets the
   variable therefore cannot behave differently than it did.

3. The key travels in a request header, not in a query parameter, because the URL of a request
   is written down in the access log of the web server, in the framework's own log records
   (the WebProcessor adds `url` to every record), in the browser history and in the referrer of
   a next request. An application-specific header name is used rather than `Authorization`,
   which belongs to the session of a user and which some Apache configurations strip before PHP
   sees it. Rejected: a query parameter, for the reasons above. Rejected: an allowlist of client
   IP addresses — it identifies a network location instead of a caller and breaks the moment the
   pipeline moves.

4. The comparison runs over the SHA-256 digests of both values with `hash_equals`, so it takes
   the same time for every wrong key and reveals the length of the configured key no more than
   its content. Rejected: `==`, which returns as soon as two characters differ.

5. The key is kept out of every rendering of the system's state: `Settings::set()` masks it in
   the debug log (as it now masks `mysql.dbPass`), the refusal message names no reason at all,
   and the guard passes the settings object and `$_SERVER` rather than the key itself, so a
   stack trace shows `Object(Ampersand\Misc\Settings)` and `Array`. Rejected: a refusal message
   that distinguishes "no key configured" from "wrong key" — it would tell a caller whether
   guessing is worth the effort.

6. The gate is lifted in one place instead of five. Rejected: a check per endpoint, which would
   let the five call sites drift apart, and would leave a sixth endpoint added later unprotected
   or unreachable by accident.

7. The key lifts the production-mode gate and nothing else. The role check
   (`requireAdminRole()`, `rbac.adminRoles`) runs after it and stays in force, and so does the
   `inProductionMode()` check that hides the OpenAPI specification and the administrative menu
   items from the frontend. Rejected: a key that also grants the admin role — that would make
   one environment variable the whole of the access control.

Impact op de specificatie: none. The mechanism is framework runtime; the Ampersand model, the
generated code and the contract with the compiler are untouched.

Impact in productie: an existing deployment that sets no `AMPERSAND_SERVICE_KEY` keeps the
behaviour it has today. A deployment that sets one accepts machine access to its administrative
endpoints, including the installer, which drops and rebuilds the database, and including the
test login, which logs a caller in as any account. The key is therefore as sensitive as the
database password: one key per deployment, stored in the secret store of the platform, rotated
by changing the variable and restarting the container.

**BOX<FACETS> reads the technical type of every facet from interfaces.json and concepts.json in the browser**
OK-15 · geldig · 2026-09-28 · herkomst: gebruikerswens (meekijken op de Artefactenkaart, een Ampersand-applicatie die de artefacten van een reeks projecten en hun samenhang bijhoudt)

`BOX<FACETS>` is a `BOX<TABLE>` with a facet panel. The panel knows every box item's target
concept from `interfaces.json` and that concept's technical type (TType, the `TYPE` of its
`REPRESENT`) from `concepts.json`; both files are compiler output, and the build serves both
under `/assets/`. The TType decides the kind of facet: a value list,
a text field, a range or a date tree, and PASSWORD, the binary types and TYPEOFONE are never a
facet. An OBJECT item with a box of its own carries the items of that box as child facets,
recursively. Filtering and counting happen in the browser, over the rows the interface delivers,
in `facet-engine.ts`.

Overwegingen:

1. The purpose is an overview that a reader narrows down instead of reading whole: the
   Artefactenkaart shows every issue, document and choice at once. A facet
   that fits its type (years for a date, a range for a measure, a text field for prose) is what
   makes the narrowing usable.

2. The compiler hands a box template only the name, label, rendered contents and univalence of
   each box item (`SubObjAttr` in `GenAngularFrontend.hs` of Ampersand v5.9.7), and nothing
   about the items inside an item's own box. The recursion therefore needs the whole interface
   tree, and `interfaces.json` carries it. The frontend already loads that file at start-up
   (`APP_INITIALIZER`); `concepts.json` is one more asset glob in `angular.json` and is loaded on
   first use.

3. Rejected for now: a compiler change that passes the TType of each item to the template. It
   gives the first level only, so the recursion would still need `interfaces.json`; and it puts
   a compiler release, a new compiler image and a framework release between the change and any
   project. It remains a proposal, together with writing the TType straight into
   `interfaces.json`, which would make `concepts.json` unnecessary.

4. Rejected: inferring the type from the values alone. A date and a text that looks like a date
   are indistinguishable, and so are an ALPHANUMERIC and a BIGALPHANUMERIC. The inference stays
   as the fallback for a build without `concepts.json`.

5. The kind of facet also depends on the data, with fixed thresholds in `facet-engine.ts`: a
   number with more than 12 distinct values becomes a range, and an ALPHANUMERIC or OBJECT
   item with more than 40 values that cover at least 80% of the rows becomes a text field,
   because a list that long no longer narrows anything down. The identity item (`I`) is
   always a text field: its value is the row itself. Child facets stop four levels deep,
   which also ends a recursive interface reference. The singleton concept `ONE`, which
   `concepts.json` types as OBJECT, is recognised by its name. These values follow from the
   test model and the Artefactenkaart; an annotation to change them is not needed yet.

6. Rejected: filtering on the server. The interface already delivers every row with every
   column, so the browser has what it needs; one filter step over 5 000 rows and 10 facets took
   about 7 ms (Jest, 2026-09-28; the test "evaluates 5 000 rows and 10 facets" in
   `facet-engine.spec.ts` measures it again). A server-side filter would need a new API. It becomes
   worthwhile when an interface delivers far more rows than a browser holds comfortably.

7. The counts of a facet cover the rows that pass every other facet (values within a facet
   combine with or, facets with and). Rejected: counting over the filtered rows, which drops the
   alternatives of the facet the user just chose from and makes a second choice impossible.

8. The selection lives in the query string (`f.<label path>`, `q` for the search), so a view
   can be bookmarked and sent. Labels keep the URL readable; a label with a period appears by
   its item name. A nested FACETS box prefixes its item name and the atom of the enclosing
   row, so the same box in two rows of a table keeps two selections. A bookmark on a number
   facet keeps its meaning when the facet turns from a value list into a range or back.

Impact op de specificatie: none. A model uses `BOX<FACETS>` where it used `BOX<TABLE>`; the
concepts, relations, rules and the compiler contract stay as they are. The kind of facet follows
the `REPRESENT` of each concept, so a model that stores dates as ALPHANUMERIC gets a value list
for them instead of a date tree.

Impact in productie: a project picks FACETS up with the next framework release and a rebuild.
Its Dockerfile needs no change: the `ng build` in the project image reads `angular.json` from the
framework and copies `concepts.json` along. From then on `concepts.json` is readable without a
login in every project on this version, also in one without FACETS, just as `interfaces.json`
is today. It names concept tables and their columns; `interfaces.json` already carries the same
names in the SQL of every interface, so no information becomes public that was not. The browser loads `concepts.json` once, on the first
FACETS box it opens; for the Artefactenkaart that file is 105 kB, next to 2 MB of
`interfaces.json`.

Technisch: `frontend/src/app/generated/.templates/Box-FACETS.html`,
`frontend/src/app/shared/box-components/box-facets/` (component, `facet-engine.ts` with its unit
tests), `InterfacesJsonService.conceptTypes()`, the `concepts.json` glob in
`frontend/angular.json`, regression in `test/projects/box-facets`.

**BOX<FACETS> takes the box items as table columns and names its facets in the box header**
OK-16 · geldig · 2026-09-28 · herkomst: gebruikerswens, OK-15

The box items of `BOX<FACETS>` are the table columns, as in `BOX<TABLE>`. Two header
annotations choose the facets: `facets="A, B, A.C"` names the facet items in their order, where
`A.C` is item `C` inside the box of item `A`, and `facetOnly="D"` names items that are a facet
but no column. Without `facets` every item is a facet. Every TABLE annotation works unchanged.

Overwegingen:

1. The purpose is a syntax that reads like the templates a modeller already knows. The facet
   list refers to box items by label, as `sortBy` and `sortByAndHide` do, and the annotations
   use the generic header key/values, so the compiler needs no change.

2. The box items stay the columns because the table needs them anyway, and a facet on an item
   uses the same data the column shows. Rejected: prescribed box items as in `FILTEREDDROPDOWN`
   (`"facets" : …, "list" : …`), which would spell every item twice and break the column
   annotations of TABLE.

3. The header grammar allows only `key` or `key="string"` (`pTemplateKeyValue` in the Ampersand
   parser), so a list is a comma-separated string. Rejected: repeating a key
   (`facet="A" facet="B"`), which the parser accepts but no other template does.

4. `facetOnly` hides a column at runtime (`showColumn()` on the component), because
   StringTemplate cannot compare strings and a list cannot become a set of constant `*ngIf`
   literals the way `sortByAndHide` does for a single name.

Impact op de specificatie: an interface gains the two annotations where it wants fewer facets
than columns or a facet without a column. Items that should be a facet but not a column need to
be in the box; there is no facet on an expression outside it. A label that contains a comma or
a period cannot be named in `facets` or `facetOnly`; such an item is still a facet when
`facets` is absent.

Impact in productie: none beyond OK-15.

**The table of BOX<FACETS> keeps all rows as its data and shows the rows that pass the facets**
OK-17 · geldig · 2026-09-28 · herkomst: merge-gate van OK-15 (correctheidswachter)

The `app-box-table` inside a FACETS box receives all rows of the box as `data` and the rows
that pass the facets as `shownRows`, a new optional input of `BoxTableComponent`. Emptiness,
create and delete therefore work on all rows, as in `BOX<TABLE>`. A row created through the
table stays visible until the selection changes. The panel recounts whenever the number of
rows changes and after every patch (`patched`), because an edit merges into the rows in place.

Overwegingen:

1. The purpose is that every TABLE annotation keeps its meaning inside FACETS. With the
   filtered rows as `data`, `hideOnNoRecords` hid the table as soon as the facets left no
   row, and a UNI box whose only row was filtered away offered a Create button; both follow
   from `isEmpty()` in `BaseBoxComponent`, which counts `data`.

2. With all rows as `data`, `deleteItem` removes the row from the rows of the box itself, so
   the panel needs no bookkeeping of its own. Rejected: taking a deletion from the filtered
   rows and repeating it on the source, which the first version did; it leaned on the
   internal splice of `deleteItem`.

3. A created row stays visible because the user would otherwise see nothing happen after
   Create: the new row has no values yet and passes almost no facet. Only the rows that
   `createItem` puts at the front count as created; rows that a sync with the server adds
   follow the facets. Rejected: pinning every row that was not visible before, which brought
   back every row the selection hid.

4. Rejected: comparing the rows at every change detection to notice an edit. That costs a
   walk over all rows and facets many times per second; the `patched` event of the interface
   marks the moment exactly.

Impact op de specificatie: none. The interfaces and the model stay as they are.

Impact in productie: `BoxTableComponent` has one more input; a `BOX<TABLE>` does not set it
and behaves as before. The regression suite runs over all projects to confirm that.

Technisch: `shownRows` in `box-table.component.{ts,html}`, `[data]` and `[shownRows]` in
`Box-FACETS.html`, `ngDoCheck` and `sourceChanged()` in `box-facets.component.ts`, unit
tests in `box-facets.component.spec.ts`.
