# Ontbrekende BOX-annotaties

**Datum:** 15 mei 2026  
**Auteur:** Cline (AI-assistent)  
**Repository:** AmpersandTarski/prototype  

## Aanleiding

Uit codeanalyse blijkt dat de Ampersand documentatie een reeks annotaties beschrijft die de Ampersand compiler herkent, maar waarvan de implementatie in het PrototypeFramework (Angular frontend + StringTemplate-templates) ontbreekt. Dit document rapporteert het aanmaken van de bijbehorende GitHub feature-request issues.

## Probleemanalyse

### Wat er verloren is gegaan

De ontbrekende annotaties zijn **geen nieuwe features** — het zijn features die eerder werkten en verloren zijn gegaan bij de migratie van AngularJS naar Angular.

#### Tijdlijn

| Periode | Versie | Frontend | Status annotaties |
|---------|--------|----------|-------------------|
| t/m v1.0.0 | v1.0.0 | AngularJS (`app/`) | Geen annotaties; aparte templates per combinatie (zie hieronder) |
| t/m v1.17.0 | v1.17.0 | AngularJS (`app/`) + `templates/` | **Alle annotaties geïmplementeerd** in `templates/Box-*.html` |
| v1.18.0 | v1.18.0 | AngularJS + Angular (`frontend/`) naast elkaar | Angular-templates geschreven **zonder** annotaties |
| v2.0.0+ | v2.x | Angular (`frontend/`) alleen | Annotaties volledig afwezig |

**Oorzaak:** Op 9 juli 2023 (commit `cf87f380`, "Merge repo of new frontend into this repo") werd de nieuwe Angular-frontend geïntroduceerd. De Angular-templates in `frontend/src/app/generated/.templates/` werden van scratch geschreven en namen de annotatie-implementaties uit `templates/` **niet over**. Eerste release met de verloren annotaties: **v1.18.0**.

De laatste release met volledig werkende annotaties is **v1.17.0** (uitgebracht 24 mei 2023).

---

#### Vóór v1.17.0: afzonderlijke templates per combinatie

In de vroegste AngularJS-versies (bijv. v1.0.0) bestonden er geen annotaties. In plaats daarvan koos de scripter een apart template voor elke gewenste combinatie. De templatenaam *was* de annotatie:

| Template | Equivalent annotatie |
|----------|---------------------|
| `BOX<ROWS>` | standaard rijen met kolommen |
| `BOX<ROWSNH>` | `noHeader` (No Header) |
| `BOX<ROWSNL>` | geen labels — vergelijkbaar met `hideLabels` |
| `BOX<COLS>` | formulier-stijl (kolommen naast labels) |
| `BOX<COLSNL>` | formulier-stijl zonder labels |
| `BOX<TABS>` | tabbladen |
| `BOX<DIV>` | ruwe div-wrapper (vergelijkbaar met huidig RAW) |

Tussen v1.0.0 en v1.17.0 werden deze meerdere templates samengevoegd tot de vier huidige templates (TABLE, FORM, TABS, RAW) met annotaties als parameters.

---

#### Hoe de annotaties werkten in v1.17.0 (AngularJS)

De implementatie bestond uit StringTemplate4-condities die direct AngularJS-directives genereerden. De bronbestanden bevonden zich in `templates/Box-TABLE.html`, `templates/Box-FORM.html`, etc.

**`BOX<TABLE>` — templates/Box-TABLE.html (v1.17.0)**

| Annotatie | AngularJS-implementatie |
|-----------|------------------------|
| `noHeader` | `$if(!noHeader)$<thead>...</thead>$endif$` — volledige `<thead>` weggelaten |
| `hideOnNoRecords` | `ng-show="resource['$name$'].length"` op root `<div>` — CSS hide (DOM blijft) |
| `title` | `$if(title)$<h4>$title$</h4>$endif$` — vrije tekst boven de tabel |
| `noRootTitle` | `$if(!noRootTitle)$...$endif$` — onderdrukt automatische interfacetitel |
| `sortable` | `si-table` op `<table>` + `si-sortable` op `<tr>` + `sortable-col` class op `<th>` |
| `sortBy` + `order` | `ng-attr-sort-init="{{'$sortBy$' ? '$order$' : ''}}"` |
| `showNavMenu` | ❌ Niet aanwezig in Box-TABLE.html |

