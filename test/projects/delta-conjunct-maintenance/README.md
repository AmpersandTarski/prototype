# delta-conjunct-maintenance

**Guards:** that `transactions.deltaConjunctMaintenance` (DesignChoices OK-19) changes no observable behavior under `off`, `shadow` and `on`, also together with `skipCleanConjuncts`, and that any other value stops the application at boot.

The spec runs the booking scenario of `skip-clean-conjuncts` four times: under `off`, `shadow`,
`on`, and `on` with `transactions.skipCleanConjuncts`. All four runs must produce the same digest.
The bundled compiler emits no `deltaQueries`, so the close's summary line must report
"0 delta-maintained" in every non-off run, with no shadow mismatch; with `skipCleanConjuncts` it
must also count conjuncts skipped as clean. A last phase sets the value to a YAML `false` and
waits for the boot check's own message in the debug log.

The project reuses the model of `skip-clean-conjuncts` (`regression.conf` points its entry
there) and the shared scenario in `test/shared/conjunct-parity.mjs`.

Run it with:

```bash
test/run-regression.sh delta-conjunct-maintenance
```

Once a compiler that emits `deltaQueries` is bundled, the spec needs a run in which the summary
line reports delta-maintained conjuncts and the shadow run logs "identical" checks; that run
guards the delta protocol itself. The compiler side is tracked in
[Ampersand#1684](https://github.com/AmpersandTarski/Ampersand/issues/1684).

The spec temporarily replaces `backend/config/project.yaml` and `backend/config/logging.php`
(DEBUG to a bind-mounted file) and restores both afterwards. It installs only after the backend
reads the new `project.yaml` completely: the macOS bind mount can serve Apache a stale or
half-written copy for a moment.
