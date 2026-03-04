# Creating Custom VIEW Templates

A VIEW in Ampersand determines how an atom renders in the frontend. Without a VIEW, the frontend shows the atom's internal identifier. A VIEW maps relations onto named slots and passes those slot values to an HTML template for rendering.

This differs from a BOX template. A BOX template controls how a container lays out multiple fields. A VIEW template controls how a single atom value looks — for example, a short label with a long tooltip.

## The VIEW declaration

A VIEW declaration names a concept and binds relations to slot names:

```adl
VIEW <name> : <Concept> [DEFAULT]
{ "<slot1>" : <relation1>
, "<slot2>" : <relation2>
  ...
} HTML TEMPLATE "<filename>.html" ENDVIEW
```

The keyword `DEFAULT` makes this VIEW the default for the concept. When an interface contains an atom reference with no explicit VIEW annotation, Ampersand uses the default VIEW.

The backend evaluates each relation per atom at request time and collects the results in a JSON object named `_view_`. It includes that object in the API response alongside the atom.

The Ampersand compiler:
1. Evaluates the relations for every atom that needs to be displayed.
2. Passes the results as a JSON object under the key `_view_` alongside the atom in API responses.
3. Substitutes template variables (`$name$`, `$if(exprIsUni)$`, …) to produce an Angular HTML fragment.
4. The Angular frontend binds live data from the API, resolves `*ngIf` conditions, and renders plain HTML.

## Template file

An (optional) template overrules the default HTML-layout. This file contains Angular HTML with StringTemplate variables. The Ampersand compiler substitutes those variables at build time.

Two variables are available in every VIEW template.

`$name$` is the property name of this subinterface item in the parent component. At runtime, `resource.$name$` gives the data object for the atom.

`$if(exprIsUni)$` selects between two branches. A univalent expression (`[UNI]`) produces at most one atom; the template uses `*ngIf` to bind it. A non-univalent expression produces a list; the template uses `*ngFor` to iterate. The compiler inserts the correct branch.

Inside both branches, `viewData['_view_']` holds the JSON object the backend built from the VIEW relations.

## Example: TextWithPopover

The following VIEW displays a short label and shows a tooltip with a longer text on hover:

```adl
VIEW EisMetUitleg : Eis DEFAULT
{ "text"    : eisTekst[Eis*EisTekst]
, "popover" : bijschrijving[Eis*Tekst]
} HTML TEMPLATE "TextWithPopover.html" ENDVIEW
```

In this example, `VIEW EisMetUitleg: Eis DEFAULT` is the *default* way to display any atom of concept `Eis`.
The item `"text" : eisTekst[Eis*EisTekst]` fills slot `text` with the target concepts of the relation `eisTekst`.
`"popover" : bijschrijving[Eis*Tekst]` fills slot `popover` with the target of the relation `bijschrijving`.
The part `HTML TEMPLATE "TextWithPopover.html"` delegates the HTML to this custom template file.

The file `project/templates/TextWithPopover.html` implements this:

```html
$if(exprIsUni)$
<ng-container *ngIf="resource.$name$ as viewData">
  <span *ngIf="viewData['_view_']['text'] && viewData['_view_']['popover']"
        [title]="viewData['_view_']['popover']"
        style="cursor: help; text-decoration: underline dotted;">
    {{ viewData['_view_']['text'] }}
  </span>
  <span *ngIf="viewData['_view_']['text'] && !viewData['_view_']['popover']">
    {{ viewData['_view_']['text'] }}
  </span>
  <span *ngIf="!viewData['_view_']['text'] && viewData['_view_']['popover']">
    {{ viewData['_view_']['popover'] }}
  </span>
</ng-container>
$else$
<div *ngFor="let viewData of resource.$name$">
  <span *ngIf="viewData['_view_']['text'] && viewData['_view_']['popover']"
        [title]="viewData['_view_']['popover']"
        style="cursor: help; text-decoration: underline dotted;">
    {{ viewData['_view_']['text'] }}
  </span>
  <span *ngIf="viewData['_view_']['text'] && !viewData['_view_']['popover']">
    {{ viewData['_view_']['text'] }}
  </span>
  <span *ngIf="!viewData['_view_']['text'] && viewData['_view_']['popover']">
    {{ viewData['_view_']['popover'] }}
  </span>
</div>
$endif$
```

