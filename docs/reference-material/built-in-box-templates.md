# Built-in BOX Templates

The prototype framework ships four general-purpose BOX templates — `TABLE`,
`FORM`, `TABS` and `RAW` — plus the special-purpose `PROPBUTTON`. Each
general-purpose template accepts a set of **annotations** in its BOX header that
tune how the box is rendered. This page documents those annotations first, then
the `PROPBUTTON` template.

## BOX-template annotations

Annotations are written as keywords (and occasionally `key="value"` pairs) inside
the angle brackets of a BOX header:

```ampersand
"Categories" : productCategory cRud BOX<TABLE title="Catalog" noHeader>
  [ ... ]
```

The Ampersand compiler passes every BOX-header annotation to the matching
template generically (see
[box-template-architecture.md](box-template-architecture.md)), so no annotation
needs special compiler support — a template simply reads the ones it understands
and ignores the rest.

### Annotation matrix

| Annotation | TABLE | FORM | TABS | RAW | Value | Effect |
| --- | :---: | :---: | :---: | :---: | --- | --- |
| `title` | ✅ | ✅ | ✅ | — | string | Renders a title/description line above the box content. |
| `hideOnNoRecords` | ✅ | ✅ | ✅ | — | flag | Hides the whole box (including add-controls) when it has no records. |
| `hideSubOnNoRecords` | — | ✅ | ✅ | — | flag | Hides an individual field row (FORM) or tab panel (TABS) when that sub-field has no records. |
| `noHeader` | ✅ | — | — | — | flag | Suppresses the column-header row. |
| `hideLabels` | — | ✅ | — | — | flag | Renders fields full-width without their labels. |
| `showNavMenu` | ✅ | ✅ | — | — | flag | Adds a navigation menu (links to other interfaces) per record. |
| `sortable` | ✅ | — | — | — | flag | Makes column headers clickable to sort. Combine with `sortBy` / `order`. |
| `sortBy` | ✅ | — | — | — | string | Default sort column (a sub-interface label). Use with `sortable`. |
| `order` | ✅ | — | — | — | `asc`/`desc` | Default sort direction. Use with `sortBy`. |
| `table` | — | — | — | ✅ | flag | Lays each record's fields out as an HTML table row. |
| `form` | — | — | — | ✅ | flag | Wraps each record in a non-submitting `<form>` element. |

> **Not yet available — `noRootTitle`.** Historically a root interface box could
> suppress its automatic interface heading with `noRootTitle`. That heading is
> rendered by the per-interface `component.html` template, which the compiler
> renders *without* the box header's key/values, so the annotation cannot reach
> it from the framework alone. Restoring `noRootTitle` requires a small compiler
> change (pass the root box's `noRootTitle` into the `component.html` render
> context in `genComponentFileFromTemplate`). Until then, use `title` to add an
> explicit box title.

### `title`

`title="..."` renders a free-text line above the box content. It works on
`TABLE`, `FORM` and `TABS`. The value is a string, so it must be quoted.

```ampersand
"Categories" : productCategory cRud BOX<TABLE title="All categories">
  [ "Name" : catName cRud ]
```

### `hideOnNoRecords`

When set, the entire box is removed from the page while it has no records,
including its title and add-controls.

```ampersand
"Notes" : itemNote cRud BOX<TABLE hideOnNoRecords>
  [ "Note" : noteText cRud ]
```

> **Warning.** Because the add-controls disappear together with the box, the user
> cannot create the *first* record through this box. Only use `hideOnNoRecords`
> where records are created elsewhere, or where an empty box is genuinely
> irrelevant to the user.

### `hideSubOnNoRecords`

Hides the parts *inside* a box that have no data, keeping the box itself visible:

- In a **FORM**, an empty field row is hidden.
- In **TABS**, an empty tab panel is hidden (so the tab disappears from the bar).

