# Updating and Releasing the Prototype Framework

This guide describes how to release a new version of the Ampersand Prototype Framework. It covers the manual steps a maintainer must take before automation takes over, and the points to watch.

## How the release process works

A release consists of three parts:

1. A maintainer creates a GitHub Release with a version tag in GitHub.
2. The `release.yml` workflow triggers automatically on the `created` event of that release.
3. The workflow builds and publishes artefacts to Docker Hub and attaches them to the GitHub Release.

The workflow does not create the release itself. That step is always manual.

## Versioning

The framework uses [semantic versioning](https://semver.org/): `vMAJOR.MINOR.PATCH`.

The Docker Hub metadata action derives three tags from the version automatically:
- `v{major}.{minor}.{patch}` — the exact release
- `v{major}.{minor}` — the latest patch for this minor version
- `v{major}` — the latest release for this major version

Choose the version number before creating a GitHub Release, because the tag drives all downstream tags on Docker Hub.

## Before creating a release

Before you create the GitHub Release, check and update these items manually.

### 1. Update the changelog

The file `changelog.md` in the root of the repository is the record of changes per release. Add an entry for the new version before releasing. The `release.yml` workflow attaches this file as an artefact to the GitHub Release. If you skip this step, the changelog attached to the release is out of date.

### 2. Check compiler version compatibility

The file `backend/generics/compiler-version.txt` contains the semantic version constraint that describes which Ampersand compiler versions are compatible with this framework release. Check whether the constraint is still correct for the compiler version that ships in the `Dockerfile`.

The `Dockerfile` contains a line like:
```dockerfile
COPY --from=ampersandtarski/ampersand:v4.6 /bin/ampersand /usr/local/bin
```

If you update the bundled compiler version, update `compiler-version.txt` accordingly. The Ampersand compiler uses the [Salve](https://hackage.haskell.org/package/salve) constraint language to verify compatibility at runtime.

### 3. Verify the main branch passes CI

The `continuous-integration.yml` and `frontend-tests.yml` workflows run on every push to `main`. Confirm that all checks pass before creating a release from that commit.

## Testing the framework locally before releasing

You can build and run the framework locally before pushing, using the Docker Compose setup in the root of the repository.

### Start the local environment

The `compose.yaml` builds the prototype container from `dev.Dockerfile` and mounts the entire repository as a volume on `/var/www`. Changes to backend PHP source files are therefore active immediately without a container rebuild.

```bash
docker compose up -d --build
```

This starts three containers: the prototype application on port 80, MariaDB on port 3306, and phpMyAdmin on port 8080.

### Compile a test project

The script `generate.sh` compiles an Ampersand test project and builds the Angular frontend:

```bash
./generate.sh                        # uses hello-world by default
./generate.sh box-filtered-dropdown  # specific test project
./generate.sh hello-world main.adl   # explicit project and model
```

The script runs the Ampersand compiler inside the running `prototype` container, builds the frontend with `npm run build:dev`, copies the output to `html/`, and makes the result available at http://localhost.

Test projects are in `test/projects/`: `hello-world`, `box-filtered-dropdown`, `import`, and `project-administration`.

### What to test per change type

For backend changes (PHP in `backend/src/`), the volume mount makes the changes active directly. No container rebuild is needed.

For frontend changes (Angular in `frontend/src/`), run `./generate.sh` again to rebuild, or run `cd frontend && npm run build:dev -- --watch` for continuous rebuilds during development.

For changes to the `Dockerfile` itself, run `docker compose up -d --build` to rebuild the container image.

### Automated tests

Besides manual testing, three automated test suites are available:

- Frontend unit tests: `cd frontend && npm run test`
- Frontend Cypress end-to-end tests: `cd frontend && npm run cypress:open` — requires the application running on localhost
- Storybook component tests: `cd frontend && npm run storybook`, then `npm run test:stories`

The GitHub Actions CI workflows (`continuous-integration.yml` and `frontend-tests.yml`) run the same tests on every push to `main`. Check that these pass before creating a release.

## Creating the GitHub Release

1. Go to the GitHub repository at https://github.com/AmpersandTarski/prototype.
2. Navigate to **Releases** → **Draft a new release**.
3. Create a new tag with the version number, for example `v1.15`. Point it at the `main` branch or the specific commit you want to release.
4. Write release notes describing what changed. Reference the `changelog.md` entries.
5. Click **Publish release**.

This triggers the `release.yml` workflow.

## What the workflow does automatically

The `release.yml` workflow runs four jobs.

**add-release-notes** attaches `changelog.md` to the GitHub Release as a downloadable artefact.

**docker-hub** builds the Docker image from the `framework` build target in the `Dockerfile` and pushes it to [Docker Hub](https://hub.docker.com/r/ampersandtarski/prototype-framework) with the semver tags derived from the release tag.

**build-framework-archive** installs PHP dependencies with `composer install --no-dev` and packages the project as a `.tar.gz` and a `.zip` archive. It excludes `.git`, `test/`, `docs/`, `.devcontainer/`, `.vscode/`, `dev.Dockerfile`, and `dev-container-test.sh`.

**add-release-artifacts** attaches the archives to the GitHub Release as downloadable artefacts named `ampersand-prototype-framework-{version}.tar.gz` and `ampersand-prototype-framework-{version}.zip`.

## Branch `main` builds a rolling `main` tag on Docker Hub

The `build-push-to-docker-hub.yml` workflow runs on every push to `main` and publishes the image with the `main` tag to Docker Hub. This is separate from the release workflow and does not create a versioned tag. Use the `main` tag for development purposes only.

## Points to watch

**Composer dependencies.** The release archive runs `composer install --no-dev`. Check that `composer.json` and `composer.lock` are up to date before releasing.

**Frontend build.** The release workflow does not run `npm install` or build the frontend. The frontend build output must already be present in the repository, or downstream projects must build it themselves. Verify this before releasing.

**Docker Hub secrets.** The workflows use `DOCKER_HUB_USERNAME` and `DOCKER_HUB_PASSWORD` secrets configured in the repository settings. If these are invalid, the Docker push step fails silently at the login step. Check the secrets if Docker Hub pushes fail.

**Release policy.** There is no formal release policy. The repository maintainers decide when to release. Consider aligning releases with Ampersand compiler releases, because the compiler constraint in `compiler-version.txt` determines which compiler versions users can pair with a given framework release.

## After the release

After a successful release, check:

- The new image tags appear on [Docker Hub](https://hub.docker.com/r/ampersandtarski/prototype-framework/tags).
- The GitHub Release page shows the artefacts (`changelog.md`, `.tar.gz`, `.zip`).
- The `docs` update workflow (`trigger-docs-update.yml`) has triggered and the documentation at https://ampersandtarski.github.io/ is up to date.
