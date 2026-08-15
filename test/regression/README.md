# Build regression cases

Each subdirectory here is a **build case**: a minimal `script.adl` that the `Dockerfile` in
this directory compiles into the base image and builds an Angular frontend from. The CI job
`regression-tests` (`.github/workflows/continuous-integration.yml`) builds every case on
every pull request.

**Guards:** that a model construct still compiles and that the generated frontend still
builds. It does not observe runtime behaviour — a case that compiles and builds is green,
however the prototype behaves in a browser.

## Adding a build case

```
test/regression/<case>/script.adl
```

That is the whole contract: a directory with a `script.adl` in it. The CI loop skips (and
reports) directories without one, so a case that is not a build case cannot silently turn
the pipeline red.

Run one case locally:

```bash
docker build --tag ampersandtarski/prototype-framework:local .   # from the repo root
cd test/regression && docker buildx build --build-context test=<case>/ .
```

## Runtime tests belong with their project

Tests that observe a **running** prototype (API calls, browser assertions) do not belong
here: they need a stack, not a build. They live with the model they exercise:

```
test/projects/<project>/e2e/
```

for example `test/projects/navmenu-grouping/e2e/test.mjs`. See
[issue #402](https://github.com/AmpersandTarski/prototype/issues/402) for the runtime
regression pipeline that ties these together.
