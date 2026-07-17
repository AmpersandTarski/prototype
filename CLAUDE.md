# PrototypeFramework - Project Specifieke Instructies

## 0. Missie & Rol (essentie)
- **Mijn taak:** onderhoud het PrototypeFramework — nu en in elke volgende opdracht. Dit is de runtime (PHP backend + Angular frontend) waarnaar de **Ampersand compiler** een formele ADL-specificatie vertaalt naar een werkende webapplicatie.
- **Samenwerking met de compiler:** ik werk samen met de Ampersand compiler. Het framework levert generieke, vaste runtime-code; de compiler vult die per applicatie met gegenereerde code/JSON. Wijzigingen aan het framework mogen die contract-grens (zie §6) niet breken.
- **Begrijpen voor handelen:** voor framework-onderhoud moet ik drie dingen kennen — (1) de architectuur van een Ampersand-applicatie, (2) het framework zelf, (3) de deployment van een gegenereerd prototype. Verwijzingen daarvoor staan in §6.

## 1. Tech Stack & Architectuur
- **Backend:** PHP (gegenereerd vanuit Ampersand ADL)
- **Frontend:** Angular + TypeScript + SCSS
- **Webserver:** Apache
- **Database:** MariaDB 10.6 (gegenereerd schema, `--lower-case-table-names=1`, `ANSI,TRADITIONAL` mode, UTF-8)
- **Infrastructuur:** Docker & Docker Compose
- **Ecosysteem:** Dit is het framework (PHP/Angular) waarnaar Ampersand compileert.

## 2. Build & Test Workflow (CRITICAAL)
**Gebruik UITSLUITEND `./generate.sh` om het project te compileren en bouwen.**
- **NOOIT** `ng serve` of `ng build` direct gebruiken.
- **NOOIT** andere docker commando's gebruiken dan `docker compose up -d` (en down/logs).

**Standaard cyclus voor testprojecten:**
1. Start containers: `docker compose up -d` (alleen als ze nog niet draaien)
2. Genereer en bouw: `./generate.sh <test-project-map> <entry-file.adl>` (bijv. `./generate.sh box-filtered-dropdown main.adl`). Geldige `<test-project-map>` = een submap van `test/projects/` (default: `hello-world`).
3. Test in browser op `http://localhost` (controleer console op JS errors)

### Merge-gate: `test/run-regression.sh` (standaard)

Verifieer vóór elke merge met de runner; noem in het merge-rapport welke projecten je draaide en hun verdict.

- `test/run-regression.sh <project>` — één project: eigen stack (poort 9400), model compileren, installeren, spec draaien, afbreken. Exitcode = verdict.
- `test/run-regression.sh all` — alle projecten + contributierapport (verdict, duur, en wat elk project bewaakt).
- `test/run-regression.sh --list` — wat elk project bewaakt (uit de `**Guards:**`-regel van zijn README).

Raakt je wijziging gedrag dat nog geen project bewaakt? Voeg dan een project of spec toe (`test/projects/<p>/e2e/`) — de reproductie die de bug aantoonde wordt de regressie die de fix bewaakt. Zie issue #402; browser-specs zijn Playwright. Runs serialiseren zichzelf met een lock (`flock` bestaat niet op macOS, dus de runner gebruikt een mkdir-lock).

De runner compileert het model in je werkkopie: ná een run staan in `backend/generics/` en `html/` de bestanden van het laatst gedraaide project (allebei gitignored). Draai `./generate.sh <project>` om je dev-stack terug te zetten op het project waaraan je werkt.

## 3. Base Image Bouwen (Voor linux/amd64)
Het base image (`ampersandtarski/prototype-framework:local`) wordt gebruikt in `FROM` statements van alle test- en klantprojecten.
**ALTIJD bouwen voor `linux/amd64`**, ook op Apple Silicon:
```bash
docker buildx build \
  --platform linux/amd64 \
  --load \
  -t ampersandtarski/prototype-framework:local \
  /Users/stef/git/PrototypeFramework
```
*(Gebruik nooit `docker tag` om een bestaand image te overschrijven, bouw altijd opnieuw).*

## 4. Specifieke Testprojecten & Commando's

### Box-Filtered-Dropdown (bfdd)
- **Starten (poort 9080):**
  ```bash
  cd /Users/stef/git/PrototypeFramework/test
  docker compose -f docker-compose.yml -f docker-compose.override.yml -p bfdd up -d --build
  ```
- **Installeren (ignoreInvariantRules):**
  ```bash
  docker exec bfdd-prototype curl -s "http://localhost/api/v1/admin/installer?ignoreInvariantRules=true"
  ```
- **Opruimen:**
  ```bash
  cd /Users/stef/git/PrototypeFramework/test
  docker compose -f docker-compose.yml -f docker-compose.override.yml -p bfdd down -v
  ```
- **Toegang:** App op `http://localhost:9080`, PhpMyAdmin op `http://localhost:9081`.

### NVWA FC5 Prototype (Landeneisenregister / e-CertNL-registerkern)
Broncode: `~/git/FC5` (container `ecert-prototype`; app op poort 8090, phpMyAdmin 8091, Swagger 8092). Bouwt op het gepubliceerde base image (`FROM ampersandtarski/prototype-framework:<versie>` in `project/Dockerfile`) — frameworkwijzigingen bereiken FC5 dus pas na een release + rebuild.
- **Alleen frontend gewijzigd:**
  ```bash
  cd ~/git/FC5 && docker compose up -d --build
  ```
