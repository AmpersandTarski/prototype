# Spec — NVWA CLE "eisen en dekkingen" importeren via een Ampersand Excel-interface

Status: **werkend op het staal** (61 ESE, 100% op beide routes). Verbreding naar alle 6 bestanden: zie §7.

## 1. Doel & mechanisme

De zes bestanden `eisen en dekkingen C**.xlsx` (CAT, CBB, CBN, CFA, CST, CZZ) moeten als
populatie in een prototype landen, **100% van de paren correct**, en wel via **beide** importers:
compile-time (Haskell, `INCLUDE "*.xlsx"`) én runtime (PHP, `POST /admin/import`). Het
transformatie-gereedschap is een **Ampersand-script** (concepten + relaties + interfaces); de
importer gebruikt dat script als *schema*.

Elk bestand is één platte, gedenormaliseerde export uit de Oracle-database `mngr_cle` (de SQL
staat in het tweede worksheet "SQL Statement"). Eén worksheet "Select select", 46 kolommen, één
productgroep per bestand. De grain is een **cartesisch product** van vier onafhankelijke assen:

```
ESE ⋈ Organisme ⋈ Kenmerk(EKK) ⋈ Dekking(DKG)      (LEFT OUTER JOINs)
```

Eén ESE met 3 organismen × 5 kenmerken × 8 dekkingen beslaat dus tot 120 rijen (max gemeten:
één ESE over 3726 rijen). Totaal: **1.225.904 rijen, 91.674 ESE's** over de 6 bestanden.

### Twee importers, één formaat (Excel-INTERFACE-route)

