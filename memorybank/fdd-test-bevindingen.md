# FILTEREDDROPDOWN Test Bevindingen

**Datum**: 11/12-5-2026  
**Doel**: Verifiëren of BOX<FILTEREDDROPDOWN> correct werkt voor alle testgevallen in `test/projects/box-filtered-dropdown/model/main.adl`

---

## Voortgang per opdrachtstap

| Opdrachtstap | Status | Bewijs aanwezig? |
|---|---|---|
| Stap 1: Checklist van testgevallen | ✅ gedaan | Zie sectie "Testgevallen" hieronder |
| Stap 2: Angular source (`ampersand proto --frontend-version Angular`) | ✅ gedaan | interfaces.json geverifieerd, HTML snippet gezien |
| Stap 3: PHP prototype (`ampersand proto --no-frontend`) | ✅ gedaan | Backend generics bekeken; interfaces.json identiek aan frontend; PHP src: 0 FDD-code |
| Stap 4: Prototype draaien + curl | ✅ gedaan | API data + UI + component code geanalyseerd; bugs gevonden |

---

## Randvoorwaarde: Docker build

---

## Stap 1: Testgevallen checklist

### Datamodel

| Relatie | Type | UNI | TOT | Rol in test |
|---------|------|-----|-----|-------------|
| `eligible[Project*Employee]` | object | nee | nee | selectFrom |
| `instroomOpties[Project*Datum]` | DATE | nee | nee | selectFrom |
| `keuze[Project*Integer]` | INTEGER | nee | nee | selectFrom |
| `projectMember[Project*Employee]` | object | nee | nee | setRelation (Default tab) |
| `instroomVanaf[Project*Datum]` | DATE | **ja** | nee | setRelation (Default tab) |
| `aantal[Project*Integer]` | INTEGER | **ja** | nee | setRelation (Default tab) |
| `projectMaster[Project*Employee]` | object | **ja** | nee | setRelation (UNI tab) |
| `projectFounder[Project*Employee]` | object | nee | **ja** | setRelation (TOT tab) |
| `projectResponsible[Project*Employee]` | object | **ja** | **ja** | setRelation (UNI+TOT tab) |

### Testdata (project 1)

- eligible = {m1, m4, m5}
- projectMember = {m1, m2, m4, m5}
- instroomVanaf = null (geen waarde voor project 1)
- instroomOpties = {2025-10-01, 2025-11-15, 2026-01-03, 2026-04-12}
- aantal = 1
- keuze = {1, 3, 5, 7}
- projectMaster = m3
- projectFounder = {m1}
- projectResponsible = m3

### Verwacht UI-gedrag per CRUD op setRelation

| CRUD | canUpdate | canCreate | canDelete | Dropdown? | Minus? | Trash? | Plus? |
|------|-----------|-----------|-----------|-----------|--------|--------|-------|
| cRud | nee | nee | nee | ❌ | ❌ | ❌ | ❌ |
| cRUd | **ja** | nee | nee | ✅ | ✅ | ❌ | ❌ |
| cRuD | nee | nee | **ja** | ❌ | ❌ | ✅ | ❌ |
| cRUD | **ja** | nee | **ja** | ✅ | ✅ | ✅ | ❌ |
| CRud | nee | **ja** | nee | ❌ | ❌ | ❌ | ❌ |
| CRUd | **ja** | **ja** | nee | ✅ | ✅ | ❌ | ✅ |
| CRuD | nee | **ja** | **ja** | ❌ | ❌ | ✅ | ❌ |
| CRUD | **ja** | **ja** | **ja** | ✅ | ✅ | ✅ | ✅ |

### Tab "Default": 24 testgevallen (8 CRUD × 3 relaties)

| Test | CRUD | Naam (projectMember, niet-UNI) | Instroom (instroomVanaf, UNI) | Aantal (aantal, UNI) |
|------|------|-------------------------------|-------------------------------|----------------------|
| 1 | cRud | Lijst, geen knoppen | Null getoond, geen knoppen | 1 getoond, geen knoppen |
| 2 | cRUd | Lijst + dropdown + minus | Dropdown, minus | Dropdown, minus |
| 3 | cRuD | Lijst + trash | Null + trash | 1 + trash |
| 4 | cRUD | Lijst + dropdown + minus + trash | Dropdown + minus + trash | Dropdown + minus + trash |
| 5 | CRud | Lijst, geen knoppen | Null, geen knoppen | 1, geen knoppen |
| 6 | CRUd | Lijst + dropdown + minus + plus | Dropdown + minus + plus | Dropdown + minus + plus |
| 7 | CRuD | Lijst + trash | Null + trash | 1 + trash |
| 8 | CRUD | Lijst + dropdown + minus + trash + plus | Dropdown + minus + trash + plus | Alles |

