# Rapport: Atomic Object vs. Atomic Alphanumeric — 32 CRUD × UNI-variaties

**Doel:** Ga systematisch alle 32 combinaties langs (16 CRUD-combinaties × 2 voor UNI/niet-UNI) en beoordeel per combinatie of het gedragsverschil tussen `atomic-object` en `atomic-alphanumeric` logisch verklaarbaar is vanuit het type (object vs. alfanumeriek veld). Verschillen die *alleen* op andere gronden bestaan worden als **niet-logisch** aangemerkt.

**Criterium voor logisch:** Een verschil is логisch als het rechtstreeks volgt uit het feit dat een object een identiteit (`_id_`) en label heeft waarnaar verwezen wordt, terwijl een alfanumeriek veld een scalaire waarde *is*. Alle andere oorzaken (bijv. technische keuzes, incomplete implementatie, signal-architectuur) zijn **niet-logisch** vanuit gebruikersperspectief.

---

## Legenda

| Symbool | Betekenis |
|---|---|
| ✅ Logisch | Verschil is volledig verklaarbaar vanuit object vs. alfanumeriek |
| ⚠️ Deels logisch | Gedeeltelijk verklaarbaar, maar bevat ook niet-logische elementen |
| ❌ Niet-logisch | Verschil is niet verklaarbaar vanuit het type |
| `=` | Gedrag is gelijk; geen verschil |

CRUD-notatie: `C` = Create, `R` = Read, `U` = Update, `D` = Delete. Hoofdletter = toegestaan, kleine letter = niet toegestaan.

---

## UNI = true (univalente relatie, maximaal één waarde)

### crud = `cRud` (alleen lezen)

| Aspect | Alphanumeric | Object | Oordeel |
|---|---|---|---|
| **Weergave** | `<span>` met tekst | `<span>` met `_label_` + `<app-ifcs-dropdown>` | ✅ Logisch: een object kan sub-interfaces hebben, een string niet |
| **Lege toestand** | Niets (geen speciale tekst) | `getUniEmptyText()` → "No \<type\> selected" | ⚠️ Deels logisch: object geeft explicieter feedback, maar beide zouden dit kunnen |
| **Interactie** | Geen | Geen | = |

---

### crud = `Crud` (alleen aanmaken, lezen uitgeschakeld)

| Aspect | Alphanumeric | Object | Oordeel |
|---|---|---|---|
| **Weergave** | "Alphanumeric is not readable" | "Object is not readable" | = (zelfde patroon) |
| **Functionaliteit** | Niets | Niets | = |

> Opmerking: `cRu + UNI` is door de base class als semantisch ongeldig gemarkeerd en wordt omgezet naar `cRud`. Aanmaken zonder update is bij UNI zinloos.

---

### crud = `CRud` (aanmaken + lezen)

| Aspect | Alphanumeric | Object | Oordeel |
|---|---|---|---|
| **Weergave** | Valt in `!(canUpdate() && isUni)` → **lijst-template** met C-add-input | Valt in `isUni && canRead() && !canUpdate()` → **uniRead** template | ❌ Niet-logisch: alphanumeric toont add-veld via de lijst, object toont read-only view. Beide zouden consistent moeten zijn in wat C-zonder-U bij UNI betekent. `CRu` UNI is semantisch ongeldig (base class zet het om naar `cRu`) — maar alphanumeric doet dit niet consistent. |
| **Add-input** | Zichtbaar (`canCreate()` in list-template) | Niet zichtbaar (uniRead heeft geen C-knop) | ❌ Niet-logisch (zie boven) |

---

### crud = `CRUd` (aanmaken + lezen + wijzigen)

| Aspect | Alphanumeric | Object | Oordeel |
|---|---|---|---|
| **Input-type** | `<input type="text">` met `<datalist>` (browser-native autocomplete) | `<p-dropdown editable>` (PrimeNG) | ✅ Logisch: een object wordt gekozen *uit een set bestaande objecten* — dropdown is passend. Een alfanumerieke waarde wordt vrij ingetypt — tekstveld is passend. |
| **Opties ophalen** | Via `interfacesLoader` → `fetchDropdownMenuData` (als opties beschikbaar) | Via `interfacesLoader` → `fetchDropdownMenuData` óf `selectOptions` | = (zelfde mechanisme) |
| **Validatie bij !canCreate()** | Afwijzen als waarde niet in optielijst staat (terugsetten naar oude waarde) | Dropdown laat alleen bestaande opties selecteren (visueel beperkt) | ✅ Logisch: beide beschermen tegen onbekende waarden, maar via verschillende UI-mechanismen passend bij het type |
| **"Create" knop naast dropdown** | Niet aanwezig | Aanwezig (`pi-plus`) voor direct nieuwe waarde aanmaken | ✅ Logisch: bij objecten is expliciet aanmaken zinvol; bij alfanumeriek is typen voldoende |
| **Optimistic update** | Nee — wacht op backend-respons | Nee (gewone `update()`) | = |
| **Sub-interface link** | Niet aanwezig | `<app-ifcs-dropdown>` naast het veld | ✅ Logisch: object heeft navigeerbare sub-interface, string niet |

