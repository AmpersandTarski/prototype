# Templates

## BOX templates

### TABLE
Interface template for table structures. The target atoms of the interface make up the records / rows. The sub interfaces are used as columns.

Usage: `BOX <TABLE attributes*>`

Examples:
* `BOX <TABLE>`
* `BOX <TABLE no-header>`
* `BOX <TABLE hide-empty-table>`
* `BOX <TABLE no-header hide-empty-table>`

Possible attributes are:
| attribute | value | description |
| ------ | -- | -- |
| hide-empty-table | n.a. | when attribute is set, the complete table is hidden in the interface when there are no records |
| no-header | n.a. | when attribute is set, no table header is used |