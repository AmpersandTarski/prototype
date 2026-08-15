# Ampersand Interface Architectuur Lessen

**Datum:** 11 augustus 2025  
**Context:** Interface LandeneisOverzicht in project/Plagen.adl  

## Hoofdmenu Interface Patroon

### 🔑 **Buitenste BOX-atoom Regel**
```ampersand
INTERFACE Name : "_SESSION"[SESSION] CRuD BOX<TABS>
```

**ALTIJD `"_SESSION"[SESSION]` voor hoofdmenu interfaces:**
- Interfaces met dit BOX-atoom staan automatisch in het applicatie hoofdmenu
- Het SESSION concept is het startpunt voor alle gebruiker interfaces
- Dit is een vaste conventie in Ampersand

### 🔄 **Recursieve BOX Architectuur**

**Interface rendering proces:**
1. **Buitenste BOX-atoom:** Bijvoorbeeld `"_SESSION"[SESSION]` (1 SESSION atoom)
2. **BOX-term:** Verbindt huidige BOX-atoom met nieuwe BOX-atomen via relatie compositie
3. **Nieuwe BOX-atomen:** Voor elk resultaat atoom wordt een HTML div (doosje) gemaakt
4. **Recursief:** Proces herhaalt zich naar binnen toe per nieuw BOX-atoom
5. **Eindpunt:** Atomen zonder BOX worden afgebeeld volgens TYPE (ALPHANUMERIC, DATE, etc.)

## Compositie Semantiek & Typesysteem

### 🧮 **Relationele Compositie (;) Regel**
```ampersand
-- Als R[A*B] en S[B*C], dan R;S : [A*C]
V[SESSION*LandEis];landeisLand[LandEis*LandCode] : [SESSION*LandCode]
```

**Stap-voor-stap type ontleding:**
1. `V[SESSION*LandEis]` → alle LandEis atomen vanuit SESSION
2. `landeisLand[LandEis*LandCode]` → van LandEis naar bijbehorende LandCode  
3. **Compositie:** `V[SESSION*LandEis];landeisLand : [SESSION*LandCode]`
4. **Resultaat:** Alle unieke LandCode atomen waar landeneisen voor bestaan

### 🎯 **BOX-atoom Transformatie**

**Voorbeeld:** `V[SESSION*LandEis];landeisLand`
- **Input:** 1 SESSION atoom (buitenste BOX-atoom)
- **Output:** Meerdere LandCode atomen (nieuwe BOX-atomen)
- **Interface effect:** Voor elke LandCode wordt een HTML div gemaakt
- **Volgende niveau:** Elke div heeft die LandCode als BOX-atoom

## Interface Structuur Fouten & Correcties

### ❌ **Foutieve Dubbele BOX Nesting**
```ampersand
"📋 Per Land" : V[SESSION*LandEis];landeisLand cRud BOX<TABLE>
  [ "LandEis" : landeisLand~ cRud BOX<TABLE>  -- FOUT: Dubbele TABLE nesting
```

**Probleem:** 
- Inconsistente structuur met andere tabs
- Onduidelijke BOX-atoom flow  
- Dubbele TABLE nesting zonder logische reden

### ✅ **Correcte Interface Structuur**
```ampersand
"📋 Per Land" : V[SESSION*LandEis];landeisLand cRud BOX<TABLE>
  [ "Land code"     : I                      cRud<LandCode>
  , "Land naam"     : naam                   cRud  
  , "Landeneisen"   : landeisLand~           cRud BOX<TABLE>
      [ "POcombinatie" : landeisPO          cRud
      , "Product"      : landeisPO;productNaam cRud
      , "Eistype"      : landeisType        cRud
      ]
  ]
```

**Waarom correct:**
- **BOX-atoom flow:** SESSION → LandCode atomen → per LandCode de bijbehorende LandEis tupels
- **Logische groepering:** Eerst land identificatie, dan bijbehorende landeneisen
- **Consistente nesting:** Één TABLE niveau per groepering
- **Inverse relatie:** `landeisLand~` gaat van LandCode terug naar alle LandEis van dat land

## Belangrijke Interface Patronen

### 🏗️ **Groeperings Interface Template**
```ampersand
INTERFACE Name : "_SESSION"[SESSION] CRuD BOX<TABS>
  [ "Per Groep" : V[SESSION*DataType];groepRelatie cRud BOX<TABLE>
      [ "Groep ID"    : I                    cRud<GroepConcept>
      , "Groep Info"  : groepEigenschappen   cRud
      , "Details"     : groepRelatie~        cRud BOX<TABLE>
          [ "Detail 1" : detailRelatie1     cRud
          , "Detail 2" : detailRelatie2     cRud
          ]
      ]
  ]
```

### 🔄 **Interface Architectuur Principes**

1. **Eén BOX-atoom per interface niveau** - helder gedefinieerde context
2. **Consistente nesting diepte** - gelijke tabs hebben gelijke structuur  
3. **Logische compositie flow** - van algemeen naar specifiek
4. **TYPE-based eindpunten** - atomen zonder BOX worden automatisch gerendered
5. **Inverse relaties voor details** - van groep terug naar items

## Compositie Debugging

### 🔍 **Type Checking Methode**
1. Identificeer source concept (links van eerste relatie)
2. Volg elke relatie: domain → codomain  
3. Controleer type compatibiliteit tussen opeenvolgende relaties
4. Eindresultaat = source → final codomain

### 🎯 **BOX-atoom Tracing**
1. Start met buitenste BOX-atoom concept
2. Pas BOX-term compositie toe
3. Resultaat = nieuwe BOX-atoom concepten
4. Herhaal per nieuw BOX-atoom

Deze methodiek helpt bij het debuggen van complexe interface structuren en het begrijpen van de relationele flow in Ampersand interfaces.