The user interface applies this template to every atom of concept `Eis`, unless it is overruled by another (non-DEFAULT) template.

## The rendering pipeline

Template variables and what they resolve to:

| Variable | Resolved at | Value for this example |
|---|---|---|
| `$name$` | Compile time | `I` (the property name in the interface) |
| `$if(exprIsUni)$` | Compile time | `true` — `I[Eis]` is UNI (identity is always UNI) |
| `viewData['_view_']['text']` | Angular runtime | value from `eisTekst[Eis*EisTekst]` |
| `viewData['_view_']['popover']` | Angular runtime | value from `bijschrijving[Eis*Tekst]` |

---

### 1. After Ampersand Compilation (Step 2: Template with Substituted Variables)

After the Ampersand compiler processes the template, `$name$` → `I` and the `$if(exprIsUni)$` branch is selected.  
The result is placed inside the generated Angular component HTML for the `Eisen` interface.

**Generated file:** `/var/www/frontend/src/app/generated/eisen/eisen.component.html` (excerpt)

```html
<ng-container *ngIf="resource.I as viewData">
  <!-- Text with tooltip (both slots filled) -->
  <span *ngIf="viewData['_view_']['text'] && viewData['_view_']['popover']"
        [title]="viewData['_view_']['popover']"
        style="cursor: help; text-decoration: underline dotted;">
    {{ viewData['_view_']['text'] }}
  </span>
  <!-- Text only (no tooltip) -->
  <span *ngIf="viewData['_view_']['text'] && !viewData['_view_']['popover']">
    {{ viewData['_view_']['text'] }}
  </span>
  <!-- Popover only -->
  <span *ngIf="!viewData['_view_']['text'] && viewData['_view_']['popover']">
    {{ viewData['_view_']['popover'] }}
  </span>
</ng-container>
```

This is still Angular. The `*ngIf`, `[title]`, and `{{ }}` are Angular directives that are resolved at browser runtime.

---

### 2. The Backend API Response (what Angular receives)

The Angular frontend fetches the interface data from the Ampersand backend API.  
For `EIS_AZ_012` the response looks like this:

```
GET /api/v1/resource/SESSION/1/Eisen?limit=10&content=true
```

Relevant portion of the response (abbreviated):

```json
{
  "_id_": "EIS_AZ_012",
  "_label_": "vrijVan…",
  "_view_": {
    "text":    "vrijVan",
    "popover": "The plants were tested and found free from Tomato yellow leaf curl virus, Tomato brown rugose fruit virus, Ralstonia solanacearum, Pepino mosaic virus, Tomato spotted wilt virus."
  },
  "I": {
    "_id_": "EIS_AZ_012",
    "_view_": {
      "text":    "vrijVan",
      "popover": "The plants were tested and found free from Tomato yellow leaf curl virus, Tomato brown rugose fruit virus, Ralstonia solanacearum, Pepino mosaic virus, Tomato spotted wilt virus."
    }
  }
}
```

Important: the `_view_` object appears *both* at the top level of the atom and inside the `I` sub-resource.  
The template accesses it via `resource.I` (the column), so it uses `resource.I._view_` = `viewData['_view_']`.

---

### 3. Resolved Template with Real Values (Step 3: Template with Data Injected)

With the API data bound, Angular evaluates the `*ngIf` conditions. For `EIS_AZ_012`:

- `viewData['_view_']['text']`    = `"vrijVan"` → truthy ✓  
- `viewData['_view_']['popover']` = `"The plants were tested…"` → truthy ✓  
- First `*ngIf` condition evaluates to **true**

The first `<span>` block is selected, the other two are excluded by Angular.  
The Angular template collapses to the following effective HTML:

```html
<span
  title="The plants were tested and found free from Tomato yellow leaf curl virus, Tomato brown rugose fruit virus, Ralstonia solanacearum, Pepino mosaic virus, Tomato spotted wilt virus."
  style="cursor: help; text-decoration: underline dotted;">
  vrijVan
</span>
```

No Angular remains. This is plain HTML sent to the browser's DOM.

---

### 4. The Rendered UI (Step 4: Screenshot)

In the browser the table cell shows:

```
┌──────────────────┐
│  vrijVan···      │  ← dotted underline; cursor changes to ❓ on hover
└──────────────────┘
```

When the user hovers over the cell, the native browser tooltip appears:

```
┌──────────────────────────────────────────────────────────────────────────────────────┐
│ The plants were tested and found free from Tomato yellow leaf curl virus, Tomato     │
│ brown rugose fruit virus, Ralstonia solanacearum, Pepino mosaic virus, Tomato        │
│ spotted wilt virus.                                                                  │
└──────────────────────────────────────────────────────────────────────────────────────┘
```

> **Note:** The tooltip is rendered via the HTML `title` attribute — a native browser tooltip.  
> It appears after hovering for approximately one second. No JavaScript library is required.

---

### 5. The Four Cases

The template handles all four combinations of populated/empty slots:

| `text` | `popover` | Rendered output |
|--------|-----------|-----------------|
| ✓ filled | ✓ filled | Text with dotted underline + tooltip on hover |
| ✓ filled | ✗ empty / null | Plain text, no tooltip |
| ✗ empty | ✓ filled | Popover text shown as plain text |
| ✗ empty | ✗ empty | Nothing rendered (outer `*ngIf="resource.I"` is falsy) |

---

### 6. Summary: The Complete Pipeline

```
ADL script (Kernmodel.adl)
  │
  │  VIEW EisMetUitleg: Eis DEFAULT
  │  { "text"    : eisTekst[Eis*EisTekst]
  │  , "popover" : bijschrijving[Eis*Tekst]
  │  } HTML TEMPLATE "TextWithPopover.html" ENDVIEW
  │
  ▼ Ampersand compiler (compile time)
  │  - reads TextWithPopover.html
  │  - substitutes $name$ → "I"
  │  - selects $if(exprIsUni)$ branch
  │  - writes eisen.component.html
  │
  ▼ Angular build (compile time)
  │  - type-checks the template against EisenInterface
  │  - bundles into browser JavaScript
  │
  ▼ PHP Backend (runtime, per HTTP request)
  │  - evaluates eisTekst[EIS_AZ_012] → "vrijVan"
  │  - evaluates bijschrijving[EIS_AZ_012] → "The plants were tested…"
  │  - returns JSON: { "_view_": { "text": "vrijVan", "popover": "…" } }
  │
  ▼ Angular frontend (runtime, in browser)
  │  - binds resource.I to viewData
  │  - evaluates *ngIf conditions
  │  - resolves {{ viewData['_view_']['text'] }} → "vrijVan"
  │  - resolves [title] binding → "The plants were tested…"
  │
  ▼ Browser DOM (rendered HTML)
     <span title="The plants were tested…"
           style="cursor: help; text-decoration: underline dotted;">
       vrijVan
     </span>
```

---

## Reusing this Pattern

To create a similar VIEW for another concept, the pattern is:

```adl
VIEW MyView: MyConcept DEFAULT
{ "text"    : <term-for-short-label>[MyConcept*SomeType]
, "popover" : <term-for-long-text>[MyConcept*Tekst]
} HTML TEMPLATE "TextWithPopover.html" ENDVIEW
```

Place `TextWithPopover.html` in `project/templates/`. The Dockerfile copies this directory to `/var/www/frontend/templates/` so the Ampersand compiler picks it up automatically during the next `docker compose up --build`.

The template file is **concept-independent** and **reusable** — any VIEW that defines `"text"` and `"popover"` slots can use it.
