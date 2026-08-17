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

**A dry-run round carries the verdicts of the conjuncts it does not re-evaluate in its own response, and the client hands them back on the next round**
*OK-10 · geldig · 2026-08-17 · herkomst: prototype#445, Ampersand#1682 (heuristic H3), design-heuristics O10*

A dry-run round of a transactional interface evaluates only the conjuncts that the operations
added since the previous round affect, plus every affected conjunct for which it holds no earlier
verdict. For the remaining conjuncts the round uses the verdict of the previous round, which the
response of that round returned to the client and which the client sends back inside the request
envelope `{ops, carriedVerdicts, carriedOpCount}`. The server keeps nothing between rounds. A dry
run evaluates conjuncts of invariant rules only. The SAVE round is untouched: it sends the bare
operation array, evaluates every conjunct affected by the whole buffer with its full violation
query, and decides the commit on that. The setting `transactions.editScopedDryRun` (default
`true`) is the kill switch; with it `false`, or with a client that sends no envelope, a dry run
behaves exactly as before. How much the restriction leaves out depends on the edit. An edit that
only changes a link leaves the edited relation affected and everything else is carried; an edit
that creates or destroys an atom marks the atom's concept affected as well, and a concept reaches
every conjunct over a relation with that concept as an endpoint, so then the affected set and the
newly affected set coincide and nothing is carried.

*Overwegingen:*

1. The purpose is the gap the user feels between leaving a field and seeing the SAVE state
   settle. A transactional interface of *n* edits costs *n+1* rounds of full evaluation over an
   affected-conjunct set that grows with the buffer, while only the newest operation can change
   any verdict. Bounding the work to that operation is heuristic H3 — deduplicate before you
   incrementalize — applied to the dry-run cadence, and it needs no delta calculus.
2. The verdicts of the skipped conjuncts cannot come from `__conj_violation_cache__`. A dry run
   rolls back and never persists, so that table holds the violations of the last committed state,
   and a conjunct touched by operations 1…*k−1* but not by operation *k* differs from it. The
   round-*(k−1)* result is the one that must survive, so the question is only where it lives.
3. The client is the one place that already has the buffer, the round order and the lifetime of
   the transaction, and it needs no invalidation logic because it holds no authority. Whichever
   verdicts it returns feed the advisory alone: SAVE re-evaluates everything server-side and
   refuses to commit on a violation, so a forged or stale carried verdict costs a user a wrong
   worklist, never a wrong commit. That is what makes the client an acceptable home for state
   the server would otherwise have to own.
4. A cancelled or failed round leaves the carried state describing the last prefix that actually
   completed, and `carriedOpCount` names that prefix, so the next round simply has a longer
   segment of new operations. Correctness therefore survives an aborted round without any
   cleanup. Server-side state has no such property: a round whose response never reaches the
   client has already written its state.
5. Rejected — **a per-session, per-buffer map in the session or the cache pool** (direction B of
   the issue). It keeps the server authoritative, but it introduces state with a lifetime nobody
   owns: abandoned buffers, several tabs on one session, and an invalidation obligation when a
   second session commits, which needs a generation counter shared across processes. The
   authority it buys is authority over an advisory that the commit round overrules anyway.
6. Rejected — **the server keeps the buffer and the client sends only the new operation**
   (direction C). It removes the *n(n+1)/2* replayed pair writes as well, which is the larger
   prize, and it is the right shape for a later transaction-server. It also moves the transaction
   boundary from the browser into the server and needs buffer lifetime, recovery and eviction
   before the first edit works. That is a separate change of its own size, and it is not blocked
   by this one.
7. Rejected — **attribution only, keeping the full evaluation** (direction D). It delivers the
   worklist without touching evaluation cost. The worklist here does not depend on the carried
   verdicts at all, so choosing D would have meant leaving the measurable half undone for no
   saving in risk.
8. A conjunct that no operation in the buffer touches after the round that evaluated it keeps its
   verdict for the lifetime of the buffer, so a commit by a second session in that window is
   invisible to the worklist. The staleness is real, bounded to the advisory, and corrected at
   SAVE; the client bounds it in time as well by discarding carried state older than 30 seconds
   and taking a full round instead. Rejected as a mitigation: a commit-generation counter on the
   server, which would reintroduce exactly the shared state direction B was rejected for.
9. The entry on the worklist that the user's newest edit caused is the entry that was absent from
   the previous round's worklist. Rejected: marking entries server-side from the conjuncts the
   round newly evaluated. The server's answer names every rule the edit *could* have affected,
   including rules whose violation was already there, whereas the difference between two rounds
   names what actually appeared; and the difference also works with the feature switched off.
