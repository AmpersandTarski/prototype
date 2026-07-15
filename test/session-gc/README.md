# Session garbage-collection tests

Toetst dat `I[SESSION]` alleen sessies bevat die binnen `session.expirationTime`
actief waren: zodra een nieuwe sessie start, verwijdert het framework de verlopen
sessie-atomen (`Session::deleteExpiredSessions`, aangeroepen vanuit
`Session::initSessionAtom`). De race-tests bewaken dat dit ook onder gelijktijdige
requests werkt (advisory lock, idempotente atom-delete, korte GC-transacties —
zie de commit-historie van branch `fix-session-gc` voor de gevonden deadlocks).

## Opzet (eenmalig)

Draait geïsoleerd op poort 9280, naast de gewone dev-stack:

```bash
cd test/session-gc
docker compose -p sessgc up -d --build

# backend genereren (hello-world) en dependencies installeren
docker exec sessgc-prototype sh -c "ampersand proto --no-frontend /var/www/test/projects/hello-world/model/main.adl --proto-dir /var/www/backend --crud-defaults cRud"
docker exec sessgc-prototype sh -c "cd /var/www && composer install --no-interaction"

# API bereikbaar maken (frontend is niet nodig voor deze tests)
cd ../.. && mkdir -p html && cp -r backend/public/ html/

# korte expiratietijd instellen: voeg toe aan backend/config/project.yaml onder settings:
#   session.expirationTime: 5
# (na afloop weer verwijderen; niet committen)

curl -s "http://localhost:9280/api/v1/admin/installer"
```

## Draaien

```bash
./test.sh all        # volledige merge-gate (races 3x)
./test.sh mixedrace  # losse fase: {fixed|alive|return|race|mixedrace}
```

Alle fasen moeten PASS geven. De race-fasen zijn probabilistisch; draai ze bij
twijfel vaker.

## Opruimen

```bash
cd test/session-gc
docker compose -p sessgc down -v
git checkout ../../backend/config/project.yaml   # expiratietijd terugzetten
```
