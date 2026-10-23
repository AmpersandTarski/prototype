# specialization-header — regression vehicle

**Guards:** the header of a BOX<FACETS> column named after its target concept names the specialization that every shown row holds, and returns to the label of the modeler when the selection is cleared.
The API lists the specializations of such an atom in `_concepts_`, the most specific first, in all three storage layouts (one wide table, `MULTITABLE`, a union concept); a column with a label of its own gets no `_concepts_`.

**Origin:** DesignChoices OK-29 and Ampersand#1702.
A user who chose the facet Type = Claim in a list of artefacts saw only claims under the header "Artefact", and asked for the header "Claim".

**Run:** `test/run-regression.sh specialization-header`, or by hand: `./generate.sh specialization-header` and open `http://localhost/artefacts`.
