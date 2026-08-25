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
