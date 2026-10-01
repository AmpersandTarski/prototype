# box-facets — regression vehicle

**Guards:** BOX<FACETS>: the facet panel next to a TABLE. The kind of facet follows the
item's technical type (read from interfaces.json and concepts.json at runtime), an OBJECT
item with a sub-box gives child facets, PASSWORD is never a facet, counts follow the
other facets, the selection round-trips through the URL, `facets`/`facetOnly` choose and
hide, `facetKind` chooses the kind, a FACETS box nested in a FORM finds its own box, and
TABLE annotations still reach the table. For every TABLE: a created row appears once, a
sort the user chose survives a change in the number of rows, and a sorted table raises no
NG0100 in a development build. `compact` starts a table in the dense stand (one line per row, a
header that stays), and the switch in the header lets the user change it, remembered per table.

**Origin:** DesignChoices OK-15 (types and recursion), OK-16 (`facets`, `facetOnly`),
OK-17 (the table keeps all rows) and OK-18 (`facetKind`). The request came from a user who follows a large
Ampersand application, a map of project artefacts, and wanted to narrow its overviews
down instead of reading them whole.

**Run:** `test/run-regression.sh box-facets`, or by hand: `./generate.sh box-facets` and
open `http://localhost/tickets`.

**Green means:** every assertion in `e2e/test.mjs` passes. The spec builds the frontend
itself and drives it with Puppeteer.

The model (`model/main.adl`) has one box item per TType (ALPHANUMERIC, BIGALPHANUMERIC,
HUGEALPHANUMERIC, DATE, DATETIME, FLOAT, INTEGER, BOOLEAN, PASSWORD, OBJECT), a property
relation `[PROP]`, a multi-valued item, an identity item with LINKTO, and an OBJECT item
with a sub-box. Four interfaces use FACETS: `Tickets` (every item a facet, sorted
table), `TicketsByTeam` (`facets` and `facetOnly`) and `TeamDetail` (FACETS nested in a
FORM), and `TicketsReported` shows `facetKind` (dates kept as text, a number as a range).
`NewTickets`, a plain TABLE with Create rights, guards that a created row appears once,
and every sorted table guards that a development build raises no NG0100 error. `Teams`
(a TABLE) links to `TeamDetail`, and `TicketDetail` is the LINKTO target.
Create, delete and edit under a selection are guarded by the unit tests in
`frontend/src/app/shared/box-components/box-facets/box-facets.component.spec.ts`, because
every FACETS box in this model has read rights only. The population is synthetic.