```ampersand
"Category" : I[Category] cRud BOX<TABS hideSubOnNoRecords>
  [ "Name"     : catName     cRud
  , "Products" : catProducts cRud BOX<TABLE>[ "Code" : code cRud ]
  ]
```

A category with no products shows only the *Name* tab. In TABS this is
implemented by filtering the list of tab panels per record, which avoids the
PrimeNG tab-index problems that a structural `*ngIf` on a tab panel would cause.

> **Warning.** A hidden field/tab cannot be edited through this box. Make sure the
> underlying data is reachable through another interface when it matters.

### `noHeader` (TABLE)

Suppresses the column-header row of a table. Because the in-header "add" control
is suppressed too, the table offers a separate "New …" button instead, which also
works while the table is still empty.

```ampersand
"Rows" : someRows cRud BOX<TABLE noHeader>
  [ "Col 1" : c1 cRud, "Col 2" : c2 cRud ]
```

### `hideLabels` (FORM)

Renders each field full-width without its label. Useful for compact, data-dense
forms where the values are self-explanatory.

```ampersand
"Address" : I cRud BOX<FORM hideLabels>
  [ "Street" : street cRud, "City" : city cRud ]
```

> **Accessibility.** Hiding labels removes textual context for screen readers.
> Prefer it only where the meaning of each value is obvious from its content.

### `showNavMenu` (TABLE, FORM)

Adds a per-record navigation menu that links to the other interfaces able to
display that record. It reuses the framework's `_ifcs_` navigation data, which is
included in interface reads by default, so no extra configuration is needed.

```ampersand
"Categories" : V[SESSION*Category] cRud BOX<TABLE showNavMenu>
  [ "Name" : catName cRud ]
```

### `table` and `form` (RAW)

`RAW` is an unstyled escape hatch. Two annotations change how each record's fields
are wrapped:

- `table` lays the fields out as a single HTML table row (`<tr><td>…</td></tr>`).
  The sub-expressions must produce content valid inside a `<td>`.
- `form` wraps the fields in a `<form onsubmit="return false">` element. The form
  never submits, so it adds no behaviour of its own — it is only a semantic
  container for styling or grouping.

```ampersand
"Plain" : I cRud BOX<RAW table>[ "A" : a cRud, "B" : b cRud ]
```

These are expert features; prefer `TABLE`/`FORM` for normal use.

## BOX \<PROPBUTTON\>

The `BOX <PROPBUTTON>` template creates interactive buttons for toggling boolean properties on atoms. Users click these buttons to set, clear, or toggle property values.

### When to use PROPBUTTON

Use PROPBUTTON for boolean properties like on/off, yes/no, or active/inactive states. The template works well when you want users to change a property with one click rather than typing or selecting from dropdowns. Consider PROPBUTTON for status toggles, approval workflows, or feature flags.

Do not use PROPBUTTON for properties with more than two states. Complex validation logic before state changes makes regular fields more suitable. Relations that are not univalent will cause compilation errors.

Alternatives include regular fields with checkboxes (`property : relation cRUD`), dropdown selection for limited target sets, radio buttons for exclusive boolean relations, or text fields for non-boolean properties.

### Simple Example

This example creates a toggle for marking tasks as completed:

```ampersand
CONTEXT TaskManagement

RELATION taskName [Task*TaskName] [UNI,TOT]
RELATION isCompleted [Task] [PROP,UNI]

INTERFACE TaskList : "_SESSION" ; V[SESSION*Task] cRud
BOX <TABLE>
[ "Task Name" : taskName cRud
, "Status" : I cRud BOX <PROPBUTTON>
  [ "label" : TXT "Mark Complete"
  , "property" : isCompleted cRUd
  ]
]
```

The property relation must have both `[PROP,UNI]` constraints for PROPBUTTON to work. Use the prescribed field names `"label"` and `"property"` exactly as shown, including the quotes. The property expression should reference the relation itself, not an I-expression. The CRUD annotation on the property must allow Update (capital U) for toggling to function.

