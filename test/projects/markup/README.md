# markup — regression vehicle

**Guards:** BOX<MARKUP> and app-atomic-markup: a text in MARKDOWN, GFM, HTML, LATEX or TEXT is shown
formatted; the format comes from the box header or, per row, from the item that `formatFrom`
names (a relation on the owner or on the text itself), and that item shows nothing; an unknown
format falls back to plain text with a console warning; HTML is sanitised; LATEX shows a formula as MathML, keeps an unknown macro readable and lets no HTML from the source through; `showLabels` shows
the labels; a `Concept-<name>.html` formats one concept in every interface; with update rights a
Markdown text in a field with one text is the WYSIWYG editor, which runs none of 34 attack texts in
either mode, saves nothing when a field is left without typing, and keeps the stored spelling of
untouched lines, while LATEX and a field with more texts keep the text area.

**Origin:** DesignChoices OK-24. The Artefactenkaart, an Ampersand application that keeps track
of the artefacts of a set of projects, showed its Markdown texts with the markup characters in
them (AmpersandTarski/Ampersand#1700). The Markdown editor comes from DesignChoices OK-35 and
AmpersandTarski/prototype#538: the Artefactenkaart wanted its texts edited in their formatted form.

**Run:** `test/run-regression.sh markup`. The runner runs both scripts in `e2e/`: `test.mjs`, the
browser spec, and `check-editor-html.mjs`, which needs only `npm install` in `frontend/` and also
runs on its own (`node test/projects/markup/e2e/check-editor-html.mjs`). By hand: copy the three
templates of `e2e/templates/` into `frontend/src/app/generated/.templates/`, `./generate.sh markup`
and open `http://localhost/dynamicnotes` or `http://localhost/editsamples`. The spec types into the
samples `e1` and `e2`, so a second run against the same database needs a fresh install first.

**Green means:** every assertion in `e2e/test.mjs` passes, and `check-editor-html.mjs` finds no
place that has not been reviewed. The spec builds the frontend with the concept templates in it and
removes them again afterwards.

**The safety of the Markdown editor.** The editor draws its part of the page itself, outside
Angular's sanitiser, so this project guards it in two ways (DesignChoices OK-35, overweging 6).

- The samples `x01` to `x34` in `model/main.adl` are attack texts, after the XSS cheat sheets of
  OWASP and PortSwigger: script and event handlers in raw HTML, `javascript:` and `data:` URLs in
  links, images, autolinks and reference links, schemes in mixed case or hidden in a character
  reference (`&#106;`, `&colon;`), attribute breakouts in a title, mutation XSS with `<math>` and
  `<noscript>`, HTML in a GFM table, and an image and a link into `api/v1/admin` (issue #548).
  Each sets `window.__xss` when it runs; the two into `api/v1/admin` point to a route that only
  reads.
  The spec opens them in the reading mode (`ReadSamples`) and in the editing mode (`EditSamples`),
  and fires `mouseover`, `mouseenter`, `focus`, `toggle` and `start` on every element.
  It fails when the flag is set, when the page requests `api/v1/admin`, or when an element outside
  a fixed list of tags, an `on…` attribute, or a URL with another scheme than `http`, `https`,
  `mailto` and `tel` (and Angular's inert `unsafe:`) is on the page.
- `check-editor-html.mjs` lists every place where HTML or code meets a string (`innerHTML`,
  `outerHTML`, `insertAdjacentHTML`, `createContextualFragment`, `parseFromString`,
  `document.write`, `srcdoc`, `eval`, `new Function` and an event handler set as an attribute), in
  the packages that the editor imports and every package under them, as `frontend/package-lock.json`
  names them: 110 packages on 2026-11-20, nested copies included. Each place that has been read
  stands in the script with the reason it is harmless. A place that is not there fails the check,
  for instance after an upgrade of Milkdown, until someone has read it and added it with its reason,
  or changed the editor.

**The save of the Markdown editor.** The samples `r1` to `r9` are Markdown that the editor writes
back in its own spelling (DesignChoices OK-35, overwegingen 2 and 3): the regular expressions of the
Artefactenkaart with `\.` and `$`, `\_` and `\$`, a formula with `\,`, raw LaTeX lines, lists with
`-`, a table and one sentence per line. The spec enters and leaves every editor without typing and
fails on any PATCH request or any stored text that changed. It types in `e1` and asserts that the
untouched paragraph keeps its backslash, and types bold and a list in `e2`.

**Can fail:** on 2026-09-30 the spec went red under two mutations in a copy: with
`bypassSecurityTrustHtml` in `MarkupPipe` ("HTML is sanitised" and "no script from the text
ran" failed, and the `onerror` of the test text ran), and with the format item always shown
("the format item shows nothing" and "the format per row" failed).
On 2026-11-20 it went red under five mutations of the editor.

- Raw HTML drawn through `innerHTML`, with an `htmlSchema` whose `toDOM` sets `span.innerHTML`:
  "no attack text ran in the editing mode" failed with the flag set by `x24`, and so did "the
  editing mode shows no dangerous element or attribute" (`<script>`, `img[onerror]`, `<iframe>`, …)
  and "raw HTML in a text is a node that shows its source".
- `safeUrl` returning every URL unchanged: both "shows no dangerous element or attribute"
  assertions failed, on `img[src=javascript:…]` in the editor and on `data:` URLs in the reading
  mode, and so did "an image with a javascript: or data: URL gets an empty src".
- `safeUrl` without its checks on character references and administration routes: both "shows no
  dangerous element or attribute" assertions failed (`data:` from `x31`, `api/v1/admin` from `x33`
  and `x34`), and so did "no text made the page call an administration route".
- The editor storing its own Markdown, without the merge: "the edit is stored, and the untouched
  paragraph keeps its backslash" failed (`-toets.` without its backslash).
- A save also without a change: "leaving an editor without typing sends no PATCH" failed with 43
  requests. The last two mutations ran in one run, as they touch different assertions.

The unit tests of `markdown-merge.ts` failed under two mutations as well: with `mergeEdit`
returning the editor's Markdown, 9 of 13 tests failed, and with `textToStore` storing the merge
also when it changes the meaning, 2 of 13. The full Jest suite runs with `CI=true npx ng test` in `frontend/`; a bare `npx jest`
lacks the Angular test set-up.
