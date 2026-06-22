# Full-text search module

The search module lets a user search across **all stored data** of a running prototype from the
home screen, and open each matching atom in any interface that can display it. It is a fixed
framework feature (not generated), wired into the application's home screen.

This page is written for contributors who maintain or extend the search feature. It explains the
design, points to every file involved, and shows where to change behaviour. It builds on the
backend runtime described in [The Prototype Framework](./prototype-framework.md) (TTypes, concepts,
relations) and the Angular structure in
[Frontend component internals](./frontend-component-internals.md).

## User-facing behaviour

- A search box on the home screen searches every stored value as you type (debounced).
- Results are the **atoms** (entities) whose data contains the term, grouped by concept.
- For each result the user can open the atom in any interface that can display it; the available
  interfaces are presented as buttons.

## Design

### Searching is column-based and TType-aware

Every relation knows the table and the two columns (`srcCol`, `tgtCol`) in which it is
administrated, together with the concept — and thus the [TType](./prototype-framework.md) — of
each side. Iterating over **relations** therefore yields every stored column uniformly, regardless
of whether the Ampersand compiler stored a (univalent) relation in a concept's *broad* table or in
its own *binary* table. This is why the module iterates relations rather than reverse-engineering
the SQL schema.

A column is only queried when the search term is a **plausible value** for that column's TType
(requirement: search is TType-aware without telling or asking the user):

| TType | Searched when the term… |
| --- | --- |
| `ALPHANUMERIC`, `BIGALPHANUMERIC`, `HUGEALPHANUMERIC` | always |
| `INTEGER` | consists of digits only |
| `FLOAT` | is a number (digits, optional decimal separator) |
| `DATE`, `DATETIME` | looks like (part of) a date/time |
| `OBJECT`, `PASSWORD`, `BINARY*`, `BOOLEAN`, `TYPEOFONE` | never |

So the term `983` is searched in `INTEGER` columns **and** in alphanumeric columns; the term
`Solanum` only in alphanumeric columns. `OBJECT` atoms are never searched, because an object
identifier carries no meaningful content beyond its identity (only equality matters).

Matching is a case-insensitive `LIKE '%term%'` substring search. The term is escaped twice: LIKE
metacharacters (`% _ \`) are neutralised so the user cannot inject wildcards, and the result is
then escaped as a SQL string literal.

### A match belongs to the entity on the other side

A scalar value (a name, a code, a date) describes the **entity** it is attached to. When a value
column matches, the result is the `OBJECT` atom on the other side of the relation (the `Land`, the
`Product`, the `Eis`, …), not the scalar value itself. Relations with two `OBJECT` sides or two
scalar sides have no single entity to navigate to and are skipped.

### Results carry their interfaces

Each result atom is enriched with the interfaces in which it can be opened, via
`AmpersandApp::getInterfacesToReadConcept()` — the same mechanism that powers the `_ifcs_`
navigation elsewhere in the UI. Results are returned in the `ObjectBase` shape (`_id_`, `_label_`,
`_ifcs_`) so the frontend reuses the application's interface route map for navigation.

### Limits

To stay responsive on large datasets the search caps rows per column and the total number of
distinct result atoms; when the cap is hit the response sets `truncated: true` and the UI asks the
user to refine the term. See the constants at the top of `SearchController`.

## Implementation

| Part | Location |
| --- | --- |
| Backend endpoint | `backend/src/Ampersand/Controller/SearchController.php` |
| Route registration | `backend/bootstrap/api/search.php` (`GET /api/v1/search?q=<term>`) |
| Frontend feature | `frontend/src/app/search/` (`SearchComponent`, `SearchService`, `search.model.ts`) |
| Home-screen wiring | `SearchComponent` imported in `frontend/src/app/layout/app.layout.module.ts`, used in `home.component.html` |

The endpoint requires a normal session; the interfaces offered per result respect the session's
active roles (an atom with no accessible interface is shown but not navigable).

## Extending the search

Common changes and where to make them, all in `SearchController`:

- **Make a TType (un)searchable** — edit `ttypeMatchesTerm()`. It is the single place that maps a
  TType and the term to "search this column or not". Adding a case here is all that is needed; the
  column enumeration picks it up automatically.
- **Change which entity a match belongs to** — edit `scalarColumnToSearch()`. It classifies a
  relation's two sides into the searched (scalar) column and the owning (object) column. Today it
  skips relations with two object sides or two scalar sides.
- **Tune responsiveness or result size** — edit the `*_LIMIT` / `MAX_*` constants at the top of the
  class. They cap rows per column, total result atoms, and matched-field hints.
- **Change the matching** (e.g. word boundaries, prefix-only) — edit `buildLikePattern()` and
  `buildColumnQuery()`. Keep the two-layer escaping intact: LIKE metacharacters first, then the SQL
  string literal.
- **Change the result shape** — edit `newResult()` (one result atom) and the JSON assembled in
  `search()`. The frontend types live in `frontend/src/app/search/search.model.ts`; keep the
  `ObjectBase` fields (`_id_`, `_label_`, `_ifcs_`) so navigation keeps working.
- **Change the presentation** — the result list, grouping and interface buttons are in
  `frontend/src/app/search/search.component.{ts,html,scss}`. Navigation reuses the generated
  interface route map (`INTERFACE_ROUTE_MAPPING_TOKEN`); do not hard-code routes.

A change to the backend `src/` or `bootstrap/` must be rebuilt into the base image before a
generated prototype sees it; see [Updating and Releasing the Prototype Framework](../guides/updating-and-releasing.md).
