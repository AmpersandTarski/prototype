# transactional-demo — regression vehicle

**Guards:** the TRANSACTIONAL interface feature: buffered edits, SAVE/CANCEL bar
inside the accent border, dry-run validation (SAVE disabled on invariant
violation), and a TRANSACTIONAL interface inlined as a subinterface reference
bringing its bar and buffering along.

**Origin:** v2.4.5 (transaction controls in border), v2.4.7 (bar text without
resource label), v2.4.8 (bar for inlined interface references).

**Run:** `./generate.sh transactional-demo main.adl` (containers up), then open
`http://localhost` and run the installer.

**Green means:**
- `Bookings` (routed): accent border with a bar reading "Editing"; an edit turns
  it into "Unsaved changes" + Cancel; SAVE commits; confirming a booking without
  a guest name disables SAVE with the violation as tooltip.
- `Wrapper` (Direct interface referencing `INTERFACE Bookings`): border + bar sit
  on the inlined subtree, not on the wrapper; edits inside it are buffered (a
  reload without SAVE loses them); SAVE commits them.
