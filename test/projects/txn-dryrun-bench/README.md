# txn-dryrun-bench — measurement vehicle and regression

**Guards:** that restricting a dry-run round to the conjuncts the newest edit affects
(`transactions.editScopedDryRun`, DesignChoices OK-10) leaves the worklist and the commit
decision unchanged, and measures what it saves.

**Origin:** AmpersandTarski/prototype#445.

## What the model is for

A record with twenty independently editable fields, so a buffer of twenty edits touches
twenty different relations and every round has exactly one relation the previous round did
not see. Each field has its own rule ("the value must be allowed"), which is what puts
entries on the worklist. One further rule, `Reach`, is a self-join over the relation `near`
whose cost grows with the population the driver imports; it mentions `f1`, so the first edit
of a buffer pulls it into the affected set and every later edit finds it there without
touching it. That is the conjunct a carried verdict spares, and it is why this model can
show a difference at all.

## Run

    test/run-regression.sh txn-dryrun-bench

or, against a stack of your own:

    PROTOTYPE_URL=http://localhost:9400 node test/projects/txn-dryrun-bench/e2e/bench.mjs

`BENCH_SIZES` (group size, default `20,40,60`), `BENCH_GROUPS` (default `4`) and
`BENCH_REPEATS` (default `3`) set the population sizes and the number of repeats. The
population of `near` holds `groups × size²` pairs and its self-join costs `groups × size³`
intermediate rows, so the group size is the knob that makes the expensive rule expensive.

## The two edit streams

Where an edit's value comes from decides how much the restriction can leave out, so the
driver runs both cases.

In the **reuse** stream every edit writes a value that already exists, as a dropdown, a
checkbox or a retyped value does. No atom is created, only the edited relation is affected,
and the round evaluates 2 of 41 affected conjuncts.

In the **fresh** stream every edit writes a value that does not exist yet. That creates an
atom, `Concept::addAtom` marks its concept affected
(`backend/src/Ampersand/Core/Concept.php:581`), and the compiler maps a concept to the
conjuncts of every relation it is an endpoint of (Ampersand,
`src/Ampersand/FSpec/ToFSpec/ADL2FSpec.hs:229`). The round then evaluates 46 of 46: the
restriction is still correct, and there is nothing left for it to leave out. A sharper
affectedness belongs in the compiler; the framework receives the coarse list.

**Green means:** for every population size and every buffer length, the worklist is the same
round for round with the setting on and off; the reuse stream evaluates fewer conjuncts with
the setting on; and a clean buffer commits while a violating buffer does not, with the
setting on and off alike. The latency table is reported, never asserted: a wall-clock number
is evidence, not a gate.
