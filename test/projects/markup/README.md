# markup — regression vehicle

**Guards:** BOX<MARKUP> and app-atomic-markup: a text in MARKDOWN, GFM, HTML, LATEX or TEXT is shown
formatted; the format comes from the box header or, per row, from the item that `formatFrom`
names (a relation on the owner or on the text itself), and that item shows nothing; an unknown
format falls back to plain text with a console warning; HTML is sanitised; LATEX shows a formula as MathML, keeps an unknown macro readable and lets no HTML from the source through; `showLabels` shows
the labels; a `Concept-<name>.html` formats one concept in every interface; with update rights a
Markdown text is the WYSIWYG editor, which runs none of 30 attack texts, saves nothing when a field is
left without typing, and changes only the edited lines of a stored text, while LATEX and a field
with more texts keep the text area.

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
Angular's sanitiser, so three things in this project guard it (DesignChoices OK-35, overweging 6).

- The samples `x01` to `x30` in `model/main.adl` are attack texts, after the XSS cheat sheets of
  OWASP and PortSwigger: script and event handlers in raw HTML, `javascript:` and `data:` URLs in
  links, images, autolinks and reference links, entity-encoded and mixed-case schemes, attribute
  breakouts in a title, mutation XSS with `<math>` and `<noscript>`, and HTML in a GFM table. Each
  sets `window.__xss` when it runs. The spec opens them in the reading mode (`ReadSamples`) and in
  the editing mode (`EditSamples`), fires `mouseover`, `focus` and `toggle` on every element, and
  fails when the flag is set, or when an element outside a fixed list of tags, an `on…`
  attribute, or a URL with `javascript:`, `data:` or `vbscript:` is on the page.
- `check-editor-html.mjs` lists every place in the packages of the editor (Milkdown and the
  ProseMirror packages under it) where HTML and a string meet: `innerHTML`, `outerHTML`,
  `insertAdjacentHTML`, `createContextualFragment`, `parseFromString` and `document.write`. Each
  place that has been read stands in the script with the reason it is harmless. A place that is
  not there fails the check, for instance after an upgrade of Milkdown; read it, and add it with
  its reason or change the editor.
- The samples `r1` to `r9` are Markdown that the editor writes back in its own spelling: the
  regular expressions of the Artefactenkaart with `\.` and `$`, `\_` and `\$`, a formula with `\,`, raw LaTeX lines, lists
  with `-`, a table and one sentence per line. The spec enters and leaves every editor without
  typing and fails on any PATCH request or any stored text that changed.

**Can fail:** on 2026-09-30 the spec went red under two mutations in a copy: with
`bypassSecurityTrustHtml` in `MarkupPipe` ("HTML is sanitised" and "no script from the text
ran" failed, and the `onerror` of the test text ran), and with the format item always shown
("the format item shows nothing" and "the format per row" failed).
On 2026-11-20 it went red under two mutations of the editor.
The first drew raw HTML through `innerHTML`, with an `htmlSchema` whose `toDOM` sets `span.innerHTML`.
Then "no attack text ran in the editing mode" failed with the flag set by `x24`, and so did "the
editing mode shows no dangerous element or attribute" (`<script>`, `img[onerror]`, `<iframe>`, …)
and "raw HTML in a text is a node that shows its source".
The second let `safeUrl` return every URL unchanged.
Then both "shows no dangerous element or attribute" assertions failed, on `img[src=javascript:…]`
in the editor and on `data:` URLs in the reading mode, and so did "an image with a javascript: or
data: URL gets an empty src".
The full Jest suite runs with `CI=true npx ng test` in `frontend/`; a bare `npx jest` lacks the
Angular test set-up.
