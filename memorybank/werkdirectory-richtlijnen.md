# WERKDIRECTORY RICHTLIJNEN

## Vaste Werkdirectory
- **Regel**: Altijd blijven werken vanuit de hoofddirectory. Dat is de directory waarmee VS-code geopend is.
- **Geen `cd` commando's gebruiken** naar andere directories

## Commando Uitvoering
- Alle commando's uitvoeren vanuit de hoofddirectory
- Voor subdirectories gebruik relatieve paden: `project/`, `memorybank/`, `images/`, etc.
- Bijvoorbeeld: `ampersand check project/main.adl` in plaats van `cd project && ampersand check main.adl`

## Redenen
- Consistentie in werkwijze
- Voorkomt verwarring over huidige locatie
- Behoudt overzicht van projectstructuur
- Vermijdt problemen met relatieve padverwijzingen

## Voorbeelden Correcte Commando's
```bash
# Correct - vanuit hoofddirectory
ampersand check project/main.adl
dot -Tsvg images/LogicalDataModel.gv -o images/LogicalDataModel.svg
python create_steekproef_excel.py

# Incorrect - niet meer gebruiken
cd project && ampersand check main.adl
cd images && dot -Tsvg LogicalDataModel.gv -o LogicalDataModel.svg
```

**Datum vastgesteld**: 1 augustus 2025
**Status**: Actief - altijd toepassen
