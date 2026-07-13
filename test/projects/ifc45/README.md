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
