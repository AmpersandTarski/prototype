# Hoe `app-atomic-alphanumeric` transformeert naar HTML

Dit document legt stap voor stap uit hoe de Angular-component `app-atomic-alphanumeric` omgezet wordt naar zichtbare HTML, voor alle combinaties van CRUD-vlaggen en univalentie.

---

## 1. Wat is `app-atomic-alphanumeric`?

In de gegenereerde HTML van het testprogramma staat zoiets:

```html
<app-atomic-alphanumeric
    [property]="resource.setRelation"
    propertyName="setRelation"
    crud="cRUd"
    [isUni]="true"
    [resource]="resource"
    [interfaceComponent]="interfaceComponent"
></app-atomic-alphanumeric>
```

Dit is een **Angular component-tag**. De browser snapt dit zelf niet — Angular vertaalt het naar gewone HTML. Denk eraan als een telefoonnummer dat naar een contactpersoon verwijst: de tag is het "label", Angular voert de eigenlijke code uit en plakt het resultaat in de pagina.

---

## 2. De invoer die Angular meekrijgt (`@Input`)

De component krijgt een aantal "invoerwaardes" mee, net als functieparameters:

| Naam | Waarde (voorbeeld) | Betekenis |
|---|---|---|
| `property` | `["m1", "m2", "m4", "m5"]` | De huidige waarden van de relatie |
| `resource` | Een JavaScript-object met `_path_`, `setRelation`, … | Het object waar dit veld bij hoort (bijv. een project) |
| `propertyName` | `"setRelation"` | De naam van het veld binnen het object |
| `crud` | `"cRUd"` | Welke rechten de gebruiker heeft (zie stap 3) |
| `isUni` | `false` | Heeft de relatie maximaal één waarde? |
| `isTot` | `false` | Is de relatie verplicht (totaal)? |
| `interfaceComponent` | Component-referentie | "Postbode" die berichten naar de backend stuurt |

---

## 3. De CRUD-vlag ontleed

Het woord `crud` staat voor **Create, Read, Update, Delete** — de vier dingen die je met data kunt doen. Door `"cRUd"` karakter voor karakter te lezen:

```
c R U d
│ │ │ └── kleine d → canDelete() = false → geen prullenbak
│ │ └──── grote  U → canUpdate() = true  → bewerkveld zichtbaar
│ └────── grote  R → canRead()   = true  → data zichtbaar
└──────── kleine c → canCreate() = false → geen nieuw-knop
```

**Speciale regel: CRu + UNI is ongeldig**
De combinatie C (Create) zonder U (Update) bij een univalente relatie is semantisch zinloos: je kunt geen tweede waarde toevoegen als er al één is. Zolang de Ampersand-compiler dit niet afvangt, behandelt de component `CRu+UNI` als `cRu+UNI`: de C-vlag wordt genegeerd.

---

## 4. De `data` getter: welke waardes zijn er?

Voordat er HTML wordt gebouwd, haalt Angular de lijst van huidige waarden op via een getter in `BaseAtomicComponent`:

```typescript
get data(): string[] {
    return this.requireArray(this.resource[this.propertyName]);
    // bijv. ["m1"] of ["m1","m2","m4","m5"] of []
}
```

Bij een univalente relatie (`isUni=true`) bevat `data` maximaal één element.

> **Belangrijk verschil met `atomic-object`:** De `#list` template van `atomic-alphanumeric` rendert via `*ngFor="let row of data"` — de getter leest rechtstreeks van het resource-object. Er is geen tussenliggende signal, wat `atomic-object` wel nodig heeft.

---

## 5. De keuzeboom: welke template wordt gerenderd?

De Angular-template begint met drie keuzetakken:

```html
<!-- Tak 1: UNI + canUpdate → enkel bewerkbaar tekstveld -->
<div *ngIf="canRead() && canUpdate() && isUni">
    → #uniEdit
</div>

<!-- Tak 2: alle andere leesbare gevallen → generieke lijst -->
<div *ngIf="canRead() && !(canUpdate() && isUni)">
    → #list
</div>

<!-- Tak 3: niet leesbaar -->
<div *ngIf="!canRead()">
    → "Alphanumeric is not readable"
</div>
```