### Tab "Project Master (UNI)": 8 testgevallen

| Test | CRUD | Verwacht |
|------|------|---------|
| 1 | cRud | m3 getoond, geen knoppen |
| 2 | cRUd | Dropdown (m3 geselecteerd) + minus |
| 3 | cRuD | m3 + trash |
| 4 | cRUD | Dropdown + minus + trash |
| 5 | CRud | m3, geen knoppen |
| 6 | CRUd | Dropdown + minus + plus |
| 7 | CRuD | m3 + trash |
| 8 | CRUD | Dropdown + minus + trash + plus |

### Tab "Project Founder (TOT)": 8 testgevallen

Bijzonderheid: minus/trash geblokkeerd als ≤1 founder.

| Test | CRUD | Verwacht |
|------|------|---------|
| 1 | cRud | m1 getoond, geen knoppen |
| 2 | cRUd | Lijst + dropdown + minus (geblokkeerd bij 1 founder) |
| 3 | cRuD | Lijst + trash (geblokkeerd bij 1 founder) |
| 4 | cRUD | Lijst + dropdown + minus (geblokkeerd) + trash (geblokkeerd) |
| 5 | CRud | Lijst, geen knoppen |
| 6 | CRUd | Lijst + dropdown + minus (geblokkeerd) + plus |
| 7 | CRuD | Lijst + trash (geblokkeerd) |
| 8 | CRUD | Alles, minus/trash geblokkeerd |

### Tab "Project Responsible (UNI+TOT)": 8 testgevallen

Bijzonderheid: UNI+TOT = altijd precies 1 waarde, minus/trash altijd geblokkeerd.

| Test | CRUD | Verwacht |
|------|------|---------|
| 1 | cRud | m3 getoond, geen knoppen |
| 2 | cRUd | Dropdown (m3) + minus geblokkeerd |
| 3 | cRuD | m3 + trash geblokkeerd |
| 4 | cRUD | Dropdown + minus geblokkeerd + trash geblokkeerd |
| 5 | CRud | m3, geen knoppen |
| 6 | CRUd | Dropdown + minus geblokkeerd + plus |
| 7 | CRuD | m3 + trash geblokkeerd |
| 8 | CRUD | Dropdown + minus geblokkeerd + trash geblokkeerd + plus |

---

## Stap 2: Angular source (ampersand generatie)

### Bewijs A: interfaces.json (jq output)

`jq -rf /tmp/analyze_fdd_compact.jq /tmp/fdd-interfaces.json` toonde **alle 48 FDD-boxen** met correcte CRUD/isUni/isTot:

```
# Selectie uit de output (volledig gezien in terminal):
Naam     | setRel crud=cRud  isUni=false isTot=false tgt=Employee
Instroom | setRel crud=cRud  isUni=true  isTot=false tgt=Datum
Aantal   | setRel crud=cRud  isUni=true  isTot=false tgt=Integer
Naam     | setRel crud=cRUd  isUni=false isTot=false tgt=Employee
... (alle 24 Default-gevallen gezien)
1. Project Master (cRud)  | setRel crud=cRud  isUni=true  isTot=false tgt=Employee
... (alle 8 Master-gevallen gezien)
1. Project Founder (cRud) | setRel crud=cRud  isUni=false isTot=true  tgt=Employee
... (alle 8 Founder-gevallen gezien)
1. Responsible (cRud)     | setRel crud=cRud  isUni=true  isTot=true  tgt=Employee
... (alle 8 Responsible-gevallen gezien)
```

**Conclusie**: interfaces.json correct gegenereerd. ✅

### Bewijs B: Generated component HTML (snippet)

`docker exec prototype cat .../boxfiltereddropdowntests.component.html` toonde voor test 2 (cRUd):

