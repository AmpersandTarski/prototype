# ifc45 — regression vehicle

**Guards:** framework behaviour at model scale: a large concept hierarchy
(hundreds of `CLASSIFY` statements from the IFC4.3 standard), deep
`LINKTO INTERFACE` navigation, and `BOX<TABS>` over many entity lists. Compiling
and browsing this model exercises typology handling, route generation and list
rendering well beyond the size of the other test projects.

**Origin:** BIM/IFC4.3 import prototype (2026); kept here because size-related
regressions (typology, navigation, rendering) do not show up in small models.

**Run:** `./generate.sh ifc45 app.adl` (containers up), then open
`http://localhost` and run the installer.

**Green means:** the model compiles and builds; the `IFC4.3 import` TABS
interface renders; LINKTO navigation from a list entry (e.g. IfcProject →
ImportIfcProject) opens the detail interface without console errors.

**Known red (#415):** the installer fails on `CREATE TABLE "IfcRoot"`: MySQL's row-size
limit of 65535 bytes cannot hold 183 `VARCHAR(255)` columns (utf8mb4: 1022 bytes each).
Of those columns 116 are concept identities (one per concept in the `IfcRoot` typology)
and 21 reference other atoms — keys that must stay `VARCHAR` — so neither a `REPRESENT
... BIGALPHANUMERIC` (TEXT columns do not count) nor `innodb_strict_mode=0` (tested:
the 65535 limit is a server-wide check on the column definitions) brings the table under
the limit. The layout of broad tables is the compiler's; until it changes,
`regression.conf` declares this failure as known so the suite still fails on a new red.