### Welke template bij welke CRUD + isUni combinatie?

| crud | isUni | Template | Toelichting |
|------|-------|----------|-------------|
| `cRud` | false | `#list` | Alleen lezen, geen bewerking |
| `cRud` | true  | `#list` | Alleen lezen, geen bewerking |
| `cRUd` | false | `#list` | Bewerkbare rijen + autocomplete |
| `cRUd` | true  | `#uniEdit` | Bewerkbaar tekstveld (Tak 1) |
| `cRuD` | false | `#list` | Prullenbak per waarde |
| `cRuD` | true  | `#list` | Prullenbak per waarde (geen edit) |
| `cRUD` | false | `#list` | Bewerkbare rijen + prullenbak |
| `cRUD` | true  | `#uniEdit` | Bewerkbaar tekstveld + prullenbak (Tak 1) |
| `CRud` | false | `#list` | Alleen lezen + invoerveld onderaan |
| `CRud` | true  | `#list` | *(C wordt genegeerd: zie speciale regel)* |
| `CRUd` | false | `#list` | Bewerkbare rijen + invoerveld |
| `CRUd` | true  | `#uniEdit` | Bewerkbaar tekstveld (Tak 1) |
| `CRuD` | false | `#list` | Prullenbak + invoerveld |
| `CRuD` | true  | `#list` | *(C wordt genegeerd: zie speciale regel)* |
| `CRUD` | false | `#list` | Alles: bewerkbaar + prullenbak + invoerveld |
| `CRUD` | true  | `#uniEdit` | Bewerkbaar tekstveld + prullenbak (Tak 1) |

---

## 6. Gedeelde knop-templates: `#removeButton` en `#deleteButton`

Zowel `#uniEdit` als `#list` hergebruiken twee gedeelde ng-templates voor de minus- en prullenbakknop. De `isTot`-logica zit centraal hierin:

### `#removeButton` — ontkoppelen (U)

```html
<ng-template #removeButton let-index let-canRemove="canRemove">
    <div
        *ngIf="canRemove"
        class="pi pi-fw pi-minus"
        [ngStyle]="{ color: data[index] != null ? 'red' : 'grey',
                     cursor: data[index] != null ? 'pointer' : 'default' }"
        (click)="data[index] != null && removeItem(index)"
        pTooltip="Remove"
    ></div>
</ng-template>
```

- **Zichtbaar** afhankelijk van de meegegeven context-variabele `canRemove`
- **Grijs en niet-klikbaar** als `data[index]` leeg is
- Stuurt `PATCH [remove]` → verwijdert de *koppeling*, het atom zelf blijft bestaan

### `#deleteButton` — atom verwijderen (D)

```html
<ng-template #deleteButton let-index let-showDelete="showDelete">
    <div
        *ngIf="showDelete"
        class="pi pi-fw pi-trash"
        (click)="delete(index)"
        pTooltip="Delete"
        style="color: red; cursor: pointer"
    ></div>
</ng-template>
```

- **Zichtbaar** afhankelijk van de meegegeven context-variabele `showDelete`
- Verwijdert het *atom zelf* én al zijn relaties via `DELETE`

### isTot-logica per context

| Gebruik | `canRemove` / `showDelete` expressie | Toelichting |
|---------|--------------------------------------|-------------|
| UNI minus | `canUpdate() && !isTot` | Bij isTot mag je de UNI-waarde niet ontkoppelen |
| UNI trash | `canDelete() && !(isTot && resource[propertyName])` | Bij isTot + aanwezige waarde: verboden |
| Niet-UNI minus | `canUpdate() && (!isTot \|\| data.length > 1)` | Bij isTot: verboden als dit het laatste item is |
| Niet-UNI trash | `canDelete() && (!isTot \|\| data.length > 1)` | Zelfde logica |

---

## 7. De `#uniEdit` template (UNI + canUpdate)