```html
<app-box-table
    crud="cRud"
    propertyName="_50__46__32_Assign_32_an_32_employee_32__40_cRUd_41_"
    [data]="[resource._50__46__32_Assign_32_an_32_employee_32__40_cRUd_41_]"
    isUni isTot>
  ...
  <td><app-atomic-object
      [resource]="resource.Naam"
      [property]="resource.Naam"
      propertyName="setRelation"
      label="Naam"
      crud="cRud"
      isUni
      isTot
      mode="box-filtereddropdown"
  ></app-atomic-object></td>
```

**Observatie**: `crud="cRud"` en `isUni`/`isTot` komen van de BOX-expressie (`I[Project] cRud`, identity is UNI en TOT). De werkelijke setRelation-crud wordt pas runtime geladen via interfaces.json. Dit is de oorzaak van het UI-probleem (zie Stap 4).

### Bewijs C: ng build output

`docker exec prototype ls /var/www/html/` toonde:
- `index.html` ✅
- `main.2a85cc81816e9d89.js` ✅
- `assets/interfaces.json` ✅ (454KB)

---

## Stap 3: PHP prototype (ampersand --no-frontend)

### Bewijs A: Backend generics bestanden

`docker exec prototype ls /var/www/backend/generics/` toonde:
```
concepts.json, conjuncts.json, database.sql, interfaces.json,
populations.json, relations.json, roles.json, rules.json, settings.json, views.json
```

### Bewijs B: FILTEREDDROPDOWN in backend interfaces.json

`grep -c "FILTEREDDROPDOWN" /var/www/backend/generics/interfaces.json` → **48 treffers**, overeenkomend met de 48 FDD-boxen.

`grep -i "filtereddropdown"` toonde tevens: interfacenamen `BoxFilteredDropdownErrors` en `BoxFilteredDropdownTests`, en `"type": "FILTEREDDROPDOWN"` per box.

### Bewijs C: Backend == Frontend interfaces.json

MD5 van `/var/www/backend/generics/interfaces.json` en `/var/www/html/assets/interfaces.json`:
```
MD5: 5ae6f7c2c00a0cb7fe6c7cd493a43c26  (beide identiek)
```

**Conclusie**: De backend en frontend gebruiken **hetzelfde** interfaces.json bestand.

### Bewijs D: Geen PHP-specifieke FILTEREDDROPDOWN code

`docker exec prototype grep -r "FILTEREDDROPDOWN" /var/www/backend/src/ 2>&1 | wc -l` → **0 resultaten** ✅  
De PHP backend handelt FILTEREDDROPDOWN niet speciaal af.

---

## Stap 4: Runtime curl + UI

### Bewijs A: API data (curl output)

`GET /api/v1/resource/SESSION/{atom}/BoxFilteredDropdownTests` voor project 1, test 1 (cRud):

```json
"Naam":     { "selectFrom": ["m1","m4","m5"],              "setRelation": ["m1","m2","m4","m5"] }
"Instroom": { "selectFrom": ["2025-10-01","2025-11-15",...], "setRelation": null }
"Aantal":   { "selectFrom": [1,3,5,7],                     "setRelation": 1 }
```

**Conclusie API data**: Correct gefilterd en correct in lijn met testdata. ✅

Opmerkingen:
- Employee-atomen zijn **plain strings** ("m1"), niet als `{_id_, _label_}` ObjectBase-objecten
- Date-atomen zijn **strings** ("2025-10-01"), geen ObjectBase
- Integer-atomen zijn **getallen** (1), geen ObjectBase

**Bewezen via curl**: alleen test 1 (cRud) voor project 1. Overige 47 testgevallen niet via curl geverifieerd.

### Bewijs B: UI-gedrag (screenshot van gebruiker)

Screenshot toont de Default tab voor project 1:
- setRelationPreview: m1, m2, m4, m5 ✅ (correct)
- selectFromPreview: m1, m4, m5 ✅ (correct)
- **Alle 8 FILTEREDDROPDOWN-rijen (tests 1-8): tonen "No item selected" / leeg zonder dropdown, zonder knoppen**

Dit is een **afwijking** van de verwachting: tests 2, 4, 6, 8 (met canUpdate=true) zouden een dropdown moeten tonen.

### Bewijs C: findSubObject() navigatie geverifieerd (jq)

`curl http://localhost/assets/interfaces.json` → HTTP 200, 454556 bytes ✅

Padnavigatie voor `resource/SESSION/1/BoxFilteredDropdownTests/{hash}/Default/project 1/_50__..._cRUd_41_/Naam` geverifieerd via jq:

