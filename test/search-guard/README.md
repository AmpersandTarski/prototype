# Search authorisation tests

Toetst dat `GET /api/v1/search` alleen atomen teruggeeft die de sessie mag lezen:
een atoom is pas een zoekresultaat als de sessie ten minste één interface heeft om
het in te openen (`AmpersandApp::getInterfacesToReadConcept`). Zonder die check
geeft de zoekmodule de opgeslagen waarden zelf (`matches[].value`) terug aan elke
sessie, ongeacht rollen — ook zonder login.

Het model `test/projects/search-guard` bevat daarvoor:

- `dossierNote[Dossier*Note]` — **non-UNI**, dus opgeslagen in een eigen binaire
  tabel (brede concepttabellen worden door de zoekmodule niet doorzocht; zie de
  losse bevinding daarover).
- `INTERFACE Dossiers FOR Caseworker` — de enige manier om een `Dossier` te lezen.
- `INTERFACE Welcome FOR Anonymous` — zodat een anonieme sessie zelf wél iets heeft.

## Opzet

```bash
cd test/search-guard
docker compose -p searchguard up -d --build   # of hergebruik een bestaande dev-stack

docker exec searchguard-prototype sh -c \
  "ampersand proto --no-frontend /var/www/test/projects/search-guard/model/main.adl \
   --proto-dir /var/www/backend --crud-defaults cRud"
docker exec searchguard-prototype sh -c "cd /var/www && composer install --no-interaction"
cd ../.. && mkdir -p html && cp -r backend/public/ html/
```

## Draaien

De twee fasen vragen elk een andere instelling in `backend/config/project.yaml`:

```bash
# fase 1: zet `session.loginEnabled: true` onder settings:
curl -s http://localhost:9380/api/v1/admin/installer
./test.sh anonymous     # verwacht: 0 resultaten

# fase 2: zet `session.loginEnabled: false`
./test.sh authorized    # verwacht: 1 resultaat, met interface Dossiers
```

Beide fasen moeten PASS geven. Zet `project.yaml` daarna terug
(`git checkout backend/config/project.yaml`).
