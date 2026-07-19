# eisen-dekkingen-import — NVWA CLE "eisen en dekkingen" als populatie via een Excel-interface

**Guards:** een Ampersand-script (concepten + relaties + 7 SESSION-interfaces) leest de NVWA-CLE
export "eisen en dekkingen" (platte join ESE ⋈ Organisme ⋈ Kenmerk ⋈ Dekking) als populatie via
de Excel-INTERFACE-route, waarbij 100% van de paren correct wordt opgeslagen — compile-time
(Haskell `INCLUDE`) én runtime (`/admin/import`), geen enkel gegeven overgeslagen (SPEC.md).

Zie [SPEC.md](SPEC.md) voor het volledige ontwerp: bronschema, het ont-kruisen van de
gedenormaliseerde join, het faithful relationele model, en de metric.

## Structuur

- `model/main.adl` — concepten + relaties + één SESSION-import-interface per worksheet-concept
  (ESE, Product, Land, Organisme, Verklaring, Kenmerk, Dekking). `INCLUDE "populatie.xlsx"` laadt
  de compile-partitie via de Haskell-importer.
- `model/populatie.xlsx` — compile-partitie van het staal (interface-formaat), door de normalizer
  geschreven.
- `tools/make_sample.py` — kiest een klein, heterogeen staal (61 ESE) dat alle schemavarianten
  dekt; dumpt de ruwe rijen naar `e2e/sample-raw.json`.
- `tools/normalize.py` — de dunne, tijdelijke normalizer: ont-kruist de join en schrijft het
  interface-xlsx (compile- + runtime-partitie + `partition.json`).
- `tools/build-sample.sh` — regenereert alle gecommitte artefacten uit de ruwe FC5-bronnen.
- `e2e/verify.mjs` — de verify: leidt de VERWACHTE paren onafhankelijk uit `sample-raw.json` af,
  installeert (compile-route), uploadt `runtime.xlsx` (runtime-route), exporteert, reconstrueert
  en vergelijkt. Print `SCORE: <n>%`; exit 0 = 100% op beide routes.

## Draaien

```bash
# vanuit de repo-root
test/run-regression.sh eisen-dekkingen-import
```

De runner compileert `model/main.adl` (leest de compile-partitie via `INCLUDE`), installeert, en
draait `e2e/verify.mjs`. De autoresearch itereert `model/` + `tools/normalize.py` tot `SCORE`
100% is op het staal; daarna verbreden naar alle 6 bestanden (`tools/normalize.py` op de volledige
bron).
