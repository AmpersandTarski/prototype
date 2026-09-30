# markup — regression vehicle

**Guards:** BOX<MARKUP> and app-atomic-markup: a text in MARKDOWN, GFM, HTML or TEXT is shown
formatted; the format comes from the box header or, per row, from the item that `formatFrom`
names (a relation on the owner or on the text itself), and that item shows nothing; an unknown
format falls back to plain text with a console warning; HTML is sanitised; `showLabels` shows
the labels; a `Concept-<name>.html` formats one concept in every interface and keeps the text
area when the field may be updated.

**Origin:** DesignChoices OK-24. The Artefactenkaart, an Ampersand application that keeps track
of the artefacts of a set of projects, showed its Markdown texts with the markup characters in
them (AmpersandTarski/Ampersand#1700).

**Run:** `test/run-regression.sh markup`, or by hand: copy
`e2e/templates/Concept-Explanation.html` into `frontend/src/app/generated/.templates/`,
`./generate.sh markup` and open `http://localhost/dynamicnotes`.

**Green means:** every assertion in `e2e/test.mjs` passes. The spec builds the frontend with the
concept template in it and removes the template again afterwards.

**Can fail:** on 2026-09-30 the spec went red under two mutations in a copy: with
`bypassSecurityTrustHtml` in `MarkupPipe` ("HTML is sanitised" and "no script from the text
ran" failed, and the `onerror` of the test text ran), and with the format item always shown
("the format item shows nothing" and "the format per row" failed). The full Jest suite runs
with `CI=true npx ng test` in `frontend/`; a bare `npx jest` lacks the Angular test set-up.