---

### crud = `CRUd` met `editAsText=true` (alleen object)

| Aspect | Alphanumeric | Object (editAsText) | Oordeel |
|---|---|---|---|
| **Bestaat** | N.v.t. | Ja — rendert `<input>` i.p.v. `<p-dropdown>` | ✅ Logisch: dit is een speciale modus zodat een object-component zich gedraagt *als* een tekstcomponent wanneer dat gewenst is. Alphanumeric heeft dit al standaard. |

---

### crud = `cRUd` (lezen + wijzigen, niet aanmaken)

| Aspect | Alphanumeric | Object | Oordeel |
|---|---|---|---|
| **Input** | `<input>` met datalist, autocomplete actief als opties beschikbaar | `<p-dropdown>` editable, gefilterd op bestaande opties | ✅ Logisch (zelfde redenering als CRUd) |
| **Validatie** | `!canCreate()` + opties aanwezig → afwijzen als waarde niet in lijst | Dropdown beperkt keuze visueel; blur-handler reset bij onbekende invoer | ✅ Logisch: beide voorkomen het aanmaken van nieuwe entiteiten |
| **Minus-knop (remove)** | ~~Niet aanwezig~~ **Aanwezig** via gedeeld `#removeButton` template (`canUpdate() && !isTot`) | Aanwezig als `canUpdate() && matchTotConstraint()` | ✅ Opgelost |

---

### crud = `cRUD` (lezen + wijzigen + verwijderen)

| Aspect | Alphanumeric | Object | Oordeel |
|---|---|---|---|
| **Prullenbak (D)** | Aanwezig in uniEdit-template | Aanwezig in uniUpdate-template | = |
| **Minus-knop (U-remove)** | ~~Niet aanwezig~~ **Aanwezig** via `#removeButton` (`canUpdate() && !isTot`) | Aanwezig | ✅ Opgelost |
| **Tot-constraint op minus** | ~~Niet geïmplementeerd~~ **Geïmplementeerd** via `#removeButton` (`!isTot`) | `matchTotConstraint()` verbergt minus | ✅ Opgelost |
| **Tot-constraint op prullenbak** | ~~Niet geïmplementeerd~~ **Geïmplementeerd** via `#deleteButton` (`!(isTot && resource[propertyName])`) | `matchTotConstraint()` verbergt prullenbak als isTot=true en UNI-waarde aanwezig | ✅ Opgelost |

---

### crud = `CRUD` (volledig)

| Aspect | Alphanumeric | Object | Oordeel |
|---|---|---|---|
| **Input-type** | `<input>` + datalist | `<p-dropdown>` editable | ✅ Logisch |
| **Create-knop** | Niet los aanwezig | `pi-plus` aanwezig naast dropdown | ✅ Logisch |
| **Prullenbak** | Aanwezig | Aanwezig | = |
| **Minus-knop** | ~~Niet aanwezig~~ **Aanwezig** via `#removeButton` | Aanwezig | ✅ Opgelost |
| **Tot-constraint op minus** | ~~Niet geïmplementeerd~~ **Geïmplementeerd** (`!isTot`) | Geïmplementeerd | ✅ Opgelost |
| **Tot-constraint op prullenbak** | ~~Niet geïmplementeerd~~ **Geïmplementeerd** via `#deleteButton` (`!(isTot && resource[prop])`) | Geïmplementeerd | ✅ Opgelost |
| **Sub-interface** | Niet aanwezig | Aanwezig | ✅ Logisch |

---

## UNI = false (niet-univalente relatie, nul of meer waarden)

### crud = `cRud` (alleen lezen)

| Aspect | Alphanumeric | Object | Oordeel |
|---|---|---|---|
| **Weergave** | `<span>` per rij | Label + `<app-ifcs-dropdown>` per rij | ✅ Logisch: object heeft navigeerbare sub-interface |
| **Lege lijst** | Niets zichtbaar | Niets zichtbaar | = |

> **Bug (opgelost mei 2026):** In `cRud`-modus toonde `atomic-object` niets, ook als er wél atomen aanwezig waren. Oorzaak: de `#nonUni` template rendert via `*ngFor="let object of selection()"`, maar de `selection` signal werd alleen geïnitialiseerd binnen de reactieve keten die door de early return (`canUpdate() = false`) werd overgeslagen. Fix: `selection.set([...this.data])` aanroepen vóór de early return wanneer `!isUni`. `atomic-alphanumeric` had dit probleem nooit: de `#list` template leest direct van `*ngFor="let row of data"` (een getter, geen signal).

