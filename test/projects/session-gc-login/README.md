# session-gc-login — regression vehicle

**Guards:** that login and logout keep working while the framework garbage collects expired
sessions. It exercises the paths where session atoms are created and deleted: the SIAM
login/logout mechanism (ExecEngine `InsPair sessionAccount` / `DelAtom SESSION`), the
framework's own session renewal (`AmpersandApp::resetSession`, which commits the old atom's
deletion separately), and both under concurrent requests.

**Origin:** v2.5.2. The session garbage collector calls `resetSession`, which login also
uses (session renewal per OWASP), so the changed transaction boundaries had to be shown not
to break logging in or out.

**Run:** `test/session-gc/login-test.sh all` — see `test/session-gc/README.md` for the setup
(the model needs `session.loginEnabled: true` and a short `session.expirationTime`).

**Green means:** all phases PASS: login through the Login interface, logout (the session
deletes itself), framework login through `/admin/test/login` (session id renewed, old atom
gone), garbage collection of an expired logged-in session, and a concurrent mix of login,
new and returning sessions.

## Model

A minimal SIAM-style model: `sessionIsAnon`/`sessionIsUser` properties, U/PW login and the
`DelAtom SESSION` logout rule, copied from `SIAM_Basics.adl` and `SIAM_LoginWithUPW.adl`
without navigation and password hashing (both orthogonal to session/transaction behaviour).
`Password` is `ALPHANUMERIC` here on purpose: hashing is not what this vehicle guards.
