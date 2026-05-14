# Frontend component internals

This document describes the internal structure of the Angular components that render Ampersand interfaces. It is written for contributors who want to modify existing components, maintain the framework, or write new BOX templates.

For the user-facing reference — which components are available and how to invoke them from an Ampersand script — see [Frontend components](frontend-components).

## Component hierarchy

All frontend components extend one of two base classes.

```
BaseAtomicComponent
├── AtomicAlphanumericComponent
├── AtomicBigalphanumericComponent
├── AtomicHugealphanumericComponent
├── AtomicBooleanComponent
├── AtomicDateComponent
├── AtomicDatetimeComponent
├── AtomicFloatComponent
├── AtomicIntegerComponent
├── AtomicPasswordComponent
└── AtomicObjectComponent

BaseBoxComponent
├── BoxFormComponent
├── BoxTableComponent
├── BoxTabsComponent
├── BoxRawComponent
└── BoxPropButtonComponent
```

`BaseAtomicComponent` provides shared logic for components that display or edit a single relation value. `BaseBoxComponent` provides shared logic for components that contain other components.

## BaseAtomicComponent

`BaseAtomicComponent` (file: `frontend/src/app/shared/atomic-components/BaseAtomicComponent.class.ts`) provides:

**CRUD helper methods** — `canCreate()`, `canRead()`, `canUpdate()`, `canDelete()` parse the `crud` input string and return booleans. Components use these in `*ngIf` conditions to show or hide controls.

**Multiplicity inputs** — `@Input() isUni: boolean` and `@Input() isTot: boolean` reflect the `[UNI]` and `[TOT]` constraints from the Ampersand model.

**Data helpers** — `requireArray(value)` normalises a value to an array, handling the difference between a UNI relation (single value) and a non-UNI relation (array). The `data` getter calls `requireArray(this.resource[this.propertyName])`.

**Item management** — `addItem()` and `removeItem()` send `PATCH [add]` and `PATCH [remove]` requests to the backend for non-UNI relations. `isNewItemInputDisabled()` returns true when `isUni` is true and the relation already has a value.

**Update lifecycle** — `updateValue()` checks the `dirty` flag, transforms an empty string to `null`, then sends a `PATCH [replace]` request via `interfaceComponent.patch()`.

## BaseBoxComponent

`BaseBoxComponent` (file: `frontend/src/app/shared/box-components/BaseBoxComponent.class.ts`) provides:

**CRUD rights** — same `canCreate()`, `canRead()`, `canUpdate()`, `canDelete()` methods as `BaseAtomicComponent`.

**Item management** — `createItem()` sends a `POST` request to create a new object. `removeItem()` removes the relation link. `deleteItem()` deletes the object from the database and removes all its relations throughout the application.

**Resource binding** — `@Input() resource` holds the current data context. `@Input() propertyName` identifies which property within the resource this component manages.

## AtomicAlphanumericComponent: template selection

`AtomicAlphanumericComponent` (files: `atomic-alphanumeric.component.ts` and `.html`) selects one of two Angular templates based on `canUpdate()` and `isUni`.

**`#uniEdit` template** — rendered when `canRead() && canUpdate() && isUni`. This template handles a UNI relation that the user may edit.

**`#list` template** — rendered when `canRead() && !(canUpdate() && isUni)`. This template handles all other readable cases: read-only display, non-UNI lists, and non-UNI editable lists.

**Not readable** — when `!canRead()`, the component renders the text "Alphanumeric is not readable" and nothing else.

### The `data` getter

Both templates read values from the `data` getter, which calls `requireArray(this.resource[this.propertyName])`. The getter reads directly from the resource object on every render cycle. There is no intermediate Angular Signal. This differs from `AtomicObjectComponent`, which uses a `selection` signal (see the section on differences below).

### The `#uniEdit` template

`#uniEdit` renders a single `<input>` with `[(ngModel)]` two-way binding. Because two-way binding mutates the model immediately as the user types, the component saves the original value on focus via `captureUniOriginalValue()`. If the user types an invalid value and blurs the field, `updateValue()` restores `resource[propertyName]` to the saved value.

On a valid edit, `updateValue()` sends:

```json
[{ "op": "replace", "path": "propertyName", "value": "newValue" }]
```

### The `#list` template

`#list` renders a `*ngFor` loop over `data`. Each row uses `[value]="row"` one-way binding, so the input field resets itself automatically if `validateAndUpdate()` rejects the new value.

