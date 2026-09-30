# privacy-and-cookies — regression vehicle

**Guards:** that a generated prototype stores exactly what `docs/reference-material/cookies-and-browser-storage.md` lists (cookies and session storage, in the code and after a browser visit), and that `frontend.privacyStatementUrl` puts a link to the privacy statement in the footer.

**Origin:** AmpersandTarski/Ampersand#1697. An organisation that deploys a prototype must tell its users what the prototype stores (article 13 GDPR). The reference page is what its privacy officer copies into the privacy statement, so it has to stay true.

**Run:** `test/run-regression.sh privacy-and-cookies`. The static check also runs on its own, without a stack: `node test/projects/privacy-and-cookies/e2e/inventory.mjs`.

**Green means:** both specs pass.

- `e2e/inventory.mjs` scans the frontend and backend sources for every call that writes a cookie or browser storage, and compares the keys with the table "In the browser" on the reference page, in both directions.
- `e2e/test.mjs` builds the frontend, opens it in a headless browser, and checks that the cookies and storage items the visit leaves behind are all on the reference page. It checks the footer twice: without the setting it shows no link, and with the setting written to `backend/config/project.yaml` it links to the URL. The spec restores `project.yaml` afterwards.

The model has one FORM interface on `SESSION`, enough for the menu and the footer to render.
