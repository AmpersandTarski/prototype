# skip-clean-conjuncts

**Guards:** that `transactions.skipCleanConjuncts: true` (issue #443) changes no observable
behavior: one API scenario — a committing edit, an ExecEngine repair, an invariant rollback,
a create, and the accompanying signals — produces an identical digest with the setting off
and on, while the container log proves the skip fires only in the on-run.

The model pairs an invariant (`ConfirmedNeedsName`), an ExecEngine rule (`ShowAllBookings`)
and a signal rule (`BookingNeedsName`) on one `Booking` concept, so the transaction close
touches every conjunct kind the setting can skip.

Run it with:

```bash
test/run-regression.sh skip-clean-conjuncts
```

The spec (`e2e/parity.mjs`) temporarily replaces `backend/config/project.yaml` (to flip the
setting) and `backend/config/logging.php` (DEBUG to stdout, to observe the
"Skip evaluation of conjunct" debug line) and restores both afterwards.
