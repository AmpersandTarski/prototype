---
title: Importing large datasets
id: importing-large-datasets
---

# Importing large datasets

This guide answers two questions for importing data into a running prototype:
**which route fits the shape of your data**, and **how to keep a large import
cheap** (linear, not quadratic, in the number of records).

The primitive both rely on is already in the framework; a turnkey bulk-import
endpoint that wraps the pattern below is planned (see *What is still manual*).

## 1. Pick the route that matches your data

The importer interprets your data through the Ampersand script — the interface
maps each label to a relation, so a document whose labels match the interface is
read as population. Which importer does this depends on the data's shape:

| Your data | Route | How |
| --- | --- | --- |
| **Nested document per record** (a YAML/JSON tree, one file/object per record) | Resource-API, interface-as-schema | `POST /api/v1/resource/SESSION/1/<Interface>` with the nested JSON body. `Resource::put()` walks the interface and creates the atoms/links. Convert YAML→JSON first (a faithful reserialisation). |
| **Tabular** (rows and columns) | Excel importer, INTERFACE or RELATION approach | Upload an `.xlsx` to `POST /api/v1/admin/import`, or `INCLUDE` it at compile time. A sheet named after an interface uses the interface approach; other sheets use relation blocks. |
| **Already `atoms`/`links`** (a population dump) | JSON/YAML population importer | `POST /api/v1/admin/import` of a `.json`/`.yaml` file with `atoms`/`links` keys. This is the flat dump format, not interface-as-schema. |

For the nested route, build the model so that **interface labels equal the data
labels**, root the import interface at `SESSION` (`"_SESSION";V[SESSION*Root]`)
with full CRUD, and represent long values as `BIGALPHANUMERIC` (the default OBJECT
atom id is capped at 254 characters). A document label that the interface does not
define is silently skipped with a warning — keep the interface schema-complete.

## 2. Keep it cheap — the bulk-load pattern

**Why a naive import is O(n²).** Every transaction's `close()` re-evaluates the
affected invariants with a query over the *whole* current population. Importing n
records one transaction at a time makes record k pay a pass over ~k rows, so the
total is quadratic. On a few hundred records this is unnoticeable; on tens of
thousands it dominates.

**The pattern.** Load record by record, but check the invariants only once:

1. POST each record with **`?defer=true`**. The record commits durably *without*
   evaluating invariants, so each commit is cheap and memory stays bounded (one
   transaction per record).
2. When all records are loaded, validate the whole result once:
   **`GET /api/v1/admin/ruleengine/evaluate/all`**. It re-evaluates every conjunct
   and returns the invariant (and signal) violations.

This turns the import from O(n²) into O(n). It is the runtime counterpart of how a
compile-time import validates the whole population once, after loading it.

**The trade-off — atomicity is per load, not per record.** With `?defer=true` the
data is committed before the final validation runs, so a violated invariant is
*reported by the validation pass*, not rolled back. Use bulk mode for a controlled
import where you inspect the validation report afterwards; use the normal
(non-deferred) path when each record must be all-or-nothing.

## 3. Example — a nested corpus into a SESSION interface

```bash
BASE=http://localhost/api/v1
# 1. start from an empty, installed database
curl -s "$BASE/admin/installer?ignoreInvariantRules=true" >/dev/null

# 2. load every record cheaply (defer the invariant check)
for f in data/*.json; do
  curl -s -X POST "$BASE/resource/SESSION/1/Documents?defer=true" \
       -H 'Content-Type: application/json' --data-binary @"$f" >/dev/null
done

# 3. validate the whole result once; read the violations from the response
curl -s "$BASE/admin/ruleengine/evaluate/all"
```

A record that is a nested object maps straight onto the interface: a labelled
scalar becomes a link to a leaf atom, a labelled sub-object becomes a new
(`_NEW`) atom plus a link, and a list becomes one link per element.

## 4. Checklist

- Interface rooted at `SESSION`, full CRUD, **labels == data labels**.
- Long free-text values → `REPRESENT … TYPE BIGALPHANUMERIC`.
- Bulk load with `?defer=true`; **validate once** with `evaluate/all` and read the report.
- Confirm the result independently (e.g. export the population and compare) — a
  silently skipped label loses data without an error.

## What is still manual

Today you assemble the pattern yourself (the loop plus the final validation). A
turnkey endpoint that accepts a stream of records, defers per record and validates
once — returning a single structured report — is planned as the convenience layer
over this primitive.

## References

- Design decision: `DesignChoices.md` OK-07 (bulk-load mode, and why the
  single-giant-transaction alternatives were rejected).
- Root-cause epic — incremental invariant maintenance (DBSP), so the full check
  becomes cheap without deferring: AmpersandTarski/Ampersand#1675.
- Building the model for the nested route: the *ampersand-data-import* method
  (exhaustive schema scan → model + interface → structural verification).