### Complete Reference

The syntax structure follows this pattern:

```ampersand
<fieldname> : <term> <crud>? BOX <PROPBUTTON>
[ "label" : <labelExpression>
, "property" : <propertyRelation> <crud>
, "popovertext" : <tooltipExpression>
, "color" : <colorExpression>
, "action" : <actionExpression>
]
```

PROPBUTTON requires specific field names. The `"label"` field contains the text displayed on the button. The `"property"` field specifies the boolean property to toggle and must reference a `[PROP,UNI]` relation. Optional fields include `"popovertext"` for tooltip text, `"color"` for button color using CSS color values, and `"action"` for button behavior.

Three action types control button behavior. Toggle mode (default) switches between true and false on each click. Set mode always changes the property to true. Clear mode always changes the property to false.

```ampersand
"action" : TXT "toggle"  -- Switches between true/false
"action" : TXT "set"     -- Always sets to true
"action" : TXT "clear"   -- Always sets to false
```

### Advanced Example

This project management interface demonstrates multiple PROPBUTTON instances:

```ampersand
CONTEXT ProjectManagement

RELATION projectName [Project*ProjectName] [UNI,TOT]
RELATION isActive [Project] [PROP,UNI]
RELATION isArchived [Project] [PROP,UNI]
RELATION priority [Project*Priority] [UNI]

INTERFACE ProjectDashboard : "_SESSION" ; V[SESSION*Project] cRud
BOX <TABLE>
[ "Project" : projectName cRud
, "Priority" : priority cRud
, "Active" : I cRud BOX <PROPBUTTON>
  [ "label" : TXT "Toggle Active"
  , "property" : isActive cRUd
  , "popovertext" : TXT "Click to activate/deactivate this project"
  , "color" : TXT "#28a745"
  , "action" : TXT "toggle"
  ]
, "Archive" : I cRud BOX <PROPBUTTON>
  [ "label" : TXT "Archive"
  , "property" : isArchived cRUd
  , "popovertext" : TXT "Archive this project"
  , "color" : TXT "#dc3545"
  , "action" : TXT "set"
  ]
]
```

### Common Issues

TypeScript compilation errors about property type mismatch occur when the property relation lacks the `[UNI]` constraint. The error message `Type 'Object & { _view_: ...; }[]' is not assignable to type 'PropButtonItem'` indicates this problem. Add both `[PROP,UNI]` constraints to fix it.

Buttons that do not respond to clicks usually have CRUD annotations that prevent updates. Check that the property's CRUD annotation includes a capital U for Update permission.

Interface generation errors often stem from incorrect field names. Use `"label"` and `"property"` exactly as written, including the quotes. Misspellings or variations will break the interface.

Multiple buttons interfering with each other suggests they share the same property relation. Create separate relations for different buttons to avoid conflicts.

### Integration

PROPBUTTON combines with other interface elements in the same interface:

```ampersand
INTERFACE ItemManager : "_SESSION" ; V[SESSION*Item] cRud
BOX <TABLE>
[ "Name" : itemName cRud
, "Description" : description cRud
, "Actions" : I cRud BOX <FORM>
  [ "Active" : I cRud BOX <PROPBUTTON>
    [ "label" : TXT "Active"
    , "property" : isActive cRUd
    ]
  , "Priority" : priority cRUD
  , "Tags" : tags cRUD
  ]
]
```

This creates an interface mixing PROPBUTTON with regular fields for different types of user interactions.

## BOX \<FILTEREDDROPDOWN\>

`BOX <FILTEREDDROPDOWN>` populates a relation from a **server-filtered** list of
selectable atoms. The set of options is computed per record by an Ampersand
expression, so each row can offer a different, context-dependent choice list.

> This template supersedes the older `OBJECTDROPDOWN` and `VALUEDROPDOWN`
> templates. A single `FILTEREDDROPDOWN` works for both object concepts and value
> concepts (those with a `REPRESENT`).