```html
<ng-template #uniEdit>
    <div style="display: flex; align-items: center; gap: 4px">
        <!-- U → minus: verborgen bij isTot -->
        <ng-container *ngTemplateOutlet="removeButton;
            context: { $implicit: 0, canRemove: canUpdate() && !isTot }">
        </ng-container>
        <!-- D → prullenbak: verborgen bij isTot als er een waarde aanwezig is -->
        <ng-container *ngTemplateOutlet="deleteButton;
            context: { $implicit: 0, showDelete: canDelete() && !(isTot && resource[propertyName]) }">
        </ng-container>
        <input
            type="text"
            [(ngModel)]="resource[propertyName]"
            [attr.list]="'opts-uni-' + propertyName"
            (focus)="captureUniOriginalValue()"
            (input)="dirty = true"
            (keydown.enter)="$any($event.target).blur()"
            (blur)="updateValue()"
            [required]="isTot"
        />
        <datalist [id]="'opts-uni-' + propertyName">
            <option *ngFor="let opt of (options ?? [])" [value]="opt"></option>
        </datalist>
    </div>
</ng-template>
```

**Onderdelen:**

| Element | Conditie | Gedrag |
|---------|----------|--------|
| ➖ Minus | `canUpdate() && !isTot` | Ontkoppelt de waarde via `PATCH [remove]` |
| 🗑 Prullenbak | `canDelete() && !(isTot && resource[prop])` | Verwijdert het atoom zelf via `DELETE` |
| `<input>` | altijd (Tak 1 impliceert canUpdate) | `[(ngModel)]` — twee-weg binding met het model |
| `<datalist>` | altijd | Browser-native autocomplete; `options ?? []` is null-safe |

**Let op `options ?? []`:** De opties-lijst is getypeerd als `string[] | null`. `null` betekent dat het ophalen mislukt is (bijv. HTTP 403). De `?? []` zorgt dat de `*ngFor` niet crasht; validatie wordt in dat geval volledig aan de backend overgelaten.

**Levenscyclus van een bewerking:**
1. Gebruiker klikt in het veld → `(focus)` → `captureUniOriginalValue()` slaat de huidige waarde op
2. Gebruiker typt → `(input)` → `dirty = true`
3. Enter of klik buiten het veld:
   - Enter → `(keydown.enter)` → `blur()` op het element → triggers `(blur)`
   - Klik elders → direct `(blur)`
4. `(blur)` → `updateValue()` (override in `AtomicAlphanumericComponent`):
   - Validatie: als `!canCreate()` en `options !== null` → controleer of de waarde in de optielijst staat
   - Geldig: `super.updateValue()` → `PATCH [replace]` naar backend
   - Ongeldig: herstel `resource[propertyName]` naar `uniOriginalValue`, geen patch

**Waarom `captureUniOriginalValue()` nodig is:**
De `[(ngModel)]` binding muteert het model direct terwijl de gebruiker typt. Bij afwijzing moet de originele waarde hersteld worden, maar die is al overschreven. Daarom wordt hij bij focus opgeslagen.

**Patch-operatie bij een geldige wijziging:**
```json
[{ "op": "replace", "path": "setRelation", "value": "m4" }]
```

---

## 8. De `#list` template van binnenuit

De `#list` template herhaalt zichzelf voor elke waarde in `data` (de getter leest direct van `resource[propertyName]`):

```html
<ng-template #list>
    <div *ngFor="let row of data; let i = index">
        <!-- U → minus: verborgen bij isTot als dit het laatste item is -->
        <ng-container *ngTemplateOutlet="removeButton;
            context: { $implicit: i, canRemove: canUpdate() && (!isTot || data.length > 1) }">
        </ng-container>
        <!-- D → prullenbak: verborgen bij isTot als dit het laatste item is -->
        <ng-container *ngTemplateOutlet="deleteButton;
            context: { $implicit: i, showDelete: canDelete() && (!isTot || data.length > 1) }">
        </ng-container>
        <!-- U → bewerkbaar tekstveld met autocomplete; anders platte tekst -->
        <input *ngIf="canUpdate()" type="text" #editInput [value]="row"
               [attr.list]="'opts-' + propertyName + '-' + i"
               (blur)="validateAndUpdate(row, editInput.value)"
               (keydown.enter)="$event.preventDefault(); validateAndUpdate(row, editInput.value)"
               style="padding-left: 9px" />
        <datalist *ngIf="canUpdate()" [id]="'opts-' + propertyName + '-' + i">
            <option *ngFor="let opt of (options ?? [])" [value]="opt"></option>
        </datalist>
        <span *ngIf="!canUpdate()" style="padding-left: 9px">{{ row }}</span>
    </div>
    <!-- C → invoerveld (maakt nieuw atom aan) -->
    <div *ngIf="canCreate()" class="p-inputgroup">
        <input
            type="text"
            [(ngModel)]="newValue"
            [placeholder]="'Add value'"
            [required]="isTot && data.length === 0"
            (keyup.enter)="addValue()"
            [disabled]="isNewItemInputDisabled()"
        />
        <button pButton icon="pi pi-plus" (click)="addValue()"
                [disabled]="isNewItemInputDisabled()"></button>
    </div>
</ng-template>
```