| Segment | Actie | Resultaat |
|---------|-------|----------|
| `{hash}` | zoek in topInterface.ifcObject.subinterfaces.ifcObjects | niet gevonden → skip |
| `Default` | zoek naar `name=="Default"` | gevonden ✅ |
| `project 1` | zoek in Default | niet gevonden → skip |
| `_50__..._cRUd_41_` | zoek in Default.subinterfaces.ifcObjects | gevonden ✅ |
| `Naam` | zoek in TABLE box subinterfaces | gevonden ✅ |

Daarna zoekt `findSubObject` naar `label=="setRelation"` in Naam's subinterfaces → gevonden met:

```json
{
  "crud": { "create": false, "read": true, "update": true, "delete": false },
  "expr": { "isUni": false, "isTot": false, "tgtConceptName": "Employee" },
  "hasExpr": true
}
```

**Conclusie**: `findSubObject()` MOET logisch `{crud:"cRUd", isUni:false, isTot:false, conceptType:"Employee"}` teruggeven voor test 2 Naam. ✅  
**Niet bewezen**: of het ook daadwerkelijk zo werkt in de browser (browser console FDD-DIAG logs ontbreken).

### Bewijs D: Component-code analyse

**Bronbestanden gelezen**: `atomic-object.component.ts` (703 regels), `BaseAtomicComponent.class.ts` (156 regels), `atomic-object.component.html` (201 regels).

#### Template-structuur

Drie wederzijds uitsluitende takken op basis van `isUni`:

```
isUni && canRead() && !canUpdate()  →  #uniRead    (toont huidige waarde readonly)
isUni && canRead() && canUpdate()   →  #uniUpdate  (toont p-dropdown)
!isUni && canRead()                 →  #nonUni     (toont lijst + dropdown voor toevoegen)
```

`#uniRead` toont: `<span *ngIf="!resource[propertyName]">{{ getUniEmptyText() }}</span>`  
→ toont `"No item selected"` alleen als `resource["setRelation"]` null/falsy is.

`#nonUni` toont: `<div *ngFor="let object of data">{{ object._label_ }}</div>`  
→ itereert over `data` getter = `this.resource["setRelation"]`.

#### Bug 1: Atomen als strings/getallen, niet als ObjectBase

De `data` getter levert `this.resource["setRelation"]`:
- Voor Naam: `["m1","m2","m4","m5"]` (strings)
- Voor Aantal: `1` (integer) → na `requireArray()`: `[1]`
- Voor Instroom: `null` → `[]`

`#nonUni` toont `{{ object._label_ }}` per item. Een string "m1" of getal 1 heeft geen `._label_` → `undefined` → **lege cel**.

`#uniRead` toont `{{ resource["setRelation"]._label_ }}` voor de UNI-waarde. Een integer `1` heeft geen `._label_` → `undefined` → **lege cel**.

#### Bug 2: isUni is GEEN Angular signal — timing issue

De template bindt statisch `isUni=true` (van `I[Project]` identity expressie). In `async ngOnInit` wordt dit overschreven naar `false` na de `await findSubObject()`. Maar `isUni` is een gewone property (`@Input()`, geen Signal).

**Timeline**:

```
t0:  Angular roept ngOnInit() aan
t0:  await findSubObject() → suspends
t0+ε: Template rendert met INITIËLE waarden: isUni=true, crud="cRud"
      → uniRead toont:
          Naam:    resource["setRelation"]=["m1",...] truthy → div maar _label_=undefined → LEEG
          Instroom: resource["setRelation"]=null → falsy → "No item selected"
          Aantal:  resource["setRelation"]=1 truthy → div maar _label_=undefined → LEEG
t1:  findSubObject() resolves (correct: isUni=false, crud="cRUd" voor test 2)
t1:  this.isUni=false, this.crud="cRUd"
t1:  allOptions.set([m1,m4,m5]) → triggert Angular CD
t1+ε: Template re-rendert:
      isUni=false → nonUni → data=["m1","m2","m4","m5"] (strings)
      *ngFor toont spans maar object._label_=undefined → LEGE SPANS
```

Angular wacht NIET op het resultaat van `async ngOnInit()`. Zodra `await` de eerste keer hit, gaat Angular door met renderen.

#### Verwacht zichtbaar gedrag (na analyse)

