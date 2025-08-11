# Ampersand Datamodel Generatie Recept

**Doel:** Genereren van een visueel datamodel (SVG) van Ampersand script structuur  
**Datum toegevoegd:** 1 augustus 2025

## 4-Stappen Recept

### Stap 1: Script Validatie
```bash
ampersand check project/main.adl
```
**Doel:** Zorg dat het Ampersand script syntactisch correct is en vertaalbaar

### Stap 2: Datamodel Generatie  
```bash
ampersand documentation --datamodelOnly project/main.adl
```
**Resultaat:** Genereert `LogicalDataModel_Grouped_By_Pattern.gv` bestand
**Doel:** Creëert GraphViz representatie van logische datamodel

### Stap 3: SVG Conversie
```bash
# Conversie van .gv naar .svg (gebruik dot, niet pandoc)
dot -Tsvg LogicalDataModel_Grouped_By_Pattern.gv -o images/Landeneisen.svg
```
**Resultaat:** SVG bestand in images/ directory
**Doel:** Maakt browser-viewable vector graphics van datamodel

### Stap 4: Browser Visualisatie
```bash
# Open SVG in Firefox
firefox images/Landeneisen.svg
# of via bestandspad:
open -a Firefox images/Landeneisen.svg
```
**Doel:** Toon visueel datamodel voor review en validatie

## Belangrijke Notities

- **Script locatie:** Altijd project/main.adl als startpunt
- **Output locatie:** images/ directory voor SVG bestanden  
- **Browser keuze:** Firefox voor beste SVG ondersteuning
- **Volgorde belangrijk:** Validatie eerst, dan generatie, dan conversie, dan visualisatie

## Troubleshooting Tips

- Als ampersand check faalt: Los syntax errors op eerst
- Als .gv bestand niet wordt gegenereerd: Check of main.adl alle INCLUDE statements correct heeft
- Als SVG niet goed toont: Probeer andere browsers of GraphViz parameters

## Variaties

- Voor andere output formaten: `dot -Tpng`, `dot -Tpdf`, etc.
- Voor specifieke patterns: Gebruik pattern-specifieke ADL bestanden
- Voor deployment: Integreer in CI/CD pipeline voor automatische documentatie updates

## Volledige Workflow Voorbeeld

```bash
# Stap 1: Valideer
ampersand check

# Stap 2: Genereer datamodel
ampersand documentation --datamodelOnly project/main.adl

# Stap 3: Converteer naar SVG  
dot -Tsvg LogicalDataModel_Grouped_By_Pattern.gv -o images/Landeneisen.svg

# Stap 4: Open in browser
firefox images/Landeneisen.svg
```

Dit recept werkt voor alle Ampersand projecten en genereert altijd een up-to-date visuele representatie van het datamodel.