10. Rejected — **keeping SAVE clickable and letting the advisory catch up asynchronously**
    (intervention 1 of design-heuristics). It removes the felt wait outright, and it changes what
    a transactional interface promises the user: SAVE would become a request whose refusal
    arrives afterwards. That is a decision about the interface, not about evaluation cost, and it
    belongs to whoever owns the feature.
11. The size of the win is set by how coarsely the runtime knows what an edit affects, and that
    granularity is the compiler's. `Concept::getRelatedConjuncts()` returns the `affectedConjuncts`
    list of the generated `concepts.json` (`Concept.php:168-171`), which the compiler fills in
    `fSpecAllConjsPerConcept` (Ampersand, `src/Ampersand/FSpec/ToFSpec/ADL2FSpec.hs:229-237`) by
    registering, per modifiable subterm of a conjunct, both its source and its target concept plus
    their specialisations. A concept therefore reaches the conjuncts of every relation it is an
    endpoint of. Since writing a value that is not yet in the population creates an atom
    (`Core/Concept.php:581`), such an edit affects everything, and the bench measures exactly that:
    46 of 46 affected conjuncts evaluated, at every buffer length.
12. Rejected — **a counter-measure in the framework that treats a concept as affected only for the
    conjuncts that depend on its population** (`I[C]`, `V`, cardinalities), leaving the rest to the
    affectedness of the relation itself. The reasoning is sound, and the information it needs is
    absent from the generated model: the framework receives the coarse list and cannot sharpen it.
    A sharper list belongs in the compiler, where it carries a proof obligation because it decides
    which conjuncts a commit re-evaluates. This is recorded on the Ampersand side as heuristic H8
    and hypothesis O11.
13. Rejected — **restricting a dry run by relation-affectedness alone and ignoring
    concept-affectedness**, on the argument that a dry run is advisory and SAVE corrects it. It
    would deliver the saving on every edit, and it would let a conjunct of the `I[C]`/`V` shape
    report a verdict one edit out of date. The issue's acceptance asks for a worklist identical to
    what a full re-evaluation would have produced, so a knowingly late entry is not on offer here.

*Impact op de specificatie:* none. The Ampersand source of an application is unaffected: no
concept, relation, rule or interface changes, and `TRANSACTIONAL INTERFACE` keeps its meaning.
What changes is the cadence at which the generated application evaluates the rules the script
already declares.

*Impact in productie:* a running prototype answers a dry-run round with one extra response field
(`dryRunVerdicts`) and accepts one extra request shape, both confined to `dryRun=true`. The
commit path sends and receives exactly what it did before. Setting
`transactions.editScopedDryRun: false` in `project.yaml` restores the previous evaluation on the
dry-run path without a rebuild, which is how the "identical commit decision, feature on and off"
comparison is run.

**The transaction bar shows the buffered invariant violations as a worklist, not as error toasts**
*OK-11 · geldig · 2026-08-17 · herkomst: prototype#445*

While a transaction is open, the invariant violations of the buffered edits appear inside the
transaction bar as a list of things still to be done before the transaction can be completed,
under the heading "Before you can save", in the neutral register of guidance. Entries that were
absent from the previous round carry the marker "from your last edit". The response of a dry run
produces no toast at all: the logging interceptor passes over every request carrying
`dryRun=true`, as it already did for `admin/import`. Outside a transaction nothing changes — a
rule violation is still reported as the red error message it is.

*Overwegingen:*

1. The purpose is that a half-filled form reads as unfinished rather than as broken. The same red
   toast served both meanings, so a rule that is simply not satisfied *yet* arrived with the
   weight of a genuine failure, once per edit, on top of a form the user is still filling in.
2. The list lives in the transaction bar because that is where the action it guards sits. The
   previous form — a tooltip on the disabled SAVE button — required the user to discover that
   hovering a disabled button says something, and it vanished on the way to the field it was
   about.
3. Rejected: a toast of severity `info` or `warn` instead of `error`. It softens the colour and
   keeps everything else that made the toast wrong here — it stacks per edit, it covers the form,
   and it disappears on a timer while the obligation it describes does not.
4. Passing over `dryRun=true` in the interceptor also keeps the signal badge intact. A dry-run
   response carries an empty `signals` array, and the interceptor fed that array to
   `SignalService`, so every buffered edit cleared the badge count until the next committed
   request restored it.

*Impact op de specificatie:* none. The `VIOLATION` text of a rule is what the worklist shows, so
an application influences the worklist exactly where it already influenced the tooltip.

*Impact in productie:* the transaction bar of a transactional interface grows a list above its
buttons, and buffered edits no longer raise toasts. Applications that are not transactional see
no change, because no dry run happens in them.
