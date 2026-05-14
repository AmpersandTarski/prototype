---
title: The Prototype Framework
---

# Ampersand Prototype Framework

This documentation is published at [ampersandtarski.github.io](https://ampersandtarski.github.io/). It targets developers of the framework and advanced users of Ampersand.

## Going to write or change documentation?

Read [Documenting Prototype Framework Changes](guides/documenting-prototype-changes.md) first. That guide describes the complete workflow from writing to testing locally to publishing. It tells you what to do before you push to main.

Test your changes locally before pushing. Instructions are in the [AmpersandTarski.github.io README](https://github.com/AmpersandTarski/AmpersandTarski.github.io#local-development--testing).

## Documentation structure

```
docs/
├── sidebar.js              # Navigation — update when adding pages
├── README.md               # This file
├── guides/                 # How-to guides and tutorials
└── reference-material/     # Technical reference
```

## How the documentation reaches the website

Three repositories contribute to one Docusaurus site:

- **Ampersand** — language documentation
- **Prototype Framework** (this repo) — framework guides and reference material
- **RAP** — RAP-specific documentation

Pushing to `main` with changes in `docs/` triggers the automated build at [AmpersandTarski.github.io](https://github.com/AmpersandTarski/AmpersandTarski.github.io).
