# Templates

## BOX templates

### TABLE
Interface template for table structures. The target atoms of the interface make up the records / rows. The sub interfaces are used as columns.
This templates replaces former templates: `COLS`, `SCOLS`, `HCOLS`, `SHCOLS` and `COLSNL`

Usage: `BOX <TABLE attributes*>`

Examples:
- `BOX <TABLE>`
- `BOX <TABLE noHeader>`
- `BOX <TABLE hideEmptyTable>`
- `BOX <TABLE title="Title of your table">`
- `BOX <TABLE noHeader hideEmptyTable title="Table with title">`

Possible attributes are:
| attribute | value | description |
| --------- | ----- | ----------- |
| hideEmptyTable | n.a. | when attribute is set, the complete table is hidden in the interface when there are no records |
| noHeader | n.a. | when attribute is set, no table header is used |
| title | string | title / description for the table. Title is shown above table |
| sortable | n.a. | makes table headers clickable to support sorting on some property of the data. Only applies to univalent fields |
| sortBy | sub interface label | Add default sorting for given sub interface. Use in combination with 'sortable' |
| order | `desc`, `asc` | Specifies default sorting order. Use in combination with 'sortBy'. Use `desc` for descending, `asc` for ascending |
