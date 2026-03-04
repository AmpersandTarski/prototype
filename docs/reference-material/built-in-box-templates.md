# Built-in BOX Templates

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