- **Backend/Datamodel gewijzigd (wist database!):**
  ```bash
  cd ~/git/FC5 && ./nvwa_prototype_init.sh
  ```
- **Puppeteer-repro's/demoscripts:** `~/git/FC5/project/demo/` (draaien vanuit `~/git/FC5`).

## 5. Handige Tools
- **Cline-dialoog opvragen:** Gebruik `cline-sessie` om het pad naar de meest recente `api_conversation_history.json` te krijgen.

## 6. Documentatie & Naslag (verwijzingen)
Lees eerst hier voordat je gaat zoeken. Paden zijn geverifieerd op 2026-05-31.

### Gegenereerd vs. vaste code (contract-grens — niet breken)
- **Gegenereerd** (compiler-output, niet handmatig editen): `backend/generics/*.json` + `database.sql` (concepts, relations, interfaces, rules, conjuncts, views, roles, populations, settings), `frontend/src/app/generated/`, build-output in `html/`.
- **Vast** (framework, hier onderhouden): `backend/src/Ampersand/` (PHP-runtime: `AmpersandApp.php`, `Model.php`, `Session.php`, `Transaction.php` + mappen API/Controller/Core/Event/Frontend/Interfacing/Rule/Plugs), `frontend/src/app/shared/` (atomic- & box-components), `frontend/src/app/{layout,admin}/`, `frontend/src/app/generated/.templates/` (template-voorbeelden).
- **Compilerversie-contract:** `backend/generics/compiler-version.txt` (nu `>=5.0.0 <6.0.0`). Check bij compiler-upgrades.

### (a) Ampersand-applicatie-architectuur — `~/git/Ampersand/docs/`
- `CompilationProcess.md` — pipeline ADL → P_Context (parse) → A_Context (typecheck, lattice/typologies) → FSpec (enrichment) → generatie. Lezen bij type-/compilatievragen.
- `reference-material/architecture-of-an-ampersand-application.md` — MVC, monolithische deployment, ExecEngine, hookpoints.
- `reference-material/interfaces.md` — interface-syntax, BOX-layout (TABS/TABLE/FORM), CRUD-annotaties, sub-interfaces.
- `reference-material/concept-hierarchies-and-database-mapping.md` — concepts/typologies → DB-tabellen.
- `modeling/conceptual-modeling.md` + `conceptual/automated-rules.md` — domeinmodellering en regel-automatisering (ExecEngine).

### (b) Framework zelf — `./docs/`
- `README.md` — ingang tot de docs.
- `reference-material/prototype-framework.md` — frameworkoverzicht: config, logging, generics, Event Dispatcher, compilerversies.
- `reference-material/box-template-architecture.md` + `guides/creating-custom-box-templates.md` — BOX-templates + template-variabelen (`$name$`, `$crud$`, …).
- `reference-material/frontend-components.md` + `frontend-component-internals.md` — atomic/box-components, type-mapping, CRUD, multipliciteit.
- `reference-material/data_flow_analysis.md` — dataflow backend-API → frontend.
- `guides/frontender-quick-start.md`, `guides/back-end-services.md`, `guides/creating-custom-view-templates.md` — dev-setup, PHP-extensie (plugins/listeners/ExecEngine), VIEW-templates.

### (c) Deployment van een gegenereerd prototype
- Base image `ampersandtarski/prototype-framework:local` ← `Dockerfile` (prod, PHP 8.3-apache) / `dev.Dockerfile` (dev). **Altijd `--platform linux/amd64` bouwen** (zie §3).
- `compose.yaml` — dev-stack (prototype + MariaDB + phpmyadmin); `test/docker-compose*.yml` — testprojecten.
- `generate.sh` — orkestreert ADL → backend + frontend → `npm run build:dev` → kopie naar `html/`. Vereist een draaiende `prototype`-container.
- `docker/apache/000-default.conf` + `apache-conf/.htaccess` — Apache SPA-rewrite; `db-init-scripts/01_grant_ampersand.sql` — DB-user.
- **Valkuilen:** schema-incompatibiliteit bij upgrades (reset via `docker compose down -v`, wist data → eerst backup); platform-mismatch (arm64 ≠ amd64); `--load` niet vergeten bij buildx.

### Memorybank — eigen aantekeningen (`./memorybank/`)
Lees-eerst per onderwerp:
- **Architectuur/interfaces:** `ampersand-interface-architectuur.md` (BOX-atomen, compositie, interface-debugging), `ampersand-crud-rules.md`, `ampersand-concept-type-mapping.md`, `ampersand-ontwerp-methodiek.md`, `ampersandSyntax.md`, `ampersand-operators-reference.md`.
- **Framework/frontend:** `frontend-development.md`, `systemPatterns.md`, `techContext.md`, `productcontext.md`, `projectbrief.md`, `atomic-*.md`.
- **Deployment/DB:** `build-commando-cheatsheet.md`, `deployment-framework-updates.md`, `database-schema-compatibility-warnings.md`, `ampersand-database-inspection-guide.md`, `ampersand-test-project-workflow.md`, `werkdirectory-richtlijnen.md`.