### Per rij

| Element | Conditie | Gedrag |
|---------|----------|--------|
| ➖ Minus | `canUpdate() && (!isTot \|\| data.length > 1)` | `PATCH [remove]` — ontkoppelt koppeling |
| 🗑 Prullenbak | `canDelete() && (!isTot \|\| data.length > 1)` | `DELETE` — verwijdert het atom |
| `<input>` | `canUpdate()` | één-weg `[value]="row"`, validatie in `validateAndUpdate` |
| `<datalist>` | `canUpdate()` | Autocomplete, null-safe via `options ?? []` |
| `<span>` | `!canUpdate()` | Platte tekst |

### Add-input (onderaan de lijst)

| Element | Conditie | Gedrag |
|---------|----------|--------|
| Add-input | `canCreate()` | Zichtbaar als C toegestaan |
| `[required]` | `isTot && data.length === 0` | Verplicht als isTot en lijst leeg |
| `[disabled]` | `isNewItemInputDisabled()` = `isUni && data.length > 0` | Grijs als UNI al gevuld |

---

## 9. Verschiltabel: UNI versus niet-UNI

| Aspect | `#uniEdit` (UNI + canUpdate) | `#list` (alle andere) |
|--------|------------------------------|----------------------|
| **Model-binding input** | `[(ngModel)]` — twee-weg | `[value]="row"` — één-weg |
| **Patch-operatie edit** | `replace` | `remove(oud) + add(nieuw)` |
| **Validatie ongeldig** | Model handmatig hersteld via `uniOriginalValue` | Browser reset automatisch (`[value]` onveranderd) |
| **Minus-icoon (remove)** | ➖ aanwezig als `canUpdate() && !isTot` | ➖ aanwezig als `canUpdate() && (!isTot \|\| data.length > 1)` |
| **Prullenbak (delete)** | 🗑 aanwezig als `canDelete() && !(isTot && resource[prop])` | 🗑 aanwezig als `canDelete() && (!isTot \|\| data.length > 1)` |
| **Autocomplete** | `<datalist>` met `options ?? []` | `<datalist>` per rij, `options ?? []` |
| **Add-input** | Afwezig (UNI heeft al een veld) | Aanwezig als `canCreate()` |
| **C-vlag bij volle UNI** | Genegeerd (neergedowncased) | invoerveld grijs als `isUni && data.length > 0` |
| **Data-bron** | `resource[propertyName]` via ngModel | `data` getter → `resource[propertyName]` |

---

## 10. Overzicht: alle 8 zinvolle CRUD-combinaties

### Niet-univalent (`isUni=false`)

| crud | Minus | Prullenbak | Bewerkveld | Add-input |
|------|-------|------------|------------|-----------|
| `cRud` | ✗ | ✗ | ✗ (platte tekst) | ✗ |
| `cRUd` | ✓ (tenzij isTot=1-item) | ✗ | ✓ + autocomplete | ✗ |
| `cRuD` | ✗ | ✓ (tenzij isTot=1-item) | ✗ (platte tekst) | ✗ |
| `cRUD` | ✓ (tenzij isTot=1-item) | ✓ (tenzij isTot=1-item) | ✓ + autocomplete | ✗ |
| `CRud` | ✗ | ✗ | ✗ (platte tekst) | ✓ |
| `CRUd` | ✓ (tenzij isTot=1-item) | ✗ | ✓ + autocomplete | ✓ |
| `CRuD` | ✗ | ✓ (tenzij isTot=1-item) | ✗ (platte tekst) | ✓ |
| `CRUD` | ✓ (tenzij isTot=1-item) | ✓ (tenzij isTot=1-item) | ✓ + autocomplete | ✓ |

