## Where do I find HTML templates?
The HTML templates allow you to tune the behaviour of user interfaces to your liking, by replacing the default behaviour by HTML-code of your own. They live in two places:

1. __Built-in templates__ (framework defaults): `frontend/src/app/generated/.templates/`\
   Contains `Box-*.html`, `Atomic-*.html`, `View-*.html`, and scaffolding `.txt` files used by the code generator.

2. __Custom/project-level templates__ (overrides): `test/projects/project-administration/templates/`\
   Projects can drop templates here to override the built-ins for that specific project.

Documentation on how to work with these is in `docs/guides/creating-custom-box-templates.md` and `docs/reference-material/box-template-architecture.md`.
