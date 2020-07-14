# Templates
Templates are used to generate prototype user interfaces based on Ampersand INTERFACE definitions.
There are 3 types of templates:
1. Box template -> 
2. Atomic templates -> used for interface leaves nodes (without a user defined VIEW specified)
3. View templates -> used for user defined views

e.g.
```adl
INTERFACE "Project" : I[Project] cRud BOX           <-- the default FORM box template is used
  [ "Name"                : projectName             <-- the default atomic template for a alphanumeric type is used
  , "Description"         : projectDescription
  , "(Planned) start date": projectStartDate 
  , "Active"              : projectActive
  , "Current PL"          : pl <PersonEmail>        <-- a user defined PersonEmail view template is used
  , "Project members"     : member BOX <TABLE>      <-- the built-in TABLE box template is used
    [ "Name"              : personFirstName
    , "Email"             : personEmail
    ]
  ]
```

## BOX templates

### FORM (=default BOX template)
Interface template for forms structures. For each target atom a form is added. The sub interfaces are used as form fields.
This template replaces former templates: `ROWS`, `HROWS`, `HROWSNL` and `ROWSNL`

Usage `BOX <FORM attributes*>`

Examples:
- `BOX <FORM>`
- `BOX <FORM noLabels>`
- `BOX <FORM hideNoRecords>`
- `BOX <FORM title="Title of your form">`
- `BOX <FORM noLabels hideNoForm>`

Possible attributes are:
| attribute | value | description |
| --------- | ----- | ----------- |
| hideNoRecords | n.a. | when attribute is set, the complete form is hidden in the interface when there are no records |
| noLabels | n.a. | when attribute is set, no field labels are shown |
| title | string | title / description for the forms. Title is shown above the form |

### TABLE
Interface template for table structures. The target atoms of the interface make up the records / rows. The sub interfaces are used as columns.
This templates replaces former templates: `COLS`, `SCOLS`, `HCOLS`, `SHCOLS` and `COLSNL`

Usage: `BOX <TABLE attributes*>`

Examples:
- `BOX <TABLE>`
- `BOX <TABLE noHeader>`
- `BOX <TABLE hideNoRecords>`
- `BOX <TABLE title="Title of your table">`
- `BOX <TABLE noHeader hideNoRecords title="Table with title">`

Possible attributes are:
| attribute | value | description |
| --------- | ----- | ----------- |
| hideNoRecords | n.a. | when attribute is set, the complete table is hidden in the interface when there are no records |
| noHeader | n.a. | when attribute is set, no table header is used |
| title | string | title / description for the table. Title is shown above table |
| sortable | n.a. | makes table headers clickable to support sorting on some property of the data. Only applies to univalent fields |
| sortBy | sub interface label | Add default sorting for given sub interface. Use in combination with 'sortable' |
| order | `desc`, `asc` | Specifies default sorting order. Use in combination with 'sortBy'. Use `desc` for descending, `asc` for ascending |

### TABS
Interface template for a form structure with different tabs. For each sub interface a tab is added.
This template is used best in combination with univalent interface expressions (e.g. `INTERFACE "Test" : univalentExpression BOX <TABS>`), because for each target atom of the expression a complete form (with all tabs) is shown.

Usage `BOX <TABS attributes*>`

Example:
- `BOX <TABS>`

Possible attributes are:
| attributes | value | description |
| ---------- | ----- | ----------- |
| *currently there are no attributes for this template*

### RAW
Interface template without any additional styling and without (editing) functionality. Just plain html `<div>` elements
This template replaces former templates: `DIV`, `CDIV` and `RDIV`

Usage: `BOX <RAW attributes*>`

Examples:
- `BOX <RAW>`
- `BOX <RAW table>`

Possible attributes are:
| attribute | value | description |
| --------- | ----- | ----------- |
| table     | n.a.  | uses simple table structure to display data. Similar to `TABLE` template (see below), but without any functionality, header and styling

### PROPBUTTON
Interface template that provides functionality to toggle (set/unset) a property using a button

Usage:
```
expr BOX <PROPBUTTON> 
  [ "property": propertyRelationToToggle -- mandatory; the property that is set/unset when the button is clicked
  , "popovertext": expr -- optional text that is displayed when hovering the button
  , "disabled": expr -- optional; button is disabled (not clickable) when expression evaluates to true
  , "hide": expr -- optional; button is hidden (not shown) when expression evaluates to true
  ]
```

Possible attributes are:
| attribute | value | description |
| --------- | ----- | ----------- |
| *currently there are no attributes for this template*


## Atomic templates (i.e. interface leaves)

### OBJECT

### ALPHANUMERIC, BIGALPHANUMERIC, HUGEALPHANUMERIC

### BOOLEAN

### DATE, DATETIME

### INTEGER, FLOAT

### PASSWORD

### TYPEOFONE
Special interface for singleton 'ONE' atom. This probably is never used in an prototype user interface.


## Built-in VIEW templates

### FILEOBJECT

### LINKTO

### PROPERTY

### STRONG

### URL