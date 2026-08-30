# delta-conjunct-maintenance

**Guards:** that `transactions.deltaConjunctMaintenance` (Ampersand#1684) changes no observable
behavior: the booking scenario of `skip-clean-conjuncts` produces an identical digest under
`off`, `shadow` and `on`, and — with the bundled compiler, which emits no `deltaQueries` — the
non-off modes route every conjunct to full evaluation ("0 delta-maintained" in the close's
summary line, no shadow mismatch).

The project reuses the model of `skip-clean-conjuncts` (`regression.conf` points its entry
there) and the shared scenario in `test/shared/conjunct-parity.mjs`; the spec adds the three-mode
phase logic and the log-based proof that the delta path ran but had nothing to maintain.

Run it with:

```bash
test/run-regression.sh delta-conjunct-maintenance
```

**Follow-up:** once a compiler that emits `deltaQueries` is bundled, extend the spec so that
the summary line reports delta-maintained conjuncts and the shadow run logs "identical"
checks — that becomes the guard of the delta protocol itself (see Ampersand#1684).

The spec temporarily replaces `backend/config/project.yaml` and `backend/config/logging.php`
(DEBUG to a bind-mounted file) and restores both afterwards.