### Usage

```ampersand
expr cRud BOX <FILTEREDDROPDOWN>
  [ "selectFrom"  : selectableAtoms      -- options to choose from (per record)
  , "setRelation" : theRelation <crud>   -- the relation that is filled/changed
  ]
```

Both field names are prescribed and must be written exactly, including the
quotes:

| Field | Meaning |
| --- | --- |
| `selectFrom` | Expression yielding the atoms the user may pick. It is filtered on the server for the current record, so it can depend on the record (e.g. only *eligible* employees for *this* project). |
| `setRelation` | The relation whose population is read and modified when the user picks (or clears) a value. Its CRUD annotation governs what the user may do. |

`selectFrom` and `setRelation` must have the **same target concept**; a mismatch
raises an error at runtime.

### CRUD on `setRelation`

The CRUD letters on `setRelation` decide which actions the widget offers (see the
[CRUD reference](interfaces.md#CRUD) for the general meaning):

- **R** (read) is required for the box to display the current value.
- **U** (update) lets the user replace/extend the value by selecting an option.
- **D** (delete) shows a remove/clear control.
- **C** (create) lets the user create a new atom by typing a value that is not yet
  in the list (only meaningful where a new atom makes sense).

For a `[UNI]` `setRelation` a selection replaces the current value; for a
non-univalent relation the user builds a list. A `[TOT]` relation hides the
remove control when removing the last value would violate totality.

### Example

```ampersand
"Assign an employee" : I[Project] cRud BOX<FILTEREDDROPDOWN>
  [ "selectFrom"  : eligible        -- eligible employees for this project
  , "setRelation" : projectMember cRUd
  ]
```

(See `test/projects/box-filtered-dropdown` for worked examples covering UNI, TOT
and full-CRUD variants.)

## BOX \<SELECT\>

`BOX <SELECT>` is a simpler dropdown: it selects from a **static** option list
that is already present on the record, without per-record server filtering and
without creating new atoms from typed text.

### Usage

```ampersand
expr cRud BOX <SELECT>
  [ "selectFrom" : selectableAtoms   -- options, embedded on the record
  ]
```

Use `SELECT` when the option list is the same for every record and is part of the
interface data. Use `FILTEREDDROPDOWN` when the options must be filtered per
record or when the user should be able to create new atoms.

## BOX \<NOVIEW\>

`BOX <NOVIEW>` renders nothing. Use it to keep a sub-interface in the interface
definition (for example, because the backend or a rule needs the field to be
read) while hiding it from the user interface.

```ampersand
"hiddenField" : someRelation cRud BOX <NOVIEW> []
```

## Atomic templates (interface leaves)

A leaf of an interface — a sub-interface with no further `BOX` and no user
`VIEW` — is rendered by an **atomic template**, chosen by the compiler from the
target concept's `REPRESENT` type. The framework ships one per TType:

| TType | Atomic template |
| --- | --- |
| `ALPHANUMERIC` | `Atomic-ALPHANUMERIC` |
| `BIGALPHANUMERIC` | `Atomic-BIGALPHANUMERIC` |
| `HUGEALPHANUMERIC` | `Atomic-HUGEALPHANUMERIC` |
| `BOOLEAN` | `Atomic-BOOLEAN` |
| `DATE` | `Atomic-DATE` |
| `DATETIME` | `Atomic-DATETIME` |
| `INTEGER` | `Atomic-INTEGER` |
| `FLOAT` | `Atomic-FLOAT` |
| `PASSWORD` | `Atomic-PASSWORD` |
| `OBJECT` | `Atomic-OBJECT` (a concept without a `REPRESENT`) |
| `TYPEOFONE` | `Atomic-TYPEOFONE` (the singleton `ONE` atom; rarely used in a UI) |

The Angular components behind these templates, and how the type mapping works, are
described in [Frontend components](frontend-components.md).
