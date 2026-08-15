# Using the OpenAPI Description of Your Prototype

Every prototype you generate with the Ampersand compiler exposes a REST API. This guide shows you how to discover, browse, and use that API through its **OpenAPI description** — a standard, machine-readable specification of every endpoint the prototype offers.

You do not have to write the OpenAPI description yourself. The compiler generates it from your ADL model, and the framework publishes it for you. You only have to know where to look.

## What is OpenAPI?

[OpenAPI](https://www.openapis.org/) is an industry-standard format for describing REST APIs. An OpenAPI document lists every path (such as `/api/v1/resource/...`), the HTTP methods it accepts, the shape of the data it returns, and the parameters it expects. Because the format is standardised, a whole ecosystem of tools can read it: interactive documentation viewers, API test clients, and code generators that build a typed client library in the language of your choice.

For an Ampersand prototype this means: the interfaces you declare in your ADL script (`INTERFACE ... FOR ...`) become documented API endpoints, automatically and always in sync with your model.

## Where to find it

A running prototype publishes the description at two addresses. Assuming your prototype runs on `http://localhost`, these are:

| Address | What it is | For |
|---------|------------|-----|
| `http://localhost/api/v1/docs` | Swagger UI — an interactive, clickable web page | **Humans**: browse and try the API in your browser |
| `http://localhost/api/v1/openapi.json` | The raw OpenAPI 3.0 document (JSON) | **Tools**: Postman, code generators, other software |

If your prototype runs on a different host or port, replace `http://localhost` accordingly (for example `http://localhost:9080`).

## Browsing the API in Swagger UI

Open `http://localhost/api/v1/docs` in your browser. You will see the **Swagger UI**: a list of every endpoint your prototype offers, grouped and expandable. For each endpoint you can:

- read which HTTP method and path to use;
- see the parameters and the request/response data structures;
- click **Try it out**, fill in parameters, and **Execute** the call against your running prototype — the response appears right there on the page.

This is the fastest way to understand what your model exposes without writing a single line of client code.

> **Note.** Swagger UI loads its styling and scripts from a public CDN, so your browser needs internet access to render the page. The API calls it makes go to your own prototype, not to the internet.

## Using the spec with other tools

The raw document at `http://localhost/api/v1/openapi.json` is what you feed to tooling:

- **Postman / Insomnia** — import the URL (or the downloaded file) to get a ready-made collection of every request.
- **Client code generation** — tools such as [openapi-generator](https://openapi-generator.tech/) turn the spec into a typed client library (TypeScript, Python, Java, …), so you can call your prototype from another application without hand-writing HTTP code.
- **Validation / linting** — tools such as `npx @redocly/cli lint` check the spec.

Download it with:

```bash
curl -s http://localhost/api/v1/openapi.json -o openapi.json
```

Because the framework serves exactly the file the compiler generated, the spec is **always current**: every time you regenerate the prototype, the published description reflects your latest model. There is nothing to keep in sync by hand.

## When it is available (development vs. production)

The OpenAPI description is a **development** feature. It is published only when the prototype runs **outside production mode**:

- A **development build** (the default, `ampersand proto`) generates the spec and publishes it.
- A **production build** (`ampersand proto --production`) does **not** generate the spec, and the framework does not publish it. Both `/api/v1/docs` and `/api/v1/openapi.json` return **404 Not Found**.

The reason: the description lays out your entire API surface, which is useful while building and testing but is usually not something you want to advertise on a public deployment. See [Configuring Development and Production Environments](configuring-environments.md) for how the development/production switch works.

If you ask for a production build but still want the spec generated, the compiler flag `--openapi` forces generation; `--no-openapi` forces it off. To actually serve it in production you would additionally have to run the prototype with `global.productionEnv = false` — do that deliberately and behind your own access control.

## Troubleshooting

**Both URLs return 404.** Either the prototype is in production mode, or `backend/generics/openapi.json` was not generated. Regenerate with a development build and restart the prototype.

**Swagger UI page is blank or unstyled.** Your browser cannot reach the CDN. Check your internet connection, or serve `swagger-ui-dist` locally for offline/air-gapped environments.

**A tool cannot read the spec cross-origin.** It should not happen — the spec is served with `Access-Control-Allow-Origin: *`. Confirm you are requesting `/api/v1/openapi.json` (the JSON), not `/api/v1/docs` (the HTML page).

## See also

- [Configuring Development and Production Environments](configuring-environments.md) — the development/production switch that controls publication.
- [OpenAPI publication](../reference-material/openapi-publication.md) — how the framework serves the spec, for contributors.