*"tenzij isTot=1-item"* = knop verborgen als `isTot=true` én dit het enige resterend item is.

### Univalent (`isUni=true`)

| crud | Template | Minus | Prullenbak | Tekstveld | Toelichting |
|------|----------|-------|------------|-----------|-------------|
| `cRud` | `#list` | ✗ | ✗ | Platte tekst | Alleen lezen |
| `cRUd` | `#uniEdit` | ✓ (niet bij isTot) | ✗ | ✓ + autocomplete | Bewerken via replace |
| `cRuD` | `#list` | ✗ | ✓ (niet bij isTot+waarde) | Platte tekst | Alleen verwijderen |
| `cRUD` | `#uniEdit` | ✓ (niet bij isTot) | ✓ (niet bij isTot+waarde) | ✓ + autocomplete | Bewerken + verwijderen |
| `CRud` | `#list` | ✗ | ✗ | Platte tekst | C wordt genegeerd (=cRud) |
| `CRUd` | `#uniEdit` | ✓ (niet bij isTot) | ✗ | ✓ + autocomplete | C+U samen: bewerken |
| `CRuD` | `#list` | ✗ | ✓ (niet bij isTot+waarde) | Platte tekst | C wordt genegeerd (=cRuD) |
| `CRUD` | `#uniEdit` | ✓ (niet bij isTot) | ✓ (niet bij isTot+waarde) | ✓ + autocomplete | Bewerken + verwijderen |

---

## 11. Wat gebeurt er als ik een waarde bewerk?

### Niet-UNI (`#list`): e.g. cRUd, row="m2" → "m4"

1. `(keydown.enter)` → `validateAndUpdate("m2", "m4")`
2. `!canCreate()` = true, `options !== null` en bevat "m4" → validatie geslaagd
3. `updateItem("m2", "m4")` stuurt:
   ```json
   [
     { "op": "remove", "path": "setRelation/m2" },
     { "op": "add",    "path": "setRelation", "value": "m4" }
   ]
   ```
4. Backend bevestigt → Angular herlaadt de rij

Ongeldige invoer ("m99" bestaat niet):
- `validateAndUpdate()` retourneert vroegtijdig, geen patch
- `[value]="row"` zorgt dat het veld automatisch teruggaat naar "m2"

### UNI (`#uniEdit`): e.g. cRUd+UNI, waarde="m1" → "m4"

1. Focus → `captureUniOriginalValue()` slaat "m1" op
2. Gebruiker typt "m4" → `dirty = true`
3. Enter → `blur()` → `updateValue()` (override)
4. Validatie: `options !== null` en "m4" staat erin → geslaagd
5. `super.updateValue()` stuurt:
   ```json
   [{ "op": "replace", "path": "setRelation", "value": "m4" }]
   ```

Ongeldige invoer ("m99"):
- Validatie mislukt → `resource[propertyName] = uniOriginalValue` ("m1")
- `dirty = false`, geen patch
- `[(ngModel)]` herrendert het veld met "m1"

---

## 12. Vergelijking met `atomic-object`: de `selection`-signal

`atomic-alphanumeric` leest in de `#list` template altijd direct van de `data` getter (`resource[propertyName]`). Er is **geen tussenliggende Angular signal** nodig.

`atomic-object` gebruikt wél een `selection` signal voor de `#nonUni` template:
```html
<div *ngFor="let object of selection(); let i = index" class="item">
```

Dit signal werd in `cRud`-modus **nooit geïnitialiseerd**, waardoor bestaande atomen niet getoond werden. De fix (mei 2026) zorgt dat direct vóór de early return `selection.set([...this.data])` wordt aangeroepen wanneer `!isUni`:

```typescript
if (!(this.canUpdate() || this.selectOptions !== undefined)) {
    if (!this.isUni) {
        this.selection.set([...this.data]);  // FIX: initialiseer voor read-only weergave
    }
    return;
}
```

`atomic-alphanumeric` heeft dit probleem nooit gehad omdat het nooit een signal tussenplaatst: `*ngFor="let row of data"` haalt altijd de actuele waarde op via de getter.