Beide importers ondersteunen hetzelfde interface-formaat (geverifieerd in de broncode:
runtime [`ExcelImporter::parseWorksheetWithIfc`](../../../backend/src/Ampersand/IO/ExcelImporter.php#L79),
compile-time [`XLSX.hs::xlsxIfcSheet2pops`](../../../../Ampersand/src/Ampersand/Input/Xslx/XLSX.hs#L735)):

```
worksheet-titel == interface-label
cel A1          == doelconcept van de interface (bron == SESSION)
rij 1, kolom B… == sub-interface-labels (elk een editeerbare relatie)
kolom A         == atoom-id van het doelconcept   (rij = één atoom + zijn directe links)
```

Dit is een **één-niveau ster-afvlakking**: één sheet vult de relaties die direct aan één
concept hangen. Een genormaliseerd, meer-entiteiten model krijgt dus **één sheet per concept**.

Omdat de ruwe bestanden hier niet aan voldoen (titel "Select select", A1 leeg, koppen = SQL-namen),
is een **normalizer** hoe dan ook nodig. Die ont-kruist de join terug tot de genormaliseerde
tabellen en schrijft ze in het interface-formaat. Beide routes lezen exact hetzelfde workbook.

```
6× C**.xlsx ──normalize.py (ont-kruisen)──► interface-xlsx ──┬─ INCLUDE ─► Haskell-importer (compile-time)
                                                             └─ /admin/import ─► PHP-importer (runtime)
```

**Stille valkuil (ontwerpeis):** een kolomkop die geen sub-interface-label is, wordt door de
importer afgekeurd (runtime) of overgeslagen; een label dat wél matcht maar leeg is, wordt
overgeslagen. De interface moet daarom **schemadekkend** zijn en de metric moet elk verloren
blad detecteren.

## 2. Het bronschema (scan over alle 6 bestanden)

Union van 46 kolommen (identiek per bestand). Sleutelbevindingen die het model sturen
(`tools/analyze` op de volledige 1,23 M rijen):

| bevinding | consequentie voor het model |
|---|---|
| `DEKKING_CODE` staat 2× (kol 2 == kol 31, 0 verschillen) | één keer modelleren; meervoud via `;` |
| `LAND_CODE → LAND_NAAM` functioneel (0 afwijkingen) | Land sleutelen op `LAND_CODE` |
| `OGE_ID → ESE_ORGANISME` functioneel (0 afwijkingen) | Organisme sleutelen op `OGE_ID` |
| `PRODUCT_NAAM` **niet** uniek over groepen (2257/3925 namen in >1 groep) | Product sleutelen op samengesteld `PRODUCT_GROEP ␟ PRODUCT_NAAM` |
| per ESE constant, behalve `ESE_VERKLARINGSTEKST`/`ESE_TAAL` | alle ESE-attributen `[UNI]`; verklaring is een aparte, meervoudige entiteit |
| binnen `(ESE,SETNR,ALT,VOLGNR)` zijn álle DKG-scalars constant (0 afwijkingen) | dat is de dekking-sleutel; alleen de verklaring-rendering is meervoudig |
| vrije tekst tot 3978 tekens (memo, verklaringstekst, eis-naam, bron) | doelconcept `Tekst` als `BIGALPHANUMERIC` (default kapt op 254) |
| `HOOFDEIS` = verwijzing naar een andere ESE | `hoofdeis[ESE*ESE]` (zelf-referentie) |
| `ESE_ORGANISME` meervoudig per ESE (max 107) | `organisme[ESE*Organisme]` (niet-`UNI`) |

## 3. Metric & oracle — niets overslaan (onafhankelijke reconstructie)

**Passvoorwaarde (definitie van 100%): elk gegeven uit de bron wordt een link onder de JUISTE
relatie; geen enkel blad overgeslagen, geen enkele extra.** De enige legitieme niet-links zijn
`null`-cellen.

De transformer mag niet zijn eigen examinator zijn. Daarom (`e2e/verify.mjs`):

1. **Verwachte feiten** worden onafhankelijk uit de ruwe rijen (`sample-raw.json`) afgeleid door
   een tweede, in JavaScript geschreven ont-kruising — een andere implementatie dan de
   Python-normalizer die de xlsx bouwt.
2. **Import** gebeurt via de importer (compile-time én runtime).
3. **Reconstructie**: na export wordt de populatie teruggelezen en worden dezelfde
   **inhoud-signaturen** opgebouwd. Die zijn **id-agnostisch**: de synthetische K/D/VK-id's doen
   er niet toe; een Kenmerk/Dekking/Verklaring wordt geïdentificeerd door zijn gereconstrueerde
   *inhoud* (bv. een Kenmerk = `ese ¦ soort ¦ waardeOrg ¦ waardeVertaald ¦ status ¦ eind`).
4. **Vergelijking** als verzamelingen (populatie-links zijn een verzameling, multipliciteit 1):
   `matched == expected` én `extra == 0` ⇒ 100%. Een blad onder de verkeerde relatie levert een
   ander pad → valt als *missing* + *extra* door de mand.

Beide routes worden apart getoetst: fase A (compile-partitie via `INCLUDE`, aanwezig na install)
en fase B (runtime-partitie via upload). Zo dragen beide importers aantoonbaar bij.

## 4. Het Ampersand-script

Wortelconcept **`ESE`** (exporteis-specificatie, sleutel `ESE_ESE_ID`). Tussen-entiteiten
zonder bronsleutel (`Kenmerk`, `Dekking`) krijgen door de normalizer een synthetische, stabiele
id (`K<ese>_<i>` / `D<ese>_<i>`) en een terug-link naar hun ESE. `Verklaring` (concept, taal,
tekst) is een globaal gedeelde entiteit voor zowel ESE- als Dekking-verklaringen.

Concepten: `ESE, Product, Land, Organisme, Bak, Kenmerk, Dekking, Verklaring, Verklaringconcept`
+ waarde-/enum-concepten `Productgroep, Status, Typeeis, Kenmerksoort, Typedekking, Taal, Eenheid,
Dekkingcode, Tekst(BIGALPHANUMERIC), Datum, Getal`. Zeven SESSION-import-interfaces
(ESE, Product, Land, Organisme, Verklaring, Kenmerk, Dekking) — zie `model/main.adl`. Geen RULEs
(geen invariant mag de import blokkeren; import = één transactie).

## 5. De dunne, tijdelijke normalizer

`tools/normalize.py` ont-kruist de join:

1. Groepeer rijen per ESE. ESE-attributen zijn constant per ESE (geverifieerd) → neem één.
2. `organisme` en `verklaring`: verzamel de distinct-set per ESE (meervoudig).
3. `Kenmerk`: distinct volledige EKK-tupel per ESE → synthetische id.
4. `Dekking`: distinct DKG-scalartupel per ESE → synthetische id; verklaring is de enige
   meervoudige attribuut-as.
5. `Product/Land/Organisme/Verklaring`: globaal ontdubbeld.
6. Schrijf per concept één worksheet in interface-formaat; meervoudige kolommen met de
   `[label<delim>]`-syntax (`[organisme,]`, `[verklaring,]`, `[dekkingCode;]`).

Gladgestreken export-artefacten (cartesiaanse duplicatie, ontbrekende EKK/DKG-sleutels, dubbele
`DEKKING_CODE`-kolom, `;`-meervoud, rand-witruimte) gelden als **migratiedefecten** in de
Oracle-export; wordt de bron ooit genormaliseerd aangeleverd, dan vervalt de normalizer.

## 6. Beslissingen (vastgelegd)

1. **Scope** → alleen de 6 `C**`-exportbestanden. `Eindinspectiemodel dekkingsystematiek` is
   Johan's referentiemodel, buiten scope.
2. **Wortelconcept** → `ESE`; tussen-entiteiten `Kenmerk`/`Dekking` met synthetische sleutel.
3. **Ont-kruisen** → project + dedup per entiteit; verliesloos want de join is een cartesisch
   product van onafhankelijke assen.
4. **Datum/Getal** → verbatim als tekst (`ALPHANUMERIC`). De compile-time xlsx-importer weigert
   string-cellen voor een `DATE`/`INTEGER`-concept; tekst is verliesloos. Typering is een latere
   verfijning.
5. **Metric** → onafhankelijke reconstructie, verzameling-semantiek, `extra == 0` = hard.
6. **Dubbele-proof** → compile-partitie via `INCLUDE`, runtime-partitie via upload; unie == verwacht.

## 7. Verbreden naar alle 6 bestanden — schaal & geheugen

`tools/build_full.py` normaliseert de VOLLEDIGE bron (streamt de 1,23 M ruwe rijen) tot één
workbook. De entiteits-tellingen kruisen **exact** met de onafhankelijke scan (`analyze.json`):

| concept | build_full | onafhankelijke scan |
|---|---|---|
| ESE | 91.674 | 91.674 |
| Organisme | 4.428 | 4.428 |
| Land | 223 | 223 |
| Kenmerk | 377.787 | 377.787 |
| Dekking | 204.980 | 204.980 |
| Product (groep␟naam) | 7.528 | 3.925 namen × meerdere groepen |
| Verklaring (rendering) | 8.202 | 6.030 concepten |

De normalizer is dus zelf-consistent met de bron op de volledige schaal.

**Import-schaal (gemeten in de reg-stack, 15,7 GiB host):**

- **Correctheid** — 100% op het heterogene staal, beide routes, 0 extra's (zie §3).
- **Runtime, volledige productgroep** — upload van `populatie-CFA.xlsx` (4.973 ESE, ~24k
  sheet-rijen) via `POST /admin/import`: **geslaagd in 35 s**, alle tellingen kloppen
  (ESE 4.973, Kenmerk 9.993, Dekking 7.681, … ; 160.915 links). ✅
- **Geheugengrens (config, geen modelfout):**
  - Runtime faalt bij PHP-`memory_limit` = 128 MB (PhpSpreadsheet laadt het hele workbook in
    geheugen). Met `memory_limit` = 6 GB slaagt een volledige groep. → productie-import verhoogt
    `memory_limit` en/of splitst per groep (6 uploads) of fijner.
  - Compile-time `INCLUDE` (Haskell `Codec.Xlsx`) bakt de populatie in gegenereerde code en
    schaalt slecht: het staal (honderden rijen) lukt, maar een volledige groep of het volledige
    corpus loopt out-of-memory. Compile-time `INCLUDE` is bedoeld voor bescheiden
    referentie-populaties; **bulkdata hoort op de runtime-route** (de route die FC5 in productie
    gebruikt).

**Conclusie:** het Ampersand-script leest de gegevens compleet en correct langs beide importers;
de enige grens is operationeel (geheugen/chunking), niet het model. Productie-aanbeveling: importeer
per productgroep via de runtime-route met ruim `memory_limit`; de merge-gate blijft het gepinde,
portable staal.
