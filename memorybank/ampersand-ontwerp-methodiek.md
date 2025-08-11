# Systematische Aanpak voor Ontwerpen in Ampersand

## Overzicht
Dit document beschrijft de systematische aanpak voor het ontwerpen van een wijzigingsworkflow voor fytosanitaire eisen.
Deze methodiek is algemeen toepasbaar voor het ontwikkelen van Ampersand applicaties.

## **Stap 1: Requirements Analyse**

### **Wat ik deed:**
- User story grondig analyseren en opsplitsen in concrete functionele eisen
- Identificeren van de kernentiteiten en hun onderlinge relaties
- Bepalen van de business rules en constraints

### **In dit geval:**
De user story was:
1. Interface "Wijziging" waarin gebruiker een wijziging kan editen
2. Wijziging gaat over één eis, meerdere producten, meerdere organismen  
3. Auteur maakt dekkingen aan uit vaste verzameling
4. Auteur maakt bijschrijvingen aan
5. Bewijsmiddelen zijn zichtbaar tijdens wijzigen

**Kernentiteiten geïdentificeerd:**
- Wijziging (hoofdentiteit)
- Eis (1:1 relatie)
- Product (1:n relatie) 
- Organisme (1:n relatie)
- Dekking (n:m relatie, uit bestaande verzameling)
- Bijschrijving (1:n relatie, nieuwe entiteit)
- Bewijsmiddel (1:n relatie, nieuwe entiteit)

## **Stap 2: Bestaande Codebase Verkennen**

### **Wat ik deed:**
- `main.adl` lezen om te zien welke modules er al zijn
- Relevante bestanden bekijken (`Plagen.adl`, `DekkingSelectie.adl`) 
- Bestaande concepten en relaties identificeren
- Herbruikbare patronen ontdekken

### **Belangrijke bevindingen:**
- `Eis`, `Product`, `Organisme`, `Dekking` concepten bestaan al
- Er is een patroon voor EPPO codes en wetenschappelijke namen
- Interfaces gebruiken meestal `BOX<FORM>` en `BOX<TABLE>` layouts
- `REPRESENT` statements voor datatypen zijn consistent
- `MEANING` statements worden gebruikt voor documentatie

**Ontwerpbeslissing:** Hergebruik bestaande concepten waar mogelijk, voeg alleen nieuwe toe waar nodig.

## **Stap 3: Interface-First Design**

### **Waarom deze aanpak:**
- Interface definieert wat de gebruiker daadwerkelijk kan zien en doen
- Helpt bij het identificeren van werkelijk benodigde relaties
- Voorkomt over-engineering door focus op gebruikersbehoefte
- Maakt het makkelijker om later te valideren of het ontwerp klopt

### **Hoe ik het deed:**
- Begon met hoofdinterface `INTERFACE Wijziging`
- Gebruikte logische secties gebaseerd op user story
- Definieerde CRUD rechten per veld
- Koos geschikte BOX types (`<FORM>`, `<TABLE>`)

### **Interface structuur:**
```ampersand
INTERFACE Wijziging : I[Wijziging] CRuD BOX<FORM>
  [ -- Basis informatie
  , -- Scope (eis, producten, organismen)  
  , -- Dekkingen uit vaste verzameling
  , -- Bijschrijvingen door auteur
  , -- Bewijsmiddelen ter ondersteuning
  ]
```

## **Stap 4: Conceptueel Model Afleiden**

### **Van interface naar relaties:**
Door de interface te analyseren kon ik exact bepalen welke relaties nodig waren:

**Uit interface veld → Relatie afleiden:**
- `wijzigingTitel` → `wijzigingTitel[Wijziging*Titel] [UNI,TOT]`
- `betreftEis` → `betreftEis[Wijziging*Eis] [UNI,TOT]` 
- `wijzigingProducten` → `wijzigingProducten[Wijziging*Product] [TOT]`

### **Multipliciteiten bepalen:**
- **[UNI]** voor 1:1 relaties (maar wees spaarzaam met TOT)
- **Geen TOT constraint** tenzij je een duidelijke reden hebt
- **Denk na over gebruikerservaring** - TOT dwingt onmiddellijke invulling af

**CRUCIALE REGELS:**
1. **Gebruik TOT/SUR/UNI nooit als je niet weet waarom** je de eis stelt
2. **Bij TOT/SUR altijd afvragen:** Wil je invulling echt verplicht stellen? Wat doet dit voor de gebruikerservaring?
3. **Onderscheid:** "meteen verplicht invullen" vs "op den duur verplicht invullen"

## **Stap 5: Patterns en Logische Groepering**

### **Pattern indeling:**
- `WijzigingsGegevens` - alle basisrelaties van Wijziging
- `BijschrijvingGegevens` - eigenschappen van Bijschrijving concept  
- `BewijsmiddelGegevens` - eigenschappen van Bewijsmiddel concept

