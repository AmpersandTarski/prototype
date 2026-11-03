# door-access — regression vehicle

**Guards:** access from every allowed role under the door (OK-34): with `rbac.accessibleInterfacesIfcId` naming the interface `Access`, a session with two roles opens the interfaces of both without switching its active role, the role-less interfaces (Any) open for every allowed role except Anonymous and Stampreporter, and a machine token with only Stampreporter opens its own interface and nothing role-less.

**Origin:** the kern of the Artefactenkaart needs a machine role that reaches one interface (Werkplaats#132), while `sessionActiveRoles` is UNI and a role-less interface opens for every authenticated role.

**Run:** `test/run-regression.sh door-access`.
`regression.vars` switches the door on and names `Access`; the spec plays the identity service on port 9473 of the host, as in `test/projects/door`.
`test/projects/door-access-off` compiles the same model with the door off.

**Green means:** no header opens AnonIfc only; the header `Editor` opens PublicIfc, EditorIfc and AnonIfc; `Editor,Reader` opens PublicIfc, EditorIfc, ReaderIfc and AnonIfc; a token with `Stampreporter` opens StampIfc and AnonIfc; a token with `Reader` opens PublicIfc, ReaderIfc and AnonIfc.
