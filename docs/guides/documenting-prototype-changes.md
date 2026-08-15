# Documenting Prototype Framework Changes

This guide covers everything you need to publish documentation changes to [ampersandtarski.github.io](https://ampersandtarski.github.io/) from the Prototype Framework repository.

## The workflow

1. **Write** your documentation in the right folder (see below).
2. **Update `docs/sidebar.js`** to include your new page.
3. **Test locally** before pushing (see below).
4. **Push to `main`** — the automated pipeline publishes the change.
5. **Verify** that your page appears at ampersandtarski.github.io.

## Where to put your documentation

```
docs/
├── guides/             How-to instructions and tutorials
└── reference-material/ Technical reference, architecture, APIs
```

Use lowercase filenames with hyphens: `my-new-guide.md`. The file must have a `.md` extension.

## Updating sidebar.js

Every new page must be registered in `docs/sidebar.js`. Use this ID pattern:

- Guides: `prototype/guides/my-new-guide`
- Reference: `prototype/reference-material/my-new-guide`

The ID is the file path relative to the `docs/` folder, without the `.md` extension and with `prototype/` prepended. It must match exactly.

Example entry:

```javascript
{
  label: "My New Guide",
  type: "doc",
  id: "prototype/guides/my-new-guide",
}
```

## Testing locally

Test before you push. A failed build on GitHub shows up minutes after pushing; a local build shows it in seconds.

The test environment lives in the [AmpersandTarski.github.io repository](https://github.com/AmpersandTarski/AmpersandTarski.github.io). The [README](https://github.com/AmpersandTarski/AmpersandTarski.github.io#local-development--testing) has the complete instructions. In short:

```bash
cd ~/git/AmpersandTarski.github.io
cp -R ~/git/PrototypeFramework/docs tmp/prototype
docker compose up -d --build
```

Open http://localhost:8081 and verify your page is there. When done:

```bash
docker compose down
```

## Writing guidelines

- Active voice. Short sentences.
- Avoid unnecessary adjectives and bullet lists.
- Every code example should work as written.
- Refer to `memorybank/schrijfstijl-eisen.md` for the full style guide.
