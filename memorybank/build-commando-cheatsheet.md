# Build-commando cheatsheet

Terugkerende Docker-commando's voor het PrototypeFramework.

---

## Prototype Framework base image bouwen

Het base image wordt gebruikt in `FROM`-statements in alle test- en klantprojecten.
De canonical naam is `ampersandtarski/prototype-framework:local`.

> ⚠️ **Altijd bouwen voor `linux/amd64`** — ook op een Apple Silicon Mac.
> Klant- en testcontainers draaien op linux/amd64; een native arm64-image werkt niet in die context.

**Standaard bouwcommando (linux/amd64, voor gebruik in test/klantprojecten):**

```bash
docker buildx build \
  --platform linux/amd64 \
  --load \
  -t ampersandtarski/prototype-framework:local \
  /Users/stef/git/PrototypeFramework
```

> `--load` zorgt dat het image beschikbaar komt in de lokale Docker daemon (vereist voor gebruik in `FROM` en `docker run`).
> Zonder `--load` wordt het gebouwd maar niet opgeslagen.
> Op Apple Silicon is `buildx` vereist voor cross-compilatie; gewone `docker build` zonder `--platform` levert een arm64-image op.

---

## box-filtered-dropdown testproject starten (poort 9080)

Vereist: `test/.env` en `test/docker-compose.override.yml` aanwezig.

> ⚠️ Gebruik **geen** poort 7000 (AirPlay Receiver op macOS) of 7070 (Oracle/Apple-bereik).
> Poort 9080 is de standaardkeuze: valt in het 9000-9099-bereik dat gangbaar is voor test-/adminwebservers.

```bash
cd /Users/stef/git/PrototypeFramework/test
docker compose \
  -f docker-compose.yml \
  -f docker-compose.override.yml \
  -p bfdd \
  up -d --build
```

Na het starten de applicatie installeren (ignoreInvariantRules vanwege metapopulatie-duplicaten):

```bash
docker exec bfdd-prototype curl -s \
  "http://localhost/api/v1/admin/installer?ignoreInvariantRules=true"
```

Opruimen:

```bash
cd /Users/stef/git/PrototypeFramework/test
docker compose -f docker-compose.yml -f docker-compose.override.yml -p bfdd down -v
```

De container heet `bfdd-prototype` en is bereikbaar op http://localhost:9080.
PhpMyAdmin voor de bfdd-database: http://localhost:9081 (verbindt met `bfdd-db`).

---

## NVWA FC5 prototype herbouwen en starten

Projectdirectory: `/Users/stef/Library/CloudStorage/GoogleDrive-stefjoosten1@gmail.com/Mijn Drive/cloudDrive/NVWA/FC/FC5`

### Alleen frontend gewijzigd (snel, behoudt database)

Gebruik dit als alleen de frontend (Angular) is veranderd:

```bash
cd "/Users/stef/Library/CloudStorage/GoogleDrive-stefjoosten1@gmail.com/Mijn Drive/cloudDrive/NVWA/FC/FC5" && docker compose up -d --build
```

### Backend of datamodel gewijzigd (herbouwen + database initialiseren)

Gebruik dit als het Ampersand-model of de backend is veranderd. Herbouwt het image EN initialiseert de database opnieuw:

```bash
cd "/Users/stef/Library/CloudStorage/GoogleDrive-stefjoosten1@gmail.com/Mijn Drive/cloudDrive/NVWA/FC/FC5" && ./nvwa_prototype_init.sh
```

> ⚠️ `nvwa_prototype_init.sh` wist de bestaande database en laadt initiële populatie opnieuw.
> Gebruik dit alleen als het datamodel of de backend-code is gewijzigd.

---

## Naamconventies

| Naam | Doel |
|---|---|
| `ampersandtarski/prototype-framework:local` | Base framework image — gebruikt in `FROM` van alle projecten |
| `ampersandtarski/prototype:local` | Alias voor het framework image (vermijd: gebruik de `prototype-framework` naam) |
| `ampersandtarski/bfdd-prototype:local` | Gebouwd image voor box-filtered-dropdown testproject |

> **Nooit** `docker tag prototype:local prototype-framework:local` gebruiken om een bestaand framework image te overschrijven.
> Bouw altijd opnieuw via `docker buildx build` zoals hierboven.

---

## Cline-dialoog opvragen

Geeft het volledige pad naar de `api_conversation_history.json` van de meest recente Cline-taak:

```bash
cline-sessie
```

Geïnstalleerd in `/usr/local/bin/cline-sessie` (beschikbaar voor alle gebruikers).
Retourneert alleen het pad op stdout; fouten gaan naar stderr.

Gebruik in combinatie met `jq` om de dialoog te doorzoeken:

```bash
cat "$(cline-sessie)" | jq '.[] | select(.role=="user") | .content[0].text' 2>/dev/null | head -5
```