**`BOX<FORM>` — templates/Box-FORM.html (v1.17.0)**

| Annotatie | AngularJS-implementatie |
|-----------|------------------------|
| `hideOnNoRecords` | `ng-if="resource['$name$']"` — structurele verwijdering uit DOM (vs. `ng-show` bij TABLE) |
| `title` + `noRootTitle` | Identiek aan TABLE |
| `showNavMenu` | `$if(showNavMenu)$<my-nav-to-other-interfaces resource="resource">$endif$` |
| `hideSubOnNoRecords` | `ng-if="requireArray(resource['$subObj.subObjName$']).length"` per veld-`<div>` |
| `hideLabels` | `$if(hideLabels)$`...volledige breedte`$else$`...label+waarde-kolommen`$endif$` |

**`BOX<TABS>` — templates/Box-TABS.html (v1.17.0)**

| Annotatie | AngularJS-implementatie |
|-----------|------------------------|
| `hideOnNoRecords` | `ng-show="requireArray(resource['$name$']).length"` — CSS hide |
| `title` + `noRootTitle` | Identiek aan TABLE/FORM |
| `showNavMenu` | Identiek aan FORM |
| `hideSubOnNoRecords` | `ng-if="..."` op `<uib-tab>` — **probleemloze** implementatie (AngularUI Bootstrap ondersteunt `ng-if` op tabpanels, in tegenstelling tot PrimeNG) |

**`BOX<RAW>` — templates/Box-RAW.html (v1.17.0)**

| Annotatie | AngularJS-implementatie |
|-----------|------------------------|
| `table` | Volledige template-switch: met `table` → `<table><tr ng-repeat="..."><td>` per sub-expressie; zonder → `<div ng-repeat="...">` |
| `form` | ❌ Nooit geïmplementeerd — ook niet in de AngularJS-era |

---

#### Samenvatting: wat hersteld moet worden

Van de 17 issues in dit rapport zijn **15 herstelwerk** (annotaties die eerder werkten) en **2 nieuwe features** (nooit geïmplementeerd geweest):

| Categorie | Annotaties |
|-----------|-----------|
| **Herstelwerk** (werkten in v1.17.0) | `noHeader`, `hideOnNoRecords` (TABLE/FORM/TABS), `title` (TABLE/FORM/TABS), `noRootTitle` (TABLE/FORM/TABS), `showNavMenu` (FORM/TABS), `hideSubOnNoRecords` (FORM/TABS), `hideLabels` (FORM), `table` (RAW) |
| **Nieuw** (nooit geïmplementeerd) | `form` (RAW), `showNavMenu` (TABLE) |

---

## Aangemaakt overzicht

