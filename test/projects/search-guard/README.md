# search-guard — regression vehicle

**Guards:** that the full-text search module returns only atoms the session may read. A
session without any role must not receive stored values through `GET /api/v1/search`, and a
session that holds the reading role must still find them.

**Origin:** v2.5.2. Until then the module returned every matching atom together with the
value that matched, without checking read rights: an anonymous, not-logged-in session
received the stored values themselves. The module already computed, per result, the
interfaces to open the atom in (`_ifcs_`) and returned results even when that list was empty.

**Run:** `test/run-regression.sh search-guard` (or `test/search-guard/test.sh`, which runs
the two phases against a stack of its own; see `test/search-guard/README.md`)

**Green means:** searching "Vertrouwelijk" returns nothing for an anonymous session, and
returns dossier `d1` carrying interface `Dossiers` for a session with the Caseworker role.

## Model

`dossierNote[Dossier*Note]` is deliberately **non-univalent**, so the compiler stores it in
its own binary table: the search module reads binary tables (it does not currently reach
values in a concept's broad table). `Dossier` is readable through `INTERFACE Dossiers FOR
Caseworker` only, so an anonymous session has no interface to open it in.
