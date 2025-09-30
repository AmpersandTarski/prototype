# PROPBUTTON Template

The PROPBUTTON template creates interactive buttons that modify boolean properties in Ampersand interfaces.

## How PROPBUTTON Works

PROPBUTTON displays buttons that change boolean property values when clicked. Each button operates on a single boolean property and supports three actions: toggle, set, or clear.

## Template Structure

### In Ampersand Scripts

```ampersand
INTERFACE TaskManager : "_SESSION" ; V[SESSION*Task] cRud BOX<FORM>
  [ "Task Name" : taskName cRud
  , "Mark Complete" : I cRud BOX<PROPBUTTON>
      [ "label" : TXT "Complete Task"
      , "property" : isCompleted cRUd
      ]
  ]
```

### Generated Frontend Code

The Ampersand compiler generates this Angular component:

```html
<app-box-prop-button
    crud="cRud"
    action="toggle"
    [interfaceComponent]="this"
    propertyName="isCompleted"
    [data]="[resource.isCompleted]"
    tgtResourceType="Task"
    isUni
>
</app-box-prop-button>
```

## Prescribed Field Names

PROPBUTTON uses two prescribed field names that must match exactly:

| Field Name | Required | Type | Description |
|------------|----------|------|-------------|
| `"label"` | Yes | Text expression | Button text displayed to users |
| `"property"` | Yes | Boolean property relation | Relation to modify when button is clicked |

## Action Types

The template supports three action types:

- **toggle** (default): Switches property between true and false
- **set**: Always sets property to true
- **clear**: Always sets property to false

### Action Examples

```ampersand
-- Toggle action (default)
BOX<PROPBUTTON>
  [ "label" : TXT "Toggle Status"
  , "property" : isActive cRUd
  ]

-- Set action (always true)
BOX<PROPBUTTON action="set">
  [ "label" : TXT "Activate"
  , "property" : isActive cRUd
  ]

-- Clear action (always false)
BOX<PROPBUTTON action="clear">
  [ "label" : TXT "Deactivate"
  , "property" : isActive cRUd
  ]
```

## Requirements

### Property Relation Constraints

The property relation must be:
- Boolean type (represented as `[PROP]` in Ampersand)
- Properly declared in your script

```ampersand
RELATION isCompleted[Task*Task] [PROP]
RELATION isActive[Project*Project] [PROP]
```

### CRUD Permissions

The property field requires Update permission (capital U) to allow modifications:

```ampersand
"property" : isCompleted cRUd  -- Correct: includes U for update
"property" : isCompleted cRud  -- Wrong: no update permission
```

## Implementation Details

### Component Behavior

The component creates buttons for each data item:
- Button label comes from the label expression
- Clicking triggers the specified action on the property
- Changes are sent to the backend via PATCH requests
- UI updates automatically when the backend confirms changes

### TypeScript Interface

The component expects data items with this structure:

```typescript
type PropButtonItem = ObjectBase & {
  label: string;
  property: boolean;
};
```

## Common Use Cases

### Task Completion

```ampersand
RELATION taskName[Task*TaskName] [UNI,TOT]
RELATION isCompleted[Task*Task] [PROP]

INTERFACE TaskList : "_SESSION" ; V[SESSION*Task] cRud BOX<TABLE>
  [ "Task" : taskName cRud
  , "Complete" : I cRud BOX<PROPBUTTON>
      [ "label" : TXT "Mark Done"
      , "property" : isCompleted cRUd
      ]
  ]
```

### Project Status Management

```ampersand
RELATION projectName[Project*ProjectName] [UNI,TOT]
RELATION isActive[Project*Project] [PROP]

INTERFACE ProjectControl : I[Project] cRud BOX<FORM>
  [ "Project" : projectName cRud
  , "Activate" : I cRud BOX<PROPBUTTON action="set">
      [ "label" : TXT "Start Project"
      , "property" : isActive cRUd
      ]
  , "Deactivate" : I cRud BOX<PROPBUTTON action="clear">
      [ "label" : TXT "Stop Project"
      , "property" : isActive cRUd
      ]
  ]
```

## Troubleshooting

### Button Not Responding

Check CRUD permissions on the property field:
```ampersand
-- This works:
"property" : isCompleted cRUd

-- This doesn't work:
"property" : isCompleted cRud
```

### Property Relation Errors

Ensure the property relation is declared with `[PROP]`:
```ampersand
RELATION isCompleted[Task*Task] [PROP]  -- Correct
RELATION isCompleted[Task*Status]       -- Wrong: not a property
```

### Compilation Errors

Verify prescribed field names match exactly:
```ampersand
-- Correct:
[ "label" : TXT "Complete"
, "property" : isCompleted cRUd
]

-- Wrong field names:
[ "text" : TXT "Complete"      -- Should be "label"
, "prop" : isCompleted cRUd    -- Should be "property"
]
```

## Limitations

PROPBUTTON has these constraints:
- Works only with boolean properties
- Supports one property per button
- No visual customization options
- No conditional visibility controls
- Button styling uses default PrimeNG appearance