On a valid edit of an existing row, `validateAndUpdate()` sends:

```json
[
  { "op": "remove", "path": "propertyName/oldValue" },
  { "op": "add",    "path": "propertyName", "value": "newValue" }
]
```

When `canCreate()` is true, a separate add-input appears below the list. It is disabled when `isUni && data.length > 0`.

### Shared `#removeButton` and `#deleteButton` templates

Both `#uniEdit` and `#list` delegate their minus (remove) and trash (delete) icons to two shared `ng-template` blocks.

The `#removeButton` template hides the button when `isTot` would be violated:

- UNI: hidden when `!canUpdate() || isTot`
- non-UNI: hidden when `!canUpdate() || (isTot && data.length <= 1)`

The `#deleteButton` template applies the same pattern for the `D` permission:

- UNI: hidden when `!canDelete() || (isTot && resource[propertyName] != null)`
- non-UNI: hidden when `!canDelete() || (isTot && data.length <= 1)`

### Special rule: `C` without `U` on a UNI relation

Giving `C` rights without `U` rights on a `[UNI]` relation is semantically meaningless: the user cannot add a second value to a relation that already holds one. The base class downcases `C` to `c` in this situation. The `#list` template still shows the add-input because its condition is `!(canUpdate() && isUni)`, which evaluates to true. This is a known inconsistency; the `atomic-object` component handles this case correctly by suppressing the add control.

## AtomicObjectComponent: differences from AtomicAlphanumericComponent

`AtomicObjectComponent` and `AtomicAlphanumericComponent` share the same base class and the same CRUD/UNI/TOT logic. The differences between them follow from the difference in type: an object has an identity and navigable sub-interfaces, while an alphanumeric value is a plain string.

### Logically justified differences

`AtomicObjectComponent` uses `<p-dropdown>` (PrimeNG) for selection. The dropdown restricts choices to existing objects in the database. `AtomicAlphanumericComponent` uses `<input type="text">` because text values are typed freely.

`AtomicObjectComponent` renders a sub-interface navigation link next to each object label. Alphanumeric has no equivalent because a string value has no interface to navigate to.

`AtomicObjectComponent` shows an explicit plus button to create a new object. Alphanumeric creates new values by typing in the add-input below the list.

### The `selection` signal

`AtomicObjectComponent` uses an Angular Signal (`selection`) to drive the `#nonUni` template:

```html
<div *ngFor="let object of selection(); let i = index">
```

The signal is populated inside the reactive update chain. In `cRud` mode (read-only, no update, no `selectOptions`), the component hits an early return before the signal is populated. This caused existing objects to not appear in read-only mode.

The fix (May 2026) adds `selection.set([...this.data])` before the early return when `!isUni`:

```typescript
if (!(this.canUpdate() || this.selectOptions !== undefined)) {
    if (!this.isUni) {
        this.selection.set([...this.data]);  // initialise for read-only display
    }
    return;
}
```

`AtomicAlphanumericComponent` never needed this fix. Its `#list` template uses `*ngFor="let row of data"` where `data` is a getter that reads `resource[propertyName]` directly on every render cycle. There is no signal and no initialisation requirement.

### Summary table

| Aspect | AtomicAlphanumeric | AtomicObject |
|--------|-------------------|-------------|
| Input control | `<input type="text">` | `<p-dropdown>` |
| Selection source | Free text | Existing objects in DB |
| Sub-interface link | No | Yes |
| Create control | Type in add-input | Explicit plus button |
| Data source in template | `data` getter (direct) | `selection` signal |
| Signal initialisation required | No | Yes (fixed May 2026) |
| `CRu+UNI` add-input | Shows (known bug) | Suppressed (correct) |

## Related documentation

**[BOX Template Architecture](box-template-architecture)** — Explains the template variable substitution system (`$name$`, `$crud$`, etc.), the Mustache template files, and when to create a custom template versus using an existing one.

**[BOX Template Development Guide](../guides/box-template-development-guide)** — Step-by-step guide to creating a new BOX template: the Mustache template file, the Angular component, module registration, and testing with a test project.

**[Data Flow Analysis](data_flow_analysis)** — Traces a single field from the ADL interface definition through template processing, runtime data creation, and DOM rendering. Includes the CRUD decision tree, the complete user-input-to-database journey, and property name encoding rules.

**[Built-in BOX Templates](built-in-box-templates)** — Reference for PROPBUTTON and other built-in templates, including required field names and action types.



