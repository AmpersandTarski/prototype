## Where do I find HTML templates?
The HTML templates allow you to tune the behaviour of user interfaces to your liking, by replacing the default behaviour by HTML-code of your own. They live in two places:

1. __Built-in templates__ (framework defaults): `frontend/src/app/generated/.templates/`\
   Contains `Box-*.html`, `Atomic-*.html`, `View-*.html`, and scaffolding `.txt` files used by the code generator.

2. __Custom/project-level templates__ (overrides): `test/projects/project-administration/templates/`\
   Projects can drop templates here to override the built-ins for that specific project.

Documentation on how to work with these is in `docs/guides/creating-custom-box-templates.md` and `docs/reference-material/box-template-architecture.md`.

## Does my prototype have an API description I can browse?
Yes. Every prototype publishes an [OpenAPI](https://www.openapis.org/) 3.0 description of its REST API. Open `http://localhost/api/v1/docs` for an interactive Swagger UI, or fetch the raw document from `http://localhost/api/v1/openapi.json` for tools such as Postman or code generators. See [Using the OpenAPI Description of Your Prototype](guides/using-the-openapi-description.md).

## Why do `/api/v1/docs` and `/api/v1/openapi.json` return 404?
The OpenAPI description is a development feature. It is published only when the prototype runs **outside production mode**, and only when the compiler actually generated `backend/generics/openapi.json`. A production build (`ampersand proto --production`) neither generates nor serves it, so both URLs return 404. Regenerate with a development build and restart the prototype.

## Is the OpenAPI description kept in sync with my model?
Automatically. The compiler regenerates `backend/generics/openapi.json` from your `INTERFACE` definitions on every development build, and the framework serves exactly that file. There is nothing to update by hand.

## Do I need to write the OpenAPI specification myself?
No. The Ampersand compiler generates it from your ADL model. The framework only serves it. For how that wiring works, see [OpenAPI publication](reference-material/openapi-publication.md).
