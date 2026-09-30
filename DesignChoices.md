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
OK-08 · vervangen door OK-19 · 2026-07-22 · herkomst: performance-analyse, Ampersand #1675

The principled root-cause fix — evaluate each conjunct incrementally over the delta, not the
full population (DBSP-style IVM) — is scoped as a separate compiler+runtime epic
(AmpersandTarski/Ampersand #1675, subsumes #535). Out of scope for the import work; B2 is the
pragmatic interim.

**The runtime keeps the violation cache up to date from the touched pairs when a setting asks for it, and evaluates in full otherwise**
OK-19 · geldig · 2026-09-29 · herkomst: Ampersand #1684 (phase 4), PR #449; measurements in Ampersand DC-12 and DC-14

The setting `transactions.deltaConjunctMaintenance` has three values, `off`, `shadow` and `on`,
and `off` is the default. Under `on`, a conjunct for which the compiler emitted candidate queries
has its rows in the violation cache recomputed only for the candidates that the pairs touched in
the transaction yield; every other conjunct is evaluated in full. Under `shadow` both routes run
and the full evaluation is authoritative. A value outside the three stops the application at boot
with a message that names the setting.

Overwegingen:

1. The purpose is a transaction close whose cost follows the size of the change instead of the
   size of the database. The compiler side of this lives in Ampersand #1684 and the epic of
   OK-08 (#1675); this choice is the runtime side.

2. The default is `off` because the delta route does not win yet at today's population sizes.
   A close on FC5 takes 4.2 ms (median) with the delta route and 3.1 ms without; on RAP it takes
   47.0 ms against 40.4 ms (Ampersand DC-12 and DC-14). The gain has to come from database growth,
   and the cost gate of Ampersand #1692 is meant to choose per conjunct.

3. Correctness never depends on the delta route. A conjunct touched via a concept, a relation
   that underwent a bulk mutation, and a relation without a candidate query keep full evaluation.
   In the FC5 shadow run of 1 142 replayed transactions the two routes gave the same result every
   time.

4. The touched pairs reach the delta tables only inside an open database transaction. A write in
   autocommit mode, such as the session's `lastAccess`, is committed at once, and its row would
   outlive the request.

5. The clean-conjunct check of `transactions.skipCleanConjuncts` (#443) and the delta route share
   one helper: a conjunct evaluated in this transaction with no mutation afterwards needs neither
   evaluation nor delta maintenance.

6. Rejected: a boolean setting. The shadow mode is how a production run shows that both routes
   agree before anyone relies on the delta route, and a boolean leaves no room for it. Rejected:
   treating every value other than `off` as switched on; a YAML `false` or a typo would then
   switch the feature on without anyone noticing.

Impact op de specificatie: none. A model gains and loses nothing. The candidate queries come from
the compiler, and with a compiler that emits none (every release up to and including v5.9.7) the
setting is a no-op.

Impact in productie: with the default a prototype behaves as in v2.10.0: nothing is recorded in
delta tables and the evaluation loop is unchanged. Under `on` or `shadow` the database needs the
delta tables that a delta-capable compiler writes into `database.sql`. A production database
installed before such a compiler lacks them, so switching the setting on there requires a
migration of those tables.

Technisch: the routes are in `Transaction::evaluateAffectedConjunctsWithDelta()` and
`Transaction::isSkippableCleanConjunct()`, the maintenance itself in `Conjunct::deltaMaintain()`,
the recording of touched pairs in `MysqlDB`, and the check on the value in
`backend/bootstrap/framework.php`. The regression projects `delta-conjunct-maintenance` and
`skip-clean-conjuncts` guard the three values.

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
   row, so the same box in two rows of a table keeps two selections. The form of a value
   decides how it filters (`~text` a text field, `min..max` on a number a range, anything
   else chosen values), so a bookmark keeps its meaning when the data changes and a facet
   turns from a value list into a text field or a range, or back.

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
   Create: the new row has no values yet and passes almost no facet. A row counts as created
   when it appears in front of every known row, which is where `createItem` puts it in a
   list, and when it replaces the empty value of a UNI box. A set of rows that shares no row
   with the known ones is a new load and keeps nothing visible. Rows that a sync appends
   follow the facets; a sync that returns the rows of a nested box in a new order can put a
   row in front, and that row then also stays visible until the selection changes. Rejected:
   pinning every row that was not visible before, which brought back every row the
   selection hid.

4. Rejected: comparing the rows at every change detection to notice an edit. That costs a
   walk over all rows and facets many times per second; the `patched` event of the interface
   marks the moment exactly.

Impact op de specificatie: none. The interfaces and the model stay as they are.

Impact in productie: `BoxTableComponent` has one more input; a `BOX<TABLE>` does not set it
and behaves as before. The regression suite runs over all projects to confirm that.

Technisch: `shownRows` in `box-table.component.{ts,html}`, `[data]` and `[shownRows]` in
`Box-FACETS.html`, `ngDoCheck` and `sourceChanged()` in `box-facets.component.ts`, unit
tests in `box-facets.component.spec.ts`.

**A modeller chooses the kind of a facet with facetKind, where the technical type does not fit the data**
OK-18 · geldig · 2026-09-28 · herkomst: voorstel van de bouwer bij PR #464 (akkoord van Stef, 28 september 2026)

`BOX<FACETS facetKind="Date=date, Size=range">` gives an item the kind of facet it names
(`list`, `text`, `range` or `date`) instead of the kind its technical type and its data give.
Under `date`, a text that starts with a date counts under that date, and a text without one
counts as *(empty)*. Under `range`, a text that is a number counts as that number.

Overwegingen:

1. The purpose is a usable facet for data that the model types more loosely than it is. The
   Artefactenkaart keeps its dates as ALPHANUMERIC, because 13 of its 1 859 dates carry a
   remark ("2026-08-10, herzien 2026-09-17") that a DATE column refuses; as a value list those
   dates give one line per day.

2. The annotation lives in the box header, next to `facets` and `facetOnly`, so it needs no
   compiler change. Rejected: an annotation per box item, which the Ampersand grammar does not
   have (a box item carries no key/value pairs); it would need a parser change and a compiler
   release.

3. Rejected: changing the thresholds of OK-15 (12 numbers, 40 values) per box. A threshold
   moves the boundary for every item at once; the kind per item says what the modeller means.

4. The structural answer remains a correct `REPRESENT`: the documentation says to prefer it, and
   `facetKind` is for data that does not allow it yet.

Impact op de specificatie: an interface gains `facetKind` where an item's type does not fit its
data. The model itself does not change.

Impact in productie: none beyond OK-15. A name in `facetKind` that matches no item, or a kind
outside the four, leaves a warning in the browser console and changes nothing. Without
`facetKind` the kinds stay as OK-15 describes, also for an item whose type is unknown: only a
whole date or a JSON number makes such an item a date tree or a number facet.

Technisch: `chosenKinds()` in `facet-engine.ts`, the `facetKind` input of `BoxFacetsComponent`,
`Box-FACETS.html`, and the interface `TicketsReported` in `test/projects/box-facets`.

---

**A FORM shows the fields a record has, and the fields the user may fill**
OK-20 · voorstel · 2026-09-29 · herkomst: interview met Stef over de Artefactenkaart (29 september 2026), tak ux-intenties

A FORM leaves out a field that is empty and that the user cannot fill (no `C` or `U` in its
CRUD). A group, a FORM box on `I` inside a FORM, is left out when all of its fields are empty.
The annotation `showOnNoRecords` on a group shows it while empty, `showSubOnNoRecords` on a
FORM shows every field, and `hideSubOnNoRecords` also hides an empty field the user may edit.
Groups sit side by side while the width allows.

Overwegingen:

1. The purpose is a detail screen that shows what a record has. On the Artefactenkaart, the
   screen of requirement R01 listed 27 labels, 18 of them without a value.

2. Stef put it this way in the interview: "Ik denk dat een lege groep by default er niet staat.
   Maar misschien heeft het zin een lege groep te tekenen. Dat vereist dan een annotatie in de
   interface." An empty group can carry information, such as a requirement that no rule or
   interface mentions yet; `showOnNoRecords` is for that case.

3. A field the user may fill stays visible while empty, because otherwise the value cannot be
   entered through that screen. Editability decides, and the data alone does not.

4. The compiler gives a box template the name, label and contents of each field, but not its
   CRUD or multiplicity. The FORM reads them from `interfaces.json` in the browser, as BOX<FACETS>
   does (OK-15). Rejected: a compiler change that passes CRUD and UNI per field into the
   template; it needs a compiler release for something the browser already has.

5. Rejected: keeping every field by default and asking the modeler for `hideSubOnNoRecords` on
   every box. That is an obligation on every interface, and the Ampersand design considerations
   accept an obligation only when it is a necessity.

6. Rejected: hiding empty fields with CSS (`:has()` on an empty value). CSS does not know
   whether the user may edit a field, so it would also hide a field the user must fill.

7. Each field has a kind that a subtle style follows: group, meta (a UNI field with a short
   value), content (a UNI field with a long value) and related (a field with several values).
   The kinds come from the model without annotation. Stef named three kinds of blocks in the
   interview (meta-information, content, related information); that the kind of a field follows
   from its multiplicity and length is a hypothesis that awaits his verdict.

Impact op de specificatie: a modeler groups fields that belong together in a FORM box on `I`
and adds `showOnNoRecords` where an empty group tells the user something. Existing interfaces
need no change.

Impact in productie: every FORM without annotation shows fewer rows than before, namely only
those with a value or with edit rights. An interface that relied on seeing an empty read-only
field gets `showSubOnNoRecords`. The regression projects measure the new default.

Technisch: `BoxFormComponent.showField()`, `fieldKind()` and `showsNothing()`,
`InterfacesJsonService.fieldMetas()`, `Box-FORM.html`, and `box-form.component.spec.ts`.

---

**A screen on one item carries that item as its title**
OK-21 · voorstel · 2026-09-29 · herkomst: UX-verkenning van de Artefactenkaart (29 september 2026), tak ux-intenties

The heading of an interface on `I[Concept]` is the label of the item it shows, with the label of
the interface small above it. A session interface and a list keep the interface label as heading.

Overwegingen:

1. The purpose is a page that names what the user looks at: "R01 Leerbaarheid" rather than "Eis".

2. The label comes from the VIEW of the concept, which the modeler already defines for links and
   dropdowns, so the title costs no annotation.

3. The identity field (`"Eis" : I`) repeats the title; a modeler may leave it out of a detail
   interface.

Impact op de specificatie: none required.

Impact in productie: `component.html` renders `app-interface-heading`, which keeps an `h3` with
the interface label, so a test that looks for the interface label still finds it.

Technisch: `InterfaceHeadingComponent` and `component.html`.

---

**BOX<FACETS> drops a column whose value the panel already tells, and has one search field**
OK-22 · voorstel · 2026-09-29 · herkomst: interview met Stef over de Artefactenkaart (29 september 2026), tak ux-intenties

A column leaves the table while at least two rows pass, the item has a value-list or date facet,
every passing row has the same value (or none), and the user cannot edit the item. The facet
shows that value checked. The panel shows no text facet on an item of the box itself, because
the search field searches every value of a row.

Overwegingen:

1. The purpose is room for the columns that differ. Stef: "Ik zou wel willen dat een kolom
   waarin voorspelbaar 0 of 1 waarden in voorkomen, niet wordt getoond om ruimte te besparen."
   After choosing Patents, the column Project repeats "Patents" in every row.

2. Stef also asked for the value to stay visible: "wel een vinkje in open". The facet shows it
   checked, also when no one chose it.

3. Searching for gaps with the facet *(empty)* seemed to conflict with the rule. It does not:
   the facet still shows *(empty)*, and a column the user must fill is editable and stays.
   Rejected: a setting per box, which Stef suggested as a fallback; the two conditions make it
   unnecessary.

4. With one passing row every column is constant; the rule then keeps all columns, since a
   single row is read as a record.

5. Stef found two search fields confusing. The search field searches every value of a row,
   the identity item included (`rowText()` in `facet-engine.ts`), so a text facet on an item
   of the box adds nothing. A text facet on a child item filters a different thing and stays.

Impact op de specificatie: none.

Impact in productie: the table of a faceted list shows fewer columns while a selection fixes
their value; the URL and the counts do not change.

Technisch: `BoxFacetsComponent.findConstantColumns()`, `showColumn()`, `isImplied()` and
`showsFacet()`, and six cases in `box-facets.component.spec.ts`.

---

**The content follows the width of the window, and the menu folds below 1200 pixels**
OK-23 · voorstel · 2026-09-29 · herkomst: snapshots van de Artefactenkaart op 2560×1440 en 1080×1920 (29 september 2026), tak ux-intenties

The content column has no fixed width. Below 1200 pixels the menu is a drawer that the
hamburger opens. The New menu lists no API interface.

Overwegingen:

1. The purpose is a prototype that uses a wide screen and fits a portrait screen. At 2560 pixels
   the content stood at 1504 pixels with about 365 pixels empty on each side; at 1080 pixels
   wide the menu kept 300 pixels, and a table of 774 pixels got about 430.

2. Rejected: a media query on orientation. A narrow window on a landscape screen has the same
   problem as a portrait screen; the width decides.

3. An API such as `API NieuwEis : I[Eis] CRuD` serves programs. On the Artefactenkaart, such APIs
   put 24 "New …" items above the 37 lists a person uses.

Impact op de specificatie: none.

Impact in productie: on screens between 992 and 1199 pixels wide the menu is now a drawer; above
1960 pixels the content is wider than before.

Technisch: `_responsive.scss`, `_menu.scss`, `_topbar.scss`, `LayoutService.isDesktop()` and
`AngularJSApp::getMenuItems()`.

**BOX<MARKUP> and app-atomic-markup show a text formatted in its markup language**
OK-24 · geldig · 2026-09-29 · herkomst: gebruikerswens (Stef, bij het lezen van de Artefactenkaart), AmpersandTarski/Ampersand#1700

`app-atomic-markup` shows a text in one of four formats: `MARKDOWN`, `GFM` (GitHub-flavoured
Markdown), `HTML` or `TEXT`; an unknown format name gives `TEXT` and one console warning. Two
templates use it. `BOX <MARKUP MARKDOWN> [ "text" : explanation ]` sits on the object that owns
the texts and formats every item of the box; `formatFrom="fmt"` takes the format per row from the
item `fmt`, which itself shows nothing. A project's own `Concept-<name>.html` formats every text
of one concept, in every interface, and keeps the text area where the field may be updated.
Markdown is rendered in the browser by `marked`; all HTML reaches the page through Angular's
sanitiser.

Overwegingen:

1. The purpose is that a reader sees the formatting an author wrote. The Artefactenkaart showed
   `**Why:**` where the author meant **Why:**: of its 3 306 texts, 772 contain backticks, 163
   a double asterisk (160 a closed `**bold**` pair) and 63 a Markdown link (measured in its database on 2026-09-29).

2. Stef proposed `<MARKUP MARKDOWN>` on the text itself. Ampersand v5.9.7 does not allow that
   without a compiler change, measured on the test model: a box item takes a view name and no
   keys, and both a `BOX` and a `VIEW` on a text concept make it an `OBJECT`, which the type
   checker refuses next to its `REPRESENT` ("multiple representation types: OBJECT,
   BIGALPHANUMERIC"). The box therefore sits one level up, on the owner, where a box header
   carries keys, as `facets` does for FACETS (OK-16).

3. Rejected for now: a compiler change that allows a `VIEW` or `BOX` on a text concept, or keys on
   a box item. It gives the literal syntax, but puts a compiler release, a compiler image and a
   framework release between the change and any project, and the two routes here cover the need.

4. The dynamic format is an item of the box, named by `formatFrom`. Its expression is free, so the
   format can hang on the owner (`bodyFormat[Note*Format]`) or on the text itself
   (`body;textFormat` with `textFormat[Body*Format]`); a relation that starts at a text concept is
   accepted. Rejected: a reserved item label such as `format`, which would silently take a
   modeller's item of that name. Rejected: calling the key `format`, because its value names an
   item, not a format. StringTemplate cannot compare strings, so the component hides the format
   item at runtime, as FACETS hides its `facetOnly` columns.

5. The static format is a bare word in the header (`MARKDOWN`, `GFM`, `HTML`, `TEXT`): a name with
   a hyphen, such as `GITHUB-MARKDOWN`, is no single word for the parser and no attribute name for
   StringTemplate. The runtime accepts it as an alias of `GFM` in a format item, next to `MD`,
   `COMMONMARK`, `GITHUB_MARKDOWN`, `PLAIN` and `ASCII`. `EBCDIC` is an encoding, not a markup, and reStructuredText
   has no small renderer for the browser; both give plain text and one console warning.

6. Safety comes from Angular's sanitiser on `[innerHTML]`: it removes `<script>` and event
   handlers and makes a `javascript:` URL inert (`unsafe:javascript:`), measured in the spec.
   Rejected: `bypassSecurityTrustHtml`, which would let a text run code in its reader's browser.
   Rejected: DOMPurify as a second layer, one dependency more for what Angular already does.

7. `marked` 15 is the renderer: it has no dependencies of its own, and 15 is the last series that
   runs on Node 18, the Node of the framework images (16 and later require Node 20). The pure pipe
   `markup` formats a text again only when its text or format changes.

8. The box is for reading: the compiler hands a box template no CRUD per item, so every text in
   it is read-only, and editing stays in a FORM. The route per concept knows the CRUD of its field
   and shows the text area of BIGALPHANUMERIC when the field may be updated.

9. `showLabels` is off by default. A MARKUP box usually holds one text, whose label the enclosing
   FORM or TABLE already shows; FORM's opposite default, `hideLabels`, would make the common case
   the one that needs an annotation.

Impact op de specificatie: none for the model. An interface that shows formatted text gains a
`BOX <MARKUP ...>` on the owner of the text, or the project adds a `Concept-<name>.html`. A format
per text needs a relation to a format concept.

Impact in productie: a project picks MARKUP up with the next framework release and a rebuild.
The frontend bundle grows by `marked`, a package without dependencies of its own. A text that was shown with its markup
characters is shown formatted only where the modeller asks for it; nothing changes elsewhere.

Technisch: `frontend/src/app/shared/atomic-components/atomic-markup/` (component, `markup.ts`
with its unit tests, `MarkupPipe`), `frontend/src/app/generated/.templates/Box-MARKUP.html`,
`marked` in `frontend/package.json`, documentation in
`docs/reference-material/built-in-box-templates.md`, regression in `test/projects/markup`.
