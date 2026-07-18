# gibraltar-bb — a real FC5 population, imported both ways

**Guards:** a purpose-built context imports a real FC5 `_ast.yml` population flawlessly — at run time via the backend YAML importer (this project's spec) and at compile time via the Ampersand compiler's `INCLUDE` — issue #1673.

The concepts and relations in `model/main.adl` mirror the keys of a classifier output file,
`Gibraltar_Bloembollen_exporteisen_1.0_ast.yml` (country → certificate requirements, crop
requirements → requirement groups → requirements). The same Gibraltar data is expressed twice:

- `e2e/GibraltarBB.yml` — the runtime importer's atoms/links format (uploaded by the spec).
- `model/population.adl` — `POPULATION` statements, the compiler's native form.

Both must load the identical population. This is the concrete demonstration of the compile-time
vs run-time story from `Ampersand/docs/the-excel-importer.md`: the two importers accept different
serializations, but a context with exactly the right relations reads the same data either way.

## 1. Run time — backend importer (regression)

```bash
# from the repository root
test/run-regression.sh gibraltar-bb
```

The runner compiles `model/main.adl` (relations only, no population), installs an empty database,
then `e2e/import.mjs` uploads `GibraltarBB.yml` and asserts the resulting rows. Exit 0 = pass.

## 2. Compile time — Ampersand compiler via INCLUDE

```bash
cd test/projects/gibraltar-bb/model
ampersand check GibraltarBB.adl      # INCLUDEs main.adl (relations) + population.adl (data)
```

`GibraltarBB.adl` pulls in the relations and the population with `INCLUDE`, so compiling it runs
the compiler's (Haskell) population importer. "no type errors and no population errors" means every
pair was validated against the relations. `ampersand proto GibraltarBB.adl --proto-dir <dir>
--no-frontend` writes the loaded population to `<dir>/generics/populations.json`.

## What this shows about formats

The Ampersand compiler's `INCLUDE` reads `.adl` (`POPULATION`) and `.xlsx` — not YAML/JSON. The
runtime importer reads `.xls*`, and (issue #1673) `.json`/`.yaml`. So the YAML file itself is a
run-time format; the compile-time equivalent of the same data is `population.adl` (or an `.xlsx`).
A context with the right relations is required for either path — the importer rejects atoms/links
whose concept or relation is unknown.
