# delta-conjunct-maintenance

**Guards:** that `transactions.deltaConjunctMaintenance` (DesignChoices OK-19) changes no observable behavior under `off`, `shadow` and `on`, also together with `skipCleanConjuncts`, that the delta protocol fires under `shadow` and `on`, and that any other value stops the application at boot.

The spec runs the booking scenario of `skip-clean-conjuncts` four times:
under `off`, `shadow`, `on`, and `on` with `transactions.skipCleanConjuncts`.
All four runs must produce the same digest.
The bundled compiler emits `deltaQueries` since v5.9.8,
so in every non-off run the spec requires that conjuncts go through the delta protocol:
it counts the debug line of each delta-maintained conjunct and the number in the close's summary line.
Under `shadow` it also requires the "identical" lines of the shadow check, and in no run a shadow mismatch.
Under `off` no conjunct may go through the protocol.
With `skipCleanConjuncts` the summary line must also count conjuncts skipped as clean.
A last phase sets the value to a YAML `false` and waits for the boot check's own message in the debug log.

The project reuses the model of `skip-clean-conjuncts` (`regression.conf` points its entry
there) and the shared scenario in `test/shared/conjunct-parity.mjs`.

Run it with:

```bash
test/run-regression.sh delta-conjunct-maintenance
```

In the booking scenario 14 conjunct evaluations go through the protocol per run (measured on 5 October 2026 with compiler v5.9.8).
The spec requires more than zero, so that a change in the model does not break it.
The compiler side is described in
[Ampersand#1684](https://github.com/AmpersandTarski/Ampersand/issues/1684).

The spec temporarily replaces `backend/config/project.yaml` and `backend/config/logging.php`
(DEBUG to a bind-mounted file) and restores both afterwards. It installs only after the backend
reads the new `project.yaml` completely: the macOS bind mount can serve Apache a stale or
half-written copy for a moment.
