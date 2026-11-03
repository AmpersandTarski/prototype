# box-guarded-edit — regression vehicle

**Guards:** rights that differ per atom: a box whose term selects atoms offers its U and D for those atoms only.
The box is empty for any other atom, and an update or a deletion through it does not reach that atom.

**Origin:** the Artefactenkaart, where an artefact whose home is the map has to be editable there,
and an artefact whose home is a file must not be (Werkplaats#148).

**Run:** `test/run-regression.sh box-guarded-edit`
