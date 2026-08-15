# import-multivalue — runtime multi-value spreadsheet import

Regression coverage for the runtime spreadsheet importer's **multi-value (multi-column)** support:
a header cell of the form `[Concept,]` (a concept name plus a delimiter, wrapped in brackets) lets a
single spreadsheet cell hold several atoms separated by that delimiter. This mirrors the Ampersand
compiler's compile-time importer (`INCLUDE "file.xlsx"`); see `Ampersand/src/Ampersand/Input/Xslx/XLSX.hs`.

The coverage is split in two, because the importer's logic is partly pure (host-testable) and partly
database-bound (needs a running prototype). The build-only `test/regression/*` discipline cannot test
runtime behaviour, hence this dedicated setup.

## 1. Standalone unit test (fast, no Docker / DB / compiler)

`test/unit/ExcelImporterMultiValueTest.php` exercises the real `ExcelImporter` methods via a small probe
subclass, building its inputs in-memory with the bundled PhpSpreadsheet. It covers the parse/split layer
and the block-start detection (including the case where a source-column `[Skill,]` sits directly under a
`[Skill]` block starter and must not be mistaken for a new block).

```bash
php test/unit/ExcelImporterMultiValueTest.php
```

Exit code 0 = all checks pass. This is the test to run on every change to `ExcelImporter`.

## 2. End-to-end test (needs the dev stack)

`e2e/run.sh` imports real `.xlsx` files into a running prototype and asserts the resulting population in
the database. It covers what the unit test cannot: the cartesian product of a source multi-value column,
flipped (`~`) relations, and the INTERFACE-approach `add()`.

```bash
# from the repository root
docker compose up -d                         # framework dev stack
./generate.sh import-multivalue main.adl     # compile + build this project into the prototype
test/projects/import-multivalue/e2e/run.sh   # import + assert; exit 0 on success
```

What it checks (see `model/main.adl` for the relations and interface):

| Approach  | Case                                   | Relation                |
| --------- | -------------------------------------- | ----------------------- |
| RELATION  | target multi-value `[Skill,]`          | `skills[Person*Skill]`  |
| RELATION  | source multi-value, cartesian product  | `related[Skill*Skill]`  |
| RELATION  | flipped `~` + multi-value              | `enrolled[Project*Person]` |
| INTERFACE | multi-value sub-interface column       | `skills` via `PersonSkills` |

### Notes / caveats

- `e2e/make-fixtures.php` regenerates the `.xlsx` files (run automatically by `run.sh`); the generated
  files are git-ignored.
- The INTERFACE approach is access-controlled. In this minimal model the meta-population does not load
  role atoms into the database, so `run.sh` provisions access for the test (it registers a role atom,
  assigns the `PersonSkills` interface to it, and activates that role before importing). This is test
  scaffolding, not a requirement of the importer itself.
- The dev database runs with `--lower-case-table-names=1`; table/column names in the assertions follow
  that schema.
