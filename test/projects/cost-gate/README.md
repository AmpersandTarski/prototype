# cost-gate

**Guards:** the cost gate that routes each conjunct at the close of a transaction
(Ampersand issue #1692): with the gate off nothing changes, with it on a conjunct
classified `structural` runs no query while its neighbours still do, and — the part that
matters most — the sampled self-check catches a classification that is wrong, so an
unsound skip cannot silently break a rule.

The spec supplies the cost profiles itself, by rewriting `backend/generics/conjuncts.json`
after the model is compiled. That is on purpose. The bundled compiler emits profiles since
v5.9.8, but a spec that took them as they come could not put a conjunct on a chosen route,
and it could not hand the gate a wrong profile. What this repository owns is the
framework's *response* to a contract, and that is exactly what the spec pins.

Five scenarios run against the same model and the same mutations:

1. **Gate off** — the baseline. Its commit decisions, data and violation cache are what the
   other runs are compared against.
2. **Gate on, honest profiles** — the `UNI` conjuncts of relations stored on the key column
   are marked `structural`. Commit decisions, data and violation cache must be identical to
   the baseline.
3. **Gate on, a deliberately wrong profile** — the conjunct of `NoSelfFollow`, which no
   table layout enforces, is marked `structural` too. With the self-check off, a booking
   that follows itself is committed: that is what an unsound claim costs. With the
   self-check at 100%, the same mutation is refused and the framework logs the discrepancy.

4. **Gate on with the delta protocol, every other conjunct on the integral route** — the
   setting `transactions.deltaConjunctMaintenance` is `'on'`, and the profiles keep every
   conjunct that is not structural integral. No conjunct may go through the protocol, although
   the bundled compiler gave them candidate queries: under `'on'` the gate decides.
5. **Gate on with the delta protocol, every other conjunct on the incremental route** — the
   same, with the class `recursive`, which the gate always routes to incremental maintenance.
   Now the protocol must maintain at least one conjunct.

In scenarios 4 and 5 the commit decisions, the data and the violation cache must again be
identical to the baseline. The spec reads the number of conjuncts the protocol maintained from
the close's summary line, which the framework logs at `DEBUG`; for these two runs it replaces
`backend/config/logging.php` by one that logs to a file, and waits until the backend uses it.

Scenario 3 also carries the evidence that scenario 2 needs. Parity between gate off and
gate on would prove nothing if the gate had quietly done the same work either way, and the
skip is logged at `DEBUG`, which the framework's `FingersCrossedHandler` buffers away
unless an error occurs. So the spec shows the skip by its effect instead: a violating state
can only slip past an invariant if that invariant's query was never run.

Scenario 3 is the reason the skip route has its own switch and its own safety net. Claim
PRF-8 in the Ampersand proof register is `stated`, not proved; the self-check is what keeps
a defect in it visible.

The spec restores `backend/config/project.yaml`, `backend/config/logging.php` and the generated
`conjuncts.json` when it finishes, including on failure.
