# Ampersand Producttype Patroon

## Wanneer gebruik je een producttype?

**ALLEEN** wanneer je **tupels** (paren, triples, quadrupels) nodig hebt voor interfaces of logica.

Een producttype is een **algebraïsch product** van concepten voor tupel-representatie, NIET voor automatisering of data management.

## Anatomie van een producttype

Bij elk producttype horen automatisch **functies** (@1, @2, @3, etc.) die toegang geven tot de componenten van de tupel.

### Voorbeeld: Triple producttype A × B × C

```ampersand
CONCEPT TripleType "Producttype van A, B en C"

-- Component functies (altijd UNI,TOT)
RELATION tripleA[TripleType*A] [UNI,TOT]  -- @1 functie
RELATION tripleB[TripleType*B] [UNI,TOT]  -- @2 functie  
RELATION tripleC[TripleType*C] [UNI,TOT]  -- @3 functie
```

### Automatische CREATE/DELETE/ENFORCE regels

**ALTIJD nodig** bij een producttype volgens dit exacte patroon:

```ampersand
-- Input koppeling
RELATION inputTriple[InputConcept*TripleType] [UNI]

-- CREATE regel
RULE TripleCreate : 
  (I[InputConcept] /\ compA;compA~ /\ compB;compB~ /\ compC;compC~) - inputTriple;inputTriple~ |- 
  inputTriple;I[TripleType];inputTriple~
VIOLATION ( TXT "{EX} InsAtom;TripleType"
          , TXT "{EX} InsPair;inputTriple;InputConcept;", SRC I, TXT ";TripleType;_NEW"
          , TXT "{EX} InsPair;tripleA;TripleType;_NEW;A;", SRC compA
          , TXT "{EX} InsPair;tripleB;TripleType;_NEW;B;", SRC compB  
          , TXT "{EX} InsPair;tripleC;TripleType;_NEW;C;", SRC compC
          )

-- ENFORCE regels: functies synchroon houden
ENFORCE tripleA := inputTriple~;compA
ENFORCE tripleB := inputTriple~;compB
ENFORCE tripleC := inputTriple~;compC

-- DELETE regel
RULE TripleDelete : 
  inputTriple |- compA;tripleA~ /\ compB;tripleB~ /\ compC;tripleC~
VIOLATION ( TXT "{EX} DelAtom;TripleType;", TGT I )

ROLE ExecEngine MAINTAINS TripleCreate, TripleDelete
```

## Waarom deze regels nodig zijn

1. **Tupel integriteit**: Zorgt dat elke tupel alle componenten heeft
2. **Automatische synchronisatie**: Input changes propageren naar tupel
3. **Cleanup**: Verwijdert tupels als input verdwijnt
4. **Consistentie**: Functies blijven synchroon met input

## Fout die ik maakte

Deze regels zijn **altijd verplicht** bij elk producttype, omdat de functies moeten bestaan en gevuld moeten zijn met de juiste gegevens (totaal dus).
Producttype = tupel + automatische functie regels.

## ExportEisen.ifc als referentie

Gebruik altijd `PATTERN LandenEisenProductTypes` als template voor:
- Component relaties met [UNI,TOT]
- CREATE regel met juiste voorwaarden
- ENFORCE regels voor elke component  
- DELETE regel voor cleanup
- ExecEngine rol assignment