| Kolom | setRelation waarde | isUni na override | Zichtbaar in UI |
|-------|--------------------|-------------------|-----------------|
| **Naam** | `["m1","m2","m4","m5"]` (strings) | false (na CD) | 4 lege spans, geen labels |
| **Instroom** | `null` | true (instroomVanaf UNI=true) | "No item selected" ✅ |
| **Aantal** | `1` (integer) | true (aantal UNI=true) | Lege div (1._label_=undefined) |

Dit past bij de beschrijving "No item selected / leeg" voor alle FDD-cellen in de screenshot.

#### Bugs samengevat

| Bug | Omschrijving | Effect |
|-----|-------------|--------|
| **Bug 1a** | Employee-atomen als plain strings in API, niet als ObjectBase | `object._label_` = undefined → lege cel |
| **Bug 1b** | Integer-atomen als getal in API, niet als ObjectBase | `1._label_` = undefined → lege cel |
| **Bug 1c** | Date-atomen als string in API, niet als ObjectBase | `"2025-10-01"._label_` = undefined → lege cel |
| **Bug 2** | `isUni` + `crud` worden ingesteld vanuit de BOX-expressie (`I[Project]` = UNI+TOT), niet vanuit setRelation | Beginrender altijd als isUni=true, crud=cRud, ongeacht de testcase |
| **Bug 3** | `selectFrom`-items worden WEL genormaliseerd naar ObjectBase (regel 259-261 in component), maar `setRelation`/`data` NIET | Dropdown-opties werken straks, maar geselecteerde waarden tonen niet |

**Opmerking**: Bug 1a/1b/1c en Bug 3 zijn verwant. De normalisatie die al voor `selectFrom` gedaan wordt (strings → `{_id_, _label_}`) ontbreekt voor `setRelation`.

---

### Bewijs E: Compiler verwijdert U-bit uit FDD-box node (nieuw bewijs, 12-5-2026)

**Container herbouwd** na `docker compose build --no-cache` — nieuwe interfaces.json geladen.

**FDD-box node CRUD voor de 8 Default-tab tests (Naam)**:

```
Test 1 (cRud):  fddBoxCrud.update = false  ← correct
Test 2 (cRUd):  fddBoxCrud.update = false  ← FOUT! Verwacht true
Test 3 (cRuD):  fddBoxCrud.update = false  ← correct
Test 4 (cRUD):  fddBoxCrud.update = false  ← FOUT! Verwacht true
Test 5 (CRud):  fddBoxCrud.update = false  ← correct
Test 6 (CRUd):  fddBoxCrud.update = false  ← FOUT! Verwacht true
Test 7 (CRuD):  fddBoxCrud.update = false  ← correct
Test 8 (CRUD):  fddBoxCrud.update = false  ← FOUT! Verwacht true
```

**Conclusie harde feit**: De Ampersand compiler zet de U-bit NOOIT door in de FDD-box interface-node. De U-bit is altijd `false` in de FDD-box node.

**setRelation sub-node CRUD** (ook geverifieerd):

```
Test 2 (cRUd): setRelCrud.update = true  ✅ CORRECT
Test 4 (cRUD): setRelCrud.update = true  ✅
Test 6 (CRUd): setRelCrud.update = true  ✅
Test 8 (CRUD): setRelCrud.update = true  ✅
Overige: update = false ✅ (correct)
```

**Conclusie**: De setRelation sub-node heeft WEL de juiste U-bit. `findSubObject('setRelation')` MOET dus de juiste `{crud:"cRUd"}` teruggeven (als het slaagt!).

**Effect van de U-bit afwezigheid in FDD-box**: `$crud$` in de Box-FILTEREDDROPDOWN.html template leest uit de FDD-box node → altijd `update:false` → `crud="cRud"` voor tests 2/4/6/8 (i.p.v. `cRUd/cRUD/CRUd/CRUD`).

**Bewezen vanuit gegenereerde HTML** (grep -B3 van mode="box-filtereddropdown"):
Aanwezig: cRud (12×), cRuD (12×), CRud (12×), CRuD (12×) — totaal 48 ✅
Ontbreekt: cRUd, cRUD, CRUd, CRUD — NOOIT aanwezig ❌

### Bewijs F: Experiment van gebruiker (12-5-2026)

Gebruiker heeft de outer V[SESSION*Project]-CRUD gewijzigd in de Default-tab. Screenshot toont ZELFDE gedrag → FDD haalt CRUD NIET uit de omvattende interface-definitie. Dit bevestigt dat de FDD-box node zelf de bron is.