| # | Template | Annotatie | Issue-nr |
|---|----------|-----------|--------- |
| 1 | TABLE | `noHeader` | [#300](https://github.com/AmpersandTarski/prototype/issues/300) |
| 2 | TABLE | `hideOnNoRecords` | [#301](https://github.com/AmpersandTarski/prototype/issues/301) |
| 3 | TABLE | `title` | [#302](https://github.com/AmpersandTarski/prototype/issues/302) |
| 4 | TABLE | `noRootTitle` | [#303](https://github.com/AmpersandTarski/prototype/issues/303) |
| 5 | TABLE | `showNavMenu` | [#304](https://github.com/AmpersandTarski/prototype/issues/304) |
| 6 | FORM | `hideOnNoRecords` | [#305](https://github.com/AmpersandTarski/prototype/issues/305) |
| 7 | FORM | `hideSubOnNoRecords` | [#306](https://github.com/AmpersandTarski/prototype/issues/306) |
| 8 | FORM | `hideLabels` | [#307](https://github.com/AmpersandTarski/prototype/issues/307) |
| 9 | FORM | `title` | [#308](https://github.com/AmpersandTarski/prototype/issues/308) |
| 10 | FORM | `noRootTitle` | [#309](https://github.com/AmpersandTarski/prototype/issues/309) |
| 11 | FORM | `showNavMenu` | [#310](https://github.com/AmpersandTarski/prototype/issues/310) |
| 12 | TABS | `title` | [#311](https://github.com/AmpersandTarski/prototype/issues/311) |
| 13 | TABS | `noRootTitle` | [#312](https://github.com/AmpersandTarski/prototype/issues/312) |
| 14 | TABS | `hideOnNoRecords` | [#313](https://github.com/AmpersandTarski/prototype/issues/313) |
| 15 | TABS | `hideSubOnNoRecords` | [#314](https://github.com/AmpersandTarski/prototype/issues/314) |
| 16 | RAW | `form` | [#315](https://github.com/AmpersandTarski/prototype/issues/315) |
| 17 | RAW | `table` | [#316](https://github.com/AmpersandTarski/prototype/issues/316) |

---

## Aantekeningen per issue

### Issue 1 — TABLE / noHeader
- **Issue:** [#300](https://github.com/AmpersandTarski/prototype/issues/300)
- **Bijzonderheid:** De `+`-knop voor "create new" zit momenteel in de header-rij. Verwijdering van de header vereist een expliciete designbeslissing over de nieuwe locatie van die knop (footer-rij aanbevolen).
- **Interactie met `sortable`:** `noHeader` + `sortable` is technisch geen probleem, maar de gebruiker verliest de klikbare sorteerkolommen. Moet gedocumenteerd worden.
- **Compiler dependency bevestigd als cross-cutting concern:** Geldt voor alle 17 issues — de Ampersand compiler (Haskell) moet de annotaties als StringTemplate-variabelen doorgeven. Voor `noHeader` is dit nog niet bevestigd (wel aanwezig in JSON).

### Issue 2 — TABLE / hideOnNoRecords
- **Issue:** [#301](https://github.com/AmpersandTarski/prototype/issues/301)
- **Bijzonderheid:** Als het hele component verborgen is, zijn ook de add-controls verborgen. De gebruiker kan dan de *eerste* record niet meer toevoegen. Dit is een fundamenteel UX-dilemma dat vóór implementatie opgelost moet worden.
- **Loading-flash:** Bij initieel laden is de data-array even leeg. Component kan even onzichtbaar zijn vóór data arriveert — mogelijk flash of onverwacht gedrag.
- **Angular-technisch:** Twee `*ngIf` op hetzelfde element werken niet. `<ng-container>` wrapper is nodig.
- **Compiler status:** NIET bevestigd in `interfaces.json`.

### Issue 3 — TABLE / title
- **Issue:** [#302](https://github.com/AmpersandTarski/prototype/issues/302)
- **Bijzonderheid:** `title` heeft een stringwaarde (`title="..."`), niet een boolean vlag. De compiler moet de waarde als string-variabele doorgeven — dit is een fundamenteel ander type dan de booleans.
- **PrimeNG `caption` slot** is de meest semantisch correcte implementatieoptie.
- **Interactie met `noRootTitle`:** Beide annotaties zijn onafhankelijk — `title` is expliciet door de gebruiker, `noRootTitle` betreft de automatisch gegenereerde paginatitel.
- **Compiler status:** NIET bevestigd in `interfaces.json`.

### Issue 4 — TABLE / noRootTitle
- **Issue:** [#303](https://github.com/AmpersandTarski/prototype/issues/303)
- **Bijzonderheid:** Dit is een twee-staps feature. `noRootTitle` heeft pas effect als óók de auto-title feature geïmplementeerd is. Beide moeten samen worden opgepakt.
- **Architectuurkeuze:** Auto-title kan in de template leven (`$if(isRoot)$<h2>$label$</h2>$endif$`) of in het Angular layout-component. Die keuze bepaalt de volledige implementatiestrategie.
- **Cross-template concern:** `noRootTitle` is ook gedocumenteerd voor FORM en TABS. Eén gemeenschappelijke aanpak verdient de voorkeur.
- **Compiler status:** NIET bevestigd in `interfaces.json`.

### Issue 5 — TABLE / showNavMenu
- **Issue:** [#304](https://github.com/AmpersandTarski/prototype/issues/304)
- **Bijzonderheid:** Het zwaarste TABLE-issue qua scope. Raakt drie lagen tegelijk: template (attribuut toevoegen), Angular component .ts (input + data-request), én de component HTML (extra `<td>` met menu).
- **`_ifcs_` API-mechanisme:** Al aanwezig in de backend maar niet altijd aangevraagd door de frontend. `showNavMenu` moet de frontend aanzetten om dit te includeren.
- **Performance:** `_ifcs_` per rij ophalen kan een merkbare overhead geven bij grote tabellen.
- **Cross-template concern:** `showNavMenu` geldt ook voor FORM. Gedeelde implementatie is wenselijk.
- **Compiler status:** NIET bevestigd in `interfaces.json`.

### Issue 6 — FORM / hideOnNoRecords
- **Issue:** [#305](https://github.com/AmpersandTarski/prototype/issues/305)
- **Bijzonderheid:** FORM werkt anders dan TABLE: data is vaak een UNI-relatie (één object of `null`), niet een array. De null-check is anders dan de lengte-check bij TABLE.
- **Ergste risico:** Verbergen van de form blokkeert het invullen van de eerste waarde — ernstiger dan bij TABLE omdat het FORM zelf de primaire bewerkingsinterface is.
- **Compiler status:** NIET bevestigd in `interfaces.json`.

### Issue 7 — FORM / hideSubOnNoRecords
- **Issue:** [#306](https://github.com/AmpersandTarski/prototype/issues/306)
- **Bijzonderheid:** Opereert op rijniveau, niet op componentniveau. Vereist propagatie van een "verberg-als-leeg"-instructie door de component-hiërarchie via `BaseBoxComponent`.
- **Edit-mode probleem:** In bewerkingsmodus kan de gebruiker lege velden niet meer invullen als ze verborgen zijn.
- **Inzetbaar gecombineerd:** `hideOnNoRecords + hideSubOnNoRecords` is een krachtige combinatie voor compacte, data-gedreven formulieren.
- **Compiler status:** NIET bevestigd in `interfaces.json`.

### Issue 8 — FORM / hideLabels
- **Issue:** [#307](https://github.com/AmpersandTarski/prototype/issues/307)
- **Bijzonderheid:** CSS-aanpak (hostbinding + `.hide-labels`) is beter dan structurele `*ngIf` — geen DOM-wijziging, layout-correctie (full-width input) ook in CSS.
- **Accessibiliteitswaarschuwing** is essentieel en al opgenomen.

### Issue 9 — FORM / title
- **Issue:** [#308](https://github.com/AmpersandTarski/prototype/issues/308)
- **Bijzonderheid:** `<p-fieldset legend="$title$">` biedt visueel rijkere framing dan een losse `<h3>` — ontwerpers moeten dit kiezen.
- **Compiler:** string-waarde, identiek aan TABLE/title.

### Issue 10 — FORM / noRootTitle
- **Issue:** [#309](https://github.com/AmpersandTarski/prototype/issues/309)
- **Bijzonderheid:** Geblokkeerd op TABLE/noRootTitle (#303) design-keuze. Niet apart implementeren.

### Issue 11 — FORM / showNavMenu
- **Issue:** [#310](https://github.com/AmpersandTarski/prototype/issues/310)
- **Bijzonderheid:** FORM toont één object → één knop. TABLE toont lista → per-rij knop. Fundamenteel verschil in plaatsing. Geen toolbar-area in huidige FORM-template.

### Issue 12 — TABS / title
- **Issue:** [#311](https://github.com/AmpersandTarski/prototype/issues/311)
- **Bijzonderheid:** Verwijzing naar `docs/reference-material/images/box-tabs.gif` als visuele referentie.
- **Compiler:** string-waarde, identiek aan TABLE/title en FORM/title.

### Issue 13 — TABS / noRootTitle
- **Issue:** [#312](https://github.com/AmpersandTarski/prototype/issues/312)
- **Bijzonderheid:** Geblokkeerd op TABLE/noRootTitle (#303). Alle drie templates (TABLE, FORM, TABS) moeten dezelfde mechanisme gebruiken.

### Issue 14 — TABS / hideOnNoRecords
- **Issue:** [#313](https://github.com/AmpersandTarski/prototype/issues/313)
- **Bijzonderheid:** TABS heeft geen "add new item"-knop in het component zelf. Verbergen bij leeg blokkeert **niet** het aanmaken van records — veiliger dan TABLE en FORM.

### Issue 15 — TABS / hideSubOnNoRecords
- **Issue:** [#314](https://github.com/AmpersandTarski/prototype/issues/314)
- **Bijzonderheid:** PrimeNG-beperking: `*ngIf` op `<p-tabPanel>` kan index-problemen veroorzaken. `[hidden]` is veiliger maar ook niet gegarandeerd. Proof-of-concept vereist.
- **Asymmetrie met FORM/hideSubOnNoRecords:** TABS verbergt hele panelen (sub-interfaces), FORM verbergt veldregels (controls). Hogere UX-impact.

### Issue 16 — RAW / form
- **Issue:** [#315](https://github.com/AmpersandTarski/prototype/issues/315)
- **Bijzonderheid:** Native HTML `<form>` omzeilt Angular-interceptors → CSRF-risico. Alleen voor print/export use cases, niet voor normale SPA-formulieren.

### Issue 17 — RAW / table
- **Issue:** [#316](https://github.com/AmpersandTarski/prototype/issues/316)
- **Bijzonderheid:** Naamschingering: `table` annotatie op `BOX<RAW>` vs. template-naam `TABLE`. Sub-expressies moeten geldige `<tr>`-elementen produceren, anders is de HTML ongeldig.

---

## Geleerde lessen (cumulatief)

### Na issue 1 (TABLE / noHeader)
1. **Compiler dependency is een cross-cutting concern** voor alle 17 issues. Bij elk issue benoemen of de annotatie al in de compiler-JSON staat (bevestigd) of onbekend is.
2. **StringTemplate negatie:** `$if(!var)$` gebruiken als de feature een "suppress"-gedrag heeft (iets weglaten). Dit is correcte StringTemplate4-syntax.
3. **Interactie-analyse loons:** Altijd nadenken over welke bestaande UI-elementen wegvallen als een feature verwijderd wordt, en waar die naartoe moeten.
4. **`--body-file` werkt perfect** voor lange issue-bodies met markdown, code-blokken en speciale tekens.

### Na issue 2 (TABLE / hideOnNoRecords)
5. **"Verberg alles"-annotaties blokkeren add-controls:** Als een component volledig verborgen wordt bij lege data, kan ook de eerste record niet worden toegevoegd. Dit is een structureel UX-probleem dat bij alle "hide"-annotaties speelt.
6. **Loading-race:** Bij het verbergen op basis van data-lengte is er altijd een moment dat de data leeg is vóór API-response. Bij alle hide-features: benoem loading-state als risico.
7. **Angular dubbel `*ngIf`:** Is niet mogelijk op één element — altijd een `<ng-container>` wrapper gebruiken bij extra conditionals.

### Na issue 3 (TABLE / title)
8. **String-waardenannotaties vs. boolean-vlaggen:** `title="..."`, `sortBy="..."` en `order="..."` zijn string-annotaties — de compiler moet niet een boolean maar een string doorgeven. Bij het schrijven van issues: altijd specificeren wat voor type de waarde heeft en wat dit inhoudt voor de compiler.
9. **Bestaande screenshots in docs hergebruiken:** `docs/reference-material/images/title-example-pixels.png` bestond al — nuttig als visuele referentie zonder zelf screenshots te maken.

### Na issue 4 (TABLE / noRootTitle)
10. **"Suppression"-annotaties vereisen soms dat de feature die gesuppressed wordt, zelf ook eerst geïmplementeerd is.** Bij deze categorie: altijd checklistitem "vereiste predecessor-feature" opnemen, en aangeven of die al geïmplementeerd is.
11. **Cross-template features** (noRootTitle geldt voor TABLE, FORM en TABS) verwijzen naar het belang van een gedeelde implementatiestrategie — bij elk zo'n issue uitdrukkelijk naar de andere templates verwijzen.

### Na issue 5 (TABLE / showNavMenu)
12. **Sommige annotaties raken meerdere architectuurlagen tegelijk** (template + component + API). Bij dit soort issues: altijd alle drie de lagen benoemen en hun onderlinge afhankelijkheid beschrijven.
13. **`_ifcs_` is een bestaand backend-mechanisme** dat nog niet door de huidige frontend-templates gebruikt wordt. Bij implementaties die hiervan afhangen: vermeld dit expliciet als reeds beschikbare infrastructuur.

---

## Samenvatting

Alle 17 GitHub-issues zijn aangemaakt in `AmpersandTarski/prototype` als feature-requests, genummerd #300–#316. Elk issue bevat:
- Een concrete use-case met een script-voorbeeld
- Een ASCII-mockup van huidig vs. gewenst gedrag (of HTML-mockup voor RAW-issues)
- Een gebruikersinstructie die direct in de handleiding overgenomen kan worden
- Technische implementatiedetails per issue
- Risico's en open vragen

### Overzicht per template

| Template | Aantal issues | Nummers |
|----------|--------------|---------|
| TABLE | 5 | #300–#304 |
| FORM | 6 | #305–#310 |
| TABS | 4 | #311–#314 |
| RAW | 2 | #315–#316 |
| **Totaal** | **17** | **#300–#316** |

### Cross-cutting bevindingen

1. **Compiler dependency is een cross-cutting concern:** Geen van de 17 annotaties is bevestigd aanwezig als variabele in de huidige `backend/generics/interfaces.json`. De Ampersand-compiler (Haskell) moet annotaties als StringTemplate4-variabelen exporteren voor elk issue om te werken.

2. **Twee-staps features:** `noRootTitle` (voor TABLE, FORM en TABS) vereist eerst een implementatie van de auto-titel-feature. Deze drie issues (#303, #309, #312) zijn geblokkeerd op het ontwerp van auto-titels en moeten samen worden geïmplementeerd.

3. **String-waarden vs. booleans:** `title="..."` (TABLE, FORM, TABS) vereist dat de compiler een string exporteert, niet een boolean. Dit is structureel anders dan de boolean annotaties.

4. **"Verbergen van lege data"-risico:** Bij alle `hideOnNoRecords`-annotaties (TABLE, FORM, TABS) geldt: als het component verborgen is, zijn ook de bewerkingsinterfaces verborgen. Bij TABLE en FORM is dit het grootste risico; bij TABS is dit minder erg doordat TABS zelf geen add-controls heeft.

5. **`showNavMenu` vereist `_ifcs_` API-uitbreiding:** De `_ifcs_` veldaanvraag moet actief worden aangevraagd door het frontend. Dit is een feature die twee templates (TABLE #304, FORM #310) deelt en een gemeenschappelijke servislaag verdient.

6. **PrimeNG-limieten voor TABS/hideSubOnNoRecords:** Het conditioneel inclusief/exclusief weergeven van `<p-tabPanel>` binnen `<p-tabView>` via `*ngIf` is problematisch in PrimeNG. Issue #314 vereist proof-of-concept vóór implementatie.

7. **RAW-features zijn alleen voor experts:** `BOX<RAW form>` (#315) en `BOX<RAW table>` (#316) zijn escape hatches voor directe HTML-controle en zijn niet bedoeld voor regulier gebruik. CSRF-risico bij `form` moet achter een expliciete documentatiewaarschuwing staan.
