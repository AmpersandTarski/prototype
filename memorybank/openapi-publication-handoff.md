# OpenAPI-publicatie — handoff / implementatienotitie

> **Status: GEÏMPLEMENTEERD (2026-06-17).** Afgeweken van het oorspronkelijke plan op drie punten:
> 1. **Niet onvoorwaardelijk publiek.** Publicatie is gekoppeld aan prod/dev. De compiler kreeg een nieuwe vlag `--[no-]production`; een dev-build genereert `openapi.json`, een production-build niet. `--[no-]openapi` overruled dat. De compiler geeft de keuze door aan het framework via `global.productionEnv` in `settings.json`.
> 2. **Route via `OpenApiController`** (`backend/src/Ampersand/Controller/OpenApiController.php`) i.p.v. closures; gate op `inProductionMode()` + bestaan van het bestand (404 anders).
> 3. **Padfout in het plan gecorrigeerd:** vanuit `backend/bootstrap/api/openapi.php` is het niet `dirname(__FILE__, 2)` maar de controller gebruikt `dirname(__FILE__, 4)` om `backend/generics/openapi.json` te bereiken.
>
> Compiler-kant end-to-end geverifieerd (build + generatie dev/prod/override). Framework-kant: PHP-lint schoon; volledige runtime-test vereist een nieuw compiler-image (Dockerfile pint een gepubliceerde tag). Zie `changelog.md` (v2.1.1) en `docs/reference-material/prototype-framework.md` (§OpenAPI publication).

Doel: de door de Ampersand-compiler gegenereerde **`openapi.json`** publiek ontsluiten vanuit
het draaiende prototype, plus een doorklikbare Swagger UI. Deze notitie is uitvoeringsklaar
voor een Claude Code-sessie in `~/git/PrototypeFramework/`.

## Context (geverifieerd in de broncode)

- De compiler schrijft de spec in de generics-map: **`backend/generics/openapi.json`** (naast
  `interfaces.json` e.d.). Dat deel is klaar (zie de compiler-kant in `~/git/Ampersand`).
- In de container staat het op **`/var/www/backend/generics/openapi.json`**. De Dockerfile
  kopieert `backend → /var/www/backend` en verplaatst alleen `backend/public/*` naar de docroot
  `/var/www/html`. **Generics ligt dus buiten de docroot** → nu niet extern bereikbaar.
- API draait op **Slim 3** (`$api`). Routebestanden worden **automatisch ingeladen** via
  `glob(__DIR__ . '/api/*.php')` in `backend/bootstrap/framework.php` (regel 163-165). Een nieuw
  bestand in `backend/bootstrap/api/` is dus meteen geregistreerd — geen handmatige wiring nodig.
- De basis-URL is `/api/v1` (de app draait vanuit `html/api/v1/index.php`). Globale middleware
  (`InitAmpersandAppMiddleware` e.a.) bevat **geen auth**; auth/checksum zit per route-groep.
  Een top-level route zonder groep-middleware is daarmee **publiek**.

Dit is "vaste" frameworkcode (de compiler genereert het bestand, het framework serveert het) —
het respecteert de contract-grens uit `CLAUDE.md` §6.

## Implementatie

### 1. Nieuw routebestand: `backend/bootstrap/api/openapi.php`

```php
<?php

/** @phan-file-suppress PhanInvalidFQSENInCallable */

/**
 * @var \Slim\App $api
 */
global $api;

// Publieke, machine-leesbare OpenAPI-spec. Bewust GEEN sessie-/checksum-middleware,
// zodat externe tools (Swagger UI, Postman, codegen) hem vrij kunnen ophalen.
$api->get('/openapi.json', function ($request, $response, $args) {
    $file = dirname(__FILE__, 2) . '/generics/openapi.json'; // = backend/generics/openapi.json
    if (!file_exists($file)) {
        return $response->withJson(
            ['error' => 404, 'msg' => "openapi.json not found (run the compiler's prototype generation first)"],
            404
        );
    }
    $response->getBody()->write((string) file_get_contents($file));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withHeader('Access-Control-Allow-Origin', '*'); // publiek; CORS voor cross-origin tools
});

// Doorklikbare Swagger UI voor mensen, op /api/v1/docs (laadt openapi.json relatief).
$api->get('/docs', function ($request, $response, $args) {
    $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>API documentation</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js" crossorigin></script>
  <script>
    window.ui = SwaggerUIBundle({
      url: 'openapi.json',          // relatief → /api/v1/openapi.json
      dom_id: '#swagger-ui',
      deepLinking: true
    });
  </script>
</body>
</html>
HTML;
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});
```

Opmerkingen:
- `dirname(__FILE__, 2)` vanuit `backend/bootstrap/api/openapi.php` = `backend/`, dus
  `backend/generics/openapi.json`. Werkt zowel in de bronlocatie als in de container
  (`/var/www/backend/...`), want generics blijft onder `backend/`.
- `withJson(...)` is in dit framework op het response-object beschikbaar (zie `ResourceController`).
- `url: 'openapi.json'` is relatief aan `/api/v1/docs` → de browser haalt `/api/v1/openapi.json`.
  Beide onder `/api/v1`, dus de SPA-rewrite (`.htaccess`) raakt het niet; Slim handelt het af.
- Wil je later ook YAML aanbieden, voeg dan een `/openapi.yaml`-route toe; JSON is canoniek en
  voldoende voor vrijwel alle tooling.

### 2. Geen build-/Docker-wijzigingen nodig

`backend/` wordt al integraal in de image gekopieerd, en `openapi.json` staat in de generics-map.
Het nieuwe routebestand wordt automatisch via de glob ingeladen.

### 3. Changelog

Voeg een entry toe in `changelog.md` (framework-conventie), bijv.:
`- OpenAPI: de gegenereerde \`openapi.json\` is nu publiek beschikbaar op \`/api/v1/openapi.json\`, met Swagger UI op \`/api/v1/docs\`.`

## Testen

1. (Her)genereer een prototype zodat `backend/generics/openapi.json` bestaat, en start de container
   (`./generate.sh <project> <entry.adl>` / de gangbare flow).
2. `curl -s http://localhost/api/v1/openapi.json | head` → de spec (HTTP 200, `application/json`).
3. Open `http://localhost/api/v1/docs` in de browser → Swagger UI rendert de endpoints.
4. Optioneel: valideer de spec met een externe linter (bv. `npx @redocly/cli lint`) of importeer in Postman.

## Bewuste keuzes / aandachtspunten

- **Publiek**, zoals afgesproken: geen auth op deze twee routes. De spec beschrijft het hele
  API-oppervlak; dat is bij API-docs gebruikelijk. Wil je het later toch kunnen dichtzetten, maak
  er dan een setting van (bv. `openapi.publish`, default `true`) en sla de routes over als die uit staat.
- **CORS** staat open (`*`) zodat codegen/Swagger-tools vanaf een andere origin kunnen lezen.
- **Swagger UI via CDN**: vereist internettoegang in de browser. Voor offline/airgapped omgevingen
  kun je `swagger-ui-dist` lokaal in de docroot zetten en daarnaar verwijzen i.p.v. de CDN.
- **Bron van waarheid**: er wordt niets gedupliceerd; de route serveert exact het door de compiler
  gegenereerde bestand. Bij elke hergeneratie is de gepubliceerde spec automatisch actueel.

## Schatting

Routebestand + changelog: ~halfuur–uur inclusief testen. Swagger UI zit in hetzelfde bestand,
dus geen extra werk van betekenis.