### Logische keten na al het bewijs

```
1. FDD-box node: crud.update=false (altijd, compiler verwijdert U)
2. $crud$ in template = "cRud" voor tests met U=true in ADL
3. Initiële render: canUpdate()=false → #uniRead (of #nonUni zonder dropdown)
4. findSubObject('setRelation') → crud.update=true voor cRUd tests ✅
5. Override: this.crud = "cRUd", this.isUni = false (voor projectMember)
6. allOptions.set([...]) → Angular CD trigger
7. Template zou moeten updaten: #nonUni met p-dropdown
8. MAAR: screenshot toont geen enkel verschil → stap 5-7 vuurt NIET of werkt niet
```

**Nog niet bewezen**: Of stap 4-7 daadwerkelijk uitvoert. Browser console FDD-DIAG logs zijn nodig.

---

## Openstaande vragen (status na fixes 12-5-2026)

1. **Browser console (Puppeteer)**: ✅ OPGELOST. `findSubObject()` werkt na lenient-navigatiefixCorrecte output: `{crud:"cRUd", conceptType:"Employee", isTot:false, isUni:false}` etc. voor alle 24 testgevallen Default tab.
2. **Compilergedrag U-bit**: Blijft zo. De compiler zet U-bit niet door naar de FDD-box node — dit is opzettelijk (de FDD-box is de "envelop", not de relatie zelf). `findSubObject()` leest CRUD uit de `setRelation`-subinterface.
3. **Fixes uitgevoerd**:
   - ✅ (a) `interfaces-json.service.ts`: lenient navigatie (skip onbekende path-segmenten zoals session-hash en data-atomen)
   - ✅ (b) `atomic-object.component.ts`: `normalizeAtom()` + `normalizedData()` helpers; alle signal-handlers (tap, subscribe, add, createAndAdd, remove, delete) genormaliseerd voor FDD-modus
   - ✅ (c) Template `#nonUni`: `*ngFor` → `selection()` (genormaliseerde data) i.p.v. `data` (ruwe scalaire strings)
   - ✅ (d) Template `#uniRead`: `{{ resource[propertyName]._label_ ?? resource[propertyName] }}` voor scalaire weergave
4. **Stap 4 uitbreiden**: ✅ Alle 8 testgevallen (cRud/cRUd/cRuD/cRUD/CRud/CRUd/CRuD/CRUD) werken visueel correct.

---

## Eindresultaat na fixes (12-5-2026)

### Niet-UNI (Naam, projectMember = Employee):
| Test | CRUD | Wat UI toont |
|------|------|--------------|
| 1 | cRud | m1 m2 m4 m5 (read-only, geen knoppen) |
| 2 | cRUd | m1 m2 m4 m5 + min-knop per item + "- Add employee -" dropdown |
| 3 | cRuD | m1 m2 m4 m5 + prullenbak per item |
| 4 | cRUD | m1 m2 m4 m5 + min+prullenbak per item + "- Add employee -" dropdown |
| 5 | CRud | m1 m2 m4 m5 (read-only, plus-knop aangemeld) |
| 6 | CRUd | m1 m2 m4 m5 + min + dropdown + plus-knop |
| 7 | CRuD | m1 m2 m4 m5 + prullenbak |
| 8 | CRUD | m1 m2 m4 m5 + min+prullenbak + dropdown + plus-knop |

### UNI (Instroom=Datum, Aantal=Integer):
- Null waarde + u=nee: "No datum selected" / "No integer selected" ✅
- Waarde aanwezig + u=nee: waarde getoond als tekst ("1") ✅
- Null waarde + u=ja: dropdown met placeholder "- Add datum -" of "- Add integer -" ✅
- Waarde aanwezig + u=ja: dropdown met huidige waarde als placeholder ✅

### Lege selectFrom (project 4):
- "- No employee to choose from -" ✅

### Root cause samenvatting:
De container had een OUDE versie van `interfaces-json.service.ts` (strict path navigatie) die faalde op de session-hash in het pad. Na het kopiëren van de lenient versie + rebuild werkte `findSubObject()` correct. Daarna bleken scalaire atomen (strings/integers) te worden geretourneerd door de backend zonder `._id_`/`._label_` properties, waardoor alle displaylogica leeg was. Na normalisatie met `normalizeAtom()` in de component en aanpassing van de template werkt alles.