### **Waarom patterns gebruiken:**
- Logische groepering van gerelateerde relaties
- Makkelijker onderhoud en begrip
- Volgt Ampersand best practices
- Helpt bij modulariteit

## **Stap 6: Validatieregels en Business Logic**

### **Rules afleiden van requirements:**
```ampersand
RULE "Wijziging moet minimaal één product hebben":
  I[Wijziging] |- wijzigingProducten;wijzigingProducten~
```

### **Soorten regels:**
- **Integriteitregels** - data consistentie
- **Business rules** - domein-specifieke regels  
- **Workflow regels** - procescontrole

## **Stap 7: Views en Gebruikerservaring**

### **Views voor leesbaarheid:**
```ampersand
VIEW Wijziging: Wijziging DEFAULT
  { titel: wijzigingTitel
  , streepje: TXT " - "  
  , eis: betreftEis;eisTekst
  }
```

### **Populaties voor gebruiksgemak:**
```ampersand
POPULATION BewijsmiddelType CONTAINS
  [ "Document", "URL/Link", "Email" ]
```

## **Stap 8: Iteratie en Verfijning**

### **Checklist voor review:**
- [ ] Alle user story elementen geïmplementeerd?
- [ ] Bestaande concepten optimaal hergebruikt?
- [ ] Interface logisch en gebruiksvriendelijk?
- [ ] Relaties hebben juiste multipliciteiten?
- [ ] Validatieregels dekken belangrijkste constraints?
- [ ] Views maken data leesbaar?

## **Algemene Ontwerpprincipes**

### **1. Start Simpel, Bouw Uit**
- Begin met minimale viable interface
- Voeg geleidelijk complexiteit toe
- Test vroeg en vaak

### **2. Hergebruik Eerst, Creëer Daarna**
- Verken bestaande codebase grondig
- Hergebruik concepten en patronen waar mogelijk
- Voeg alleen nieuwe concepten toe als echt nodig

### **3. Interface-Driven Design**
- Begin met gebruikersinterface
- Leid datamodel af van interface
- Valideer ontwerp tegen gebruikersbehoefte

### **4. Patterns voor Structuur**
- Groepeer gerelateerde relaties in patterns
- Gebruik betekenisvolle pattern namen
- Documenteer met PURPOSE statements

### **5. Rules voor Kwaliteit**
- Definieer business rules expliciet
- Gebruik duidelijke regel namen en messages
- Test edge cases en foutscenario's

### **6. Kritische Evaluatie Principe (NIEUW)**
- **Bij elk veld de vraag stellen:** "Waartoe dient dit veld?"
- **Bij elke relatie de vraag stellen:** "Waartoe dient deze relatie?"
- **Als je geen goed antwoord weet:** Haal het weg
- **Eenvoud boven complexiteit:** Liever te eenvoudig dan te complex
- **PURPOSE statements schrijven:** Voor elke relatie documenteren waarom die bestaat

## **Tips voor Lesmateriaal**

### **Volgorde van Onderwijs:**
1. **Concepten en Relaties** - Basiskennis Ampersand
2. **Interface Design** - Gebruikersinteractie
3. **Pattern Thinking** - Structuur en organisatie
4. **Rule Engineering** - Business logic en validatie
5. **Integration** - Samenwerking tussen modules

### **Hands-on Oefeningen:**
- Geef studenten een user story, laat ze stap-voor-stap deze methodiek volgen
- Begin met eenvoudige scenario's (bijv. bibliotheeksysteem)
- Bouw geleidelijk complexiteit op
- Laat studenten bestaande code analyseren en uitbreiden

### **Veelgemaakte Fouten:**
- Direct beginnen met datamodel in plaats van interface
- Te veel nieuwe concepten creëren i.p.v. hergebruiken
- **TOT/SUR constraints gebruiken zonder duidelijke reden** (NIEUW)
- **Te complex ontwerpen zonder kritische evaluatie** (NIEUW)
- Onduidelijke pattern en regel naamgeving
- Interfaces maken zonder na te denken over gebruikerservaring bij constraints

### **Bewezen Verbeteringen uit Praktijk:**
- **Weg van `[UNI,TOT]` naar `[UNI]`** - Betere gebruikerservaring
- **Interface vereenvoudigen** - Weglatingen zijn vaak beter dan toevoegingen
- **Kritisch elke relatie evalueren** - "Waartoe dient dit?" principe
- **PURPOSE statements gebruiken** - Voor elke relatie documenteren waarom die nodig is

Deze methodiek kan worden toegepast op vrijwel elk Ampersand project en helpt studenten om systematisch en doordacht te ontwerpen.
