# navmenu-grouping

Regression vehicle for [issue #406](https://github.com/AmpersandTarski/prototype/issues/406):
default navigation-menu grouping by interface expression type.

The model mixes the two kinds of SESSION interfaces:

- **task screens** (expression ⊆ `I[SESSION]`): `SearchRequirements`, `UpcomingRequirements`
- **lists** (expression ⊆ `V[SESSION*Concept]`): `Countries`, `Organisms`, `ProductForms`, `Requirements`, `Suppliers`

Expected menu population, written by `Installer::reinstallNavigationMenus()`:

| setting `frontend.menuGrouping` | menu |
| --- | --- |
| `none` (default) | flat: all seven items directly under `MainMenu` |
| `byType` | task screens under `MainMenu`; one group item `_MainMenu_lists` (label from `frontend.menuGroupingLabel`, default "Lists") holding the five list interfaces |

Reinstalling must be idempotent: running the installer twice yields the same
nav menu population.

## Run

With the dev stack up (`docker compose up -d` in the repo root):

```bash
node test/regression/issue-406/test.mjs
```

The script compiles this model into the backend, exercises the installer with
both settings via the API, and asserts the `app/navbar` response.
