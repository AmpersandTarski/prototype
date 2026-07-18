# import — runtime population import (JSON / YAML / auto-detect)

**Guards:** runtime population import reads JSON and YAML with identical semantics, and detects the format from file content (extension is advisory) — issue #1673.

A minimal model (persons, organizations, `worksFor`) with two `UNI,TOT` naming relations, so an
import that violates totality would fail to commit. The e2e spec uploads the same population twice —
once as JSON, once as YAML — into a freshly installed database and asserts the resulting population is
byte-for-byte identical. It also uploads the JSON content named `.txt` and the YAML content with no
extension to prove auto-detection makes the extension advisory.

## Why this exists

The runtime importer accepts two textual formats. YAML is transcoded to JSON and handed to the same
`JsonPopulationImporter`, so neither format can accept what the other rejects. This project is the
regression that keeps that promise: if the two paths ever diverge, the JSON-vs-YAML dump comparison
fails.

## Run

```bash
# from the repository root
test/run-regression.sh import
```

The runner brings up an isolated stack, compiles `model/main.adl`, installs the application and runs
`e2e/equivalence.mjs`. Exit code 0 = pass.

## Fixtures

- `e2e/population.json` — canonical JSON population.
- `e2e/population.yaml` — the same population in YAML, using comments, unquoted values and block lists
  (features JSON lacks) to exercise a genuine YAML document.
