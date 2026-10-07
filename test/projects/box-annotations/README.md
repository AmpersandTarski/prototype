# box-annotations — regression vehicle

**Guards:** the BOX-template annotations: sortByAndHide sorts the rows on a column that
is not rendered (backend delivers `_sortValues_` without `sortable`; the generated
component drops the column's cells), `sortable` keeps delivering sort values, and a box
without a sort annotation stays free of them.

**Origin:** the annotation-restoration line (issues #303–#312) and the sortByAndHide
annotation (v2.8.0).

**Run:** `test/run-regression.sh box-annotations`

**Green means:** every assertion in `e2e/test.mjs` passes — the API delivers sort values
exactly for the annotated boxes, and the frontend generation renders the sort binding
plus the *ngIf that drops the hidden column.

The model (`model/box-annotations.adl`) exercises the annotations one interface each:
`noHeader`, `hideOnNoRecords`, `hideSubOnNoRecords`, `hideLabels`, `title`,
`showNavMenu` (TABLE/FORM/TABS), `table`/`form` (RAW), `sortable` and `sortByAndHide`
(TABLE). The interfaces beyond the sort annotations are covered by opening them in a
browser after `./generate.sh box-annotations`; the spec covers the sort annotations
mechanically.
