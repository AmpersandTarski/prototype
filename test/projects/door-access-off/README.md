# door-access-off — regression vehicle

**Guards:** access from every allowed role with the door off (OK-34): the model of `test/projects/door-access`, with `rbac.accessibleInterfacesIfcId` naming `Access` and no door, opens every interface, whichever role happens to be the one active role.

**Origin:** a prototype with login off allows every role but keeps one active (`sessionActiveRoles` is UNI); on the artefact map of the Werkplaats that role was SYSTEM (measured on 3 November 2026), so an interface `FOR` a role would close there unless access follows the allowed roles.

**Run:** `test/run-regression.sh door-access-off`.

**Green means:** PublicIfc, EditorIfc, ReaderIfc, StampIfc and AnonIfc all open.