---

### crud = `cRud` — niet-leesbaar

| Aspect | Alphanumeric | Object | Oordeel |
|---|---|---|---|
| **Weergave** | "Alphanumeric is not readable" | "Object is not readable" | = |

---

### crud = `CRud` (aanmaken + lezen)

| Aspect | Alphanumeric | Object | Oordeel |
|---|---|---|---|
| **Add-input** | Aanwezig (`canCreate()`) — gewoon tekstveld + knop | Niet aanwezig: `canCreate()` zit in dropdown-filter, dropdown zelf vereist ook `canUpdate()` | ❌ Niet-logisch: object toont geen add-mogelijkheid bij C zonder U in niet-UNI. Alphanumeric wel. |

---

### crud = `CRUd` (aanmaken + lezen + wijzigen)

| Aspect | Alphanumeric | Object | Oordeel |
|---|---|---|---|
| **Weergave items** | Lijst van `<input>` velden (bewerkbaar inline) | Lijst van labels + dropdown om te selecteren | ✅ Logisch: alfanumeriek wordt inline bewerkt; object wordt geselecteerd uit set |
| **Wijzigen bestaande waarde** | Inline in het itemveld (`validateAndUpdate`) — verwijdert link naar oud atom, voegt link naar nieuwe atom toe | Via dropdown: remove + add via `add()` | ✅ Logisch: alfanumeriek kan direct worden getypt; object kiest uit bestaande entiteiten |
| **Minus-knop (remove)** | Aanwezig per rij | Aanwezig per rij | = |
| **Create nieuwe waarde** | Onderaan: tekstveld + plus-knop | In dropdown-filter: zoekbalk + plus-knop (alleen zichtbaar als `canCreate()`) | ✅ Logisch: passend bij het type |
| **Validatie bij !canCreate()** | Afwijzen als waarde niet in opties | Dropdown visueel beperkt | ✅ Logisch |
| **Tot-constraint op minus** | ~~Minus altijd zichtbaar~~ **Minus verborgen** als `isTot && data.length <= 1` via `#removeButton` | `matchTotConstraint()` verbergt minus-knop | ✅ Opgelost |
| **Tot-constraint op prullenbak** | ~~Prullenbak altijd zichtbaar~~ **Verborgen** als `isTot && data.length <= 1` via `#deleteButton` | `matchTotConstraint()` verbergt prullenbak als 1 item over | ✅ Opgelost |
| **Tot-constraint add-input** | `[required]` op add-input als `isTot && data.length === 0` | Dropdown heeft `[required]` via `isNewItemInputRequired()` | = |

---

### crud = `cRUd` (lezen + wijzigen, niet aanmaken)

| Aspect | Alphanumeric | Object | Oordeel |
|---|---|---|---|
| **Inline bewerken** | Ja, elk item is een `<input>` | Nee — items zijn labels; dropdown vervangt/voegt toe | ✅ Logisch: alfanumeriek kan inline bewerkt; object kiest uit bestaande objecten |
| **Minus-knop** | Aanwezig | Aanwezig | = |
| **Dropdown aanwezig** | Nee | Ja (`canUpdate()`) | ✅ Logisch: object vereist selectie |
| **Validatie** | Zelfde als CRUd maar zonder create | Zelfde maar zonder plus-knop | = |

---

### crud = `cRUD` (lezen + wijzigen + verwijderen)

| Aspect | Alphanumeric | Object | Oordeel |
|---|---|---|---|
| **Prullenbak per item** | Aanwezig | Aanwezig | = |
| **Minus-knop** | Aanwezig | Aanwezig | = |
| **Tot-constraint op minus** | ~~Minus altijd zichtbaar~~ **Minus verborgen** als `isTot && data.length <= 1` via `#removeButton` | `matchTotConstraint()` verbergt minus | ✅ Opgelost |
| **Tot-constraint op prullenbak** | ~~Prullenbak altijd zichtbaar~~ **Verborgen** als `isTot && data.length <= 1` via `#deleteButton` | `matchTotConstraint()` verbergt prullenbak als 1 item over | ✅ Opgelost |

---

### crud = `CRUD` (volledig)

| Aspect | Alphanumeric | Object | Oordeel |
|---|---|---|---|
| **Add-input / dropdown** | Tekstveld + knop | Dropdown met filtertemplate + plus-knop | ✅ Logisch |
| **Inline bewerken** | Ja | Nee | ✅ Logisch |
| **Prullenbak** | Aanwezig | Aanwezig | = |
| **Minus-knop** | Aanwezig | Aanwezig | = |
| **Tot-constraint op minus** | ~~Niet geïmplementeerd~~ **Geïmplementeerd** (`!isTot \|\| data.length > 1`) | Geïmplementeerd | ✅ Opgelost |
| **Tot-constraint op prullenbak** | ~~Niet geïmplementeerd~~ **Geïmplementeerd** (`!isTot \|\| data.length > 1`) via `#deleteButton` | Geïmplementeerd via `matchTotConstraint()` | ✅ Opgelost |
| **Sub-interface** | Niet aanwezig | Aanwezig | ✅ Logisch |

---

## Overige variaties (niet-leesbaar, R=0)

Voor alle 16 combinaties waarbij `R` afwezig is (`crud[1] = 'r'`), geldt:

| Aspect | Alphanumeric | Object | Oordeel |
|---|---|---|---|
| **Weergave** | "Alphanumeric is not readable" | "Object is not readable" | = |
| **Functionaliteit** | Geen | Geen (alle CRUD-bewerkingen vereisen `canRead()` impliciet) | = |

> Alle 16 niet-leesbare variants zijn equivalent. Er zijn geen logische of niet-logische verschillen.

---

## Samenvatting bevindingen

### ✅ Logisch verklaarde verschillen (verklaarbaar vanuit type)

1. **Sub-interface navigatie (`<app-ifcs-dropdown>`):** Objecten hebben navigeerbare sub-interfaces; strings niet. ✅
2. **Dropdown vs. tekstveld:** Objecten worden gekozen uit een set bestaande entiteiten (dropdown past ); alfanumeriek wordt vrij ingetypt (tekstveld past). ✅
3. **Inline bewerken vs. select-new:** Alfanumeriek wordt inline bewerkt per rij; een object wordt vervangen door selectie uit de dropdown. ✅
4. **Expliciete Create-knop bij object:** Bij objecten is een losse "+" knop logisch (je maakt een nieuwe entiteit aan); bij alfanumeriek volstaat typen. ✅
5. **`editAsText`-modus in object:** Maakt een object-component gedragen als tekstveld wanneer de context dat vraagt. ✅

### ❌ Niet-logisch verklaarde verschillen (niet verklaarbaar vanuit type)

| # | Beschrijving | Aanwezig in | Ontbreekt in |
|---|---|---|---|
| 1 | ~~**Minus-knop (remove) bij UNI+Update**~~ **✅ Opgelost** — minus-knop toegevoegd aan `#uniEdit` template (verborgen bij `isTot`, grijs als leeg) | Atomic Object | ~~Atomic Alphanumeric~~ |
| 2 | ~~**`matchTotConstraint()` check** op prullenbak/minus~~ **✅ Opgelost** — gedeeld `#removeButton` ng-template met `isTot`-logica voor zowel UNI (`!isTot`) als niet-UNI (`!isTot \|\| data.length > 1`) | Atomic Object | ~~Atomic Alphanumeric~~ |
| 3 | **`CRu + UNI` semantisch ongeldig** — base class corrigeert het, maar alphanumeric toont toch een add-input via de list-template | Atomic Alphanumeric (foutief) | Atomic Object (correct) |
| 4 | **`CRud` niet-UNI:** object toont geen add-mogelijkheid bij C zonder U | Atomic Alphanumeric (heeft add-input) | Atomic Object (geen add-mogelijkheid) |

### ⚠️ Deels logisch

| # | Beschrijving |
|---|---|
| 1 | **Lege UNI-tekst:** Object geeft "No \<type\> selected"; alphanumeric toont niets. Objecten profiteren van een duidelijke lege-staat door het concepts-type; maar alphanumeric zou dit ook kunnen bieden. |
| 2 | **Tot-constraint bij niet-UNI:** Beide handhaven isTot, maar via verschillende mechanismen (hide-knop vs. required-attribuut). |

---

## Opvallende conclusie

Van de oorspronkelijk vier niet-logische verschillen zijn er **drie opgelost**:

- ~~Geen minus-knop (ontkoppelen zonder verwijderen) bij UNI~~ → **Opgelost** via `#removeButton` ng-template
- ~~Geen tot-constraint bescherming op de minus-knop~~ → **Opgelost** via `#removeButton` ng-template
- ~~isTot-bescherming op de prullenbak ontbreekt~~ → **Opgelost** via `#deleteButton` ng-template (`!(isTot && resource[prop])` voor UNI, `!isTot || data.length > 1` voor niet-UNI)

**Één openstaand niet-logisch verschil:**

- **Inconsistente behandeling van `CRu+UNI`**: de base class zet dit om naar `cRu`, maar de alphanumeric list-template toont toch een add-input via `!(canUpdate() && isUni)` → de list-branch.

`atomic-alphanumeric` loopt nog slechts op één klein punt functioneel achter op `atomic-object` — los van het inherente type-verschil.
