# Changelog

We use [Semantic Versioning (https://semver.org/)](https://semver.org/)

Given a version number MAJOR.MINOR.PATCH, increment the:

1. MAJOR version when you make incompatible API changes,
2. MINOR version when you add functionality in a backwards compatible manner, and
3. PATCH version when you make backwards compatible bug fixes.

Additional labels for pre-release and build metadata are available as extensions to the MAJOR.MINOR.PATCH format. In our case this is e.g. `-rc.1`, `-rc.2`.

## v2.1.1 (unreleased)

* Compiler upgrade: the Ampersand compiler image is bumped from `20260322` to `20260609` in `Dockerfile` and `dev.Dockerfile`. The documented tag in `docs/guides/updating-and-releasing.md` and `docs/reference-material/prototype-framework.md` is updated to match.

* New feature: **multi-value (multi-column) spreadsheet import** — the runtime importer now handles multi-value cells the same way as the Ampersand compiler's compile-time importer.
  - A header cell `[Concept,]` (a concept name plus a delimiter, wrapped in square brackets) lets a single spreadsheet cell hold multiple atoms separated by that delimiter. Each value is trimmed and empty values are dropped.
  - RELATION approach: target multi-value, source multi-value (cartesian product), and flipped (`~`) relations. INTERFACE approach: multi-value sub-interface columns.
  - Block detection now matches the compiler's `isStartOfTable`: a bracketed cell in column A only starts a new block when the cell directly above is not bracketed, so a source `[Concept,]` on the concept row is not mistaken for the start of a new block.
  - Tests (standalone, on-demand — not wired into CI): `test/unit/ExcelImporterMultiValueTest.php` and `test/projects/import-multivalue/`.

* New feature: **admin mode / production environment** — developer and model-debugging interfaces are hidden by default.
  - Background: interfaces from `PrototypeContext.*` (Installer, population management, rule/relation inspectors, etc.) were always shown in the navigation menu, also in deployments meant for end users.
  - A new backend setting `global.productionEnv` is exposed to the frontend via the navbar response (`SessionController`). When set, `AngularJSApp::getNavMenuItems()` filters out every interface whose name starts with `PrototypeContext.`.
  - The frontend adds an **"Admin" toggle** (`p-inputSwitch`) in the topbar. `MenuService` keeps the admin-mode state in a `BehaviorSubject`, persisted in `sessionStorage`, and exposes `getNavbar()`.
  - `app.menu.component` rebuilds the menu from the navbar: developer interfaces (`PrototypeContext.*`) and the admin menu items are only shown when admin mode is active **and** the app is not in production. In production the toggle has no effect.
  - `Navbar` type (`navbar.interface.ts`) gains an optional `productionEnv` field.

* Code style: project-wide **Prettier reformatting** of the frontend (line wrapping, trailing commas, `var` → `const`, import wrapping). No behavioural changes. Added `eslint-plugin-storybook` dev dependency.

* New feature: **dedicated signals page** — violations of MAINTAIN rules are no longer shown as transient toast notifications.
  - Background: signal (process rule) violations were previously shown as blue toast messages in the corner of the screen. Individual violations were discarded; only the rule-level message was shown. Violations could not be inspected further.
  - The new approach replaces toast messages with a **dedicated page at `/signals`**:
    - All current signal violations are shown, grouped per rule (accordion), with the individual violation messages and clickable interface links per violation.
    - Data is read directly from the conjunct violation cache (`__conj_violation_cache__` database table) via a new backend endpoint `GET /admin/signals`. This endpoint filters violations to the currently active roles (same as `checkProcessRules()`).
    - The page has a "Ververs" button to refresh the data manually.
  - A **badge icon** (⚠ with count) appears in the topbar whenever there are open signal violations. Clicking it navigates to `/signals`.
  - `Signal` type in `notifications.interface.ts` is now exported so it can be used by other parts of the frontend.
  - New `SignalService` (`signal.service.ts`) maintains the current signal state as a reactive `BehaviorSubject<Signal[]>`, updated by the interceptor and the signals page.
  - `LoggingInterceptor`: instead of calling `sendSignalMessage()` for each signal, signals are now routed to `SignalService.update()`.
  - New backend: `GET /admin/signals` in `RuleEngineController::getSignalViolations()`. No extra role requirement — only violations relevant to the active roles are returned.

* `atomic-object`: `editAsText` input is now **deprecated** and has no effect.
  - The component always renders a `p-dropdown` with `[editable]="true"` for UNI updatable relations. This dropdown already supports typing-to-filter (same as the old text input), so no functionality is lost.
  - Eliminates the need for per-concept `Concept-X.html` template files that existed only to suppress `editAsText`.
  - The `editAsText` input remains accepted for backwards compatibility but is ignored.
  - `#uniUpdateText` template removed from `atomic-object.component.html`; dead code (`editTextValue`, `uniTextValue()`, `onEditTextChange()`, `finishTextEdit()`) removed from `atomic-object.component.ts`.

## v2.1.0 (13 May 2026)

* Bugfix `atomic-object`: in `cRud` mode with a non-UNI relation, existing atoms were never displayed — the column appeared empty even when backend data was present.
  - Root cause: the `#nonUni` template renders via `*ngFor="let object of selection()"`, but the `selection` signal was only initialised inside the reactive chain that is skipped for read-only mode (`canUpdate() = false`). So `selection` always stayed `[]`.
  - Fix: immediately before the early return, `selection.set([...this.data])` is called when `!isUni`. The UNI read-only path (`#uniRead`) reads `resource[propertyName]` directly and was unaffected.

* Bugfix `atomic-alphanumeric`: typing a new value in a standalone `setRelation` field (e.g. `projectMaster cRUd`) was immediately reverted after pressing Enter.
  - Root cause: when the autocomplete options endpoint (e.g. `resource/Employee`) returns HTTP 403, `options` was set to `[]` and the client-side validation guard `!options.includes(val)` rejected every typed value.
  - Fix: `options` is now typed `string[] | null`; `null` means "fetch failed / not loaded". Client-side validation is only applied when `options !== null` (i.e. a successful fetch returned a non-empty list). If `null`, the backend validates.
  - `*ngFor` in the datalist templates updated to `(options ?? [])` to avoid errors when `options` is `null`.

* Bugfix `AmpersandInterfaceComponent.delete()` / `BaseAtomicComponent.delete()`: clicking the trash icon deleted the atom in the backend but the UI kept showing the old value until a manual page refresh.
  - Fix: after the HTTP DELETE, `AmpersandInterfaceComponent.delete()` now does a GET to the root interface path and applies `mergeDeep` with the fresh backend data — identical to how `patch()` handles response content. This means: commit + invariants hold → atom disappears immediately; rollback due to invariant violation → atom stays visible (invariant checking remains fully intact).
  - `BaseAtomicComponent.delete()` simplified to a bare `.subscribe()` since the data refresh and `patched.emit()` are now handled upstream in `AmpersandInterfaceComponent.delete()`.

* Bugfix `BOX<FILTEREDDROPDOWN>`: did not work for scalar concept types (ALPHANUMERIC, Integer, Date) — the backend returns a plain string instead of a `{_id_, _label_}` object, so the dropdown could neither display nor submit values.
  - `atomic-object`: `normalizeAtom()` converts scalar values to ObjectBase shape; `handleUniDropdownChange()` + `onUniFilteredDropdownBlur()` handle blur correctly (exact match → patch, canCreate → create, else → reset); `selection` changed from private to public; `override delete()` for signal-aware UI updates
  - `Box-FILTEREDDROPDOWN.html` template: pass `$crud$`, `$if(exprIsUni)$isUni$endif$`, `$if(exprIsTot)$isTot$endif$` so template-level CRUD takes precedence over the interfaces.json lookup
  - `InterfacesJsonService.findSubObject()`: replaced SESSION-specific path parser with a lenient path-segment walker that supports both direct-resource paths and SESSION-based paths

* The atomic-alphanumeric component did not support independent Update and Delete operations: values could not be edited in-place, atoms could not be deleted, and there was no autocomplete to suggest existing values.
  - Rewritten templates with independent C/U/D controls per CRUD flag
  - Browser-native `<datalist>` autocomplete populated from the backend
  - `validateAndUpdate()` rejects unknown atoms when crud-`c` is not permitted
  - `BaseAtomicComponent`: `updateItem()` for non-UNI inline edits (remove old link + add new); `delete()` base implementation that deletes the atom and all its relations; workaround for compiler-generated `CRu+UNI` combination (treated as `cRu`)

* The `box-filtered-dropdown` test project lacked build infrastructure and did not cover all CRUD variants.
  - Added `Dockerfile` (builds with `--crud-defaults cRud`)
  - Extended `main.adl` with inline `setRelation` fields and test scenarios for all CRUD combinations

* Chore: exclude `test/projects/**/.proto/` from git (compiler-generated output, not to be committed)

## v2.0.8 (5 mei 2026)

* Bugfix import: show invariant violation details instead of generic "400 Bad Request" error when population import (in the excel importer) fails due to rule violations

## v2.0.7 (7 apr 2026)

* Documentation: add guide "Configuring Development and Production Environments" explaining `global.debugMode`, `global.productionEnv`, the configuration loading order, all environment variables, and a Docker Compose example for switching between environments
* Documentation: add BOX Template Development Guide for contributors — a comprehensive guide on creating and documenting new BOX templates
* Documentation: add PROPBUTTON Template reference page and expand the Frontend Components reference
* Documentation: extend sidebar navigation with all newly added guides and reference pages
* Add Box-PROPBUTTON template to generated templates
* Add `propbutton-unit-test` test project for testing PROPBUTTON behaviour
* Update `box-filtered-dropdown` test model
* Remove obsolete generated `project.module.ts`
* Dependency: bump phpoffice/phpspreadsheet from 1.29.10 to 1.30.0

## v2.0.6 (7 apr 2026)

* Dockerfile: make compiler image configurable via `ARG COMPILER_IMAGE` (default: `ampersandtarski/ampersand-compiler:20260322`)
* Bugfix execEngineWarshall: fix PHP 8 "Undefined array key" warnings in Warshall algorithm using null-coalescing operator (`?? false`)
* Bugfix logging: cap `FingersCrossedHandler` buffer at 500 entries to prevent memory exhaustion on large Excel imports
* Bugfix Box-FORM/TABLE templates: use `$targetLabel$` instead of `$target$` for "Add existing …" placeholder
* Bugfix BaseBoxComponent: fix stale tap-operators accumulating in dropdown after adding an item; refresh dropdown from scratch; fix filter on nullish data

## v2.0.5 (22 mrt 2026)

* Update Dockerfile: use dedicated `ampersandtarski/ampersand-compiler:20260322` stage instead of direct copy from `ampersandtarski/ampersand:v5.3.2`
* Add attributes sourceLabel and targetLabel to the JSON file for use in the prototype framework.
* Revert ONE-concept plug skip in AmpersandApp (introduced in v2.0.4, caused regression)

## v2.0.5 (18 mrt 2026)

* Update Dockerfile: use dedicated `ampersandtarski/ampersand-compiler:20260317` stage instead of direct copy from `ampersandtarski/ampersand:v5.3.2`
* Bugfix Box-FILTEREDDROPDOWN and Box-SELECT templates: correct `[property]` binding (remove erroneous `.setRelation` property access)
* Revert ONE-concept plug skip in AmpersandApp (introduced in v2.0.4, caused regression)

## v2.0.4 (6 mrt 2026)

* Bugfix: Handle concept ONE correctly — no SQL table, no plug, correct atom id

## v2.0.3 (4 mrt 2026)

* [PR 296](https://github.com/AmpersandTarski/prototype/pull/296) Add `editAsText` mode to the atomic-object component: UNI updatable object fields can now be edited as plain text input

## v2.0.2 (4 mrt 2026)

* [Issue 266](https://github.com/AmpersandTarski/prototype/issues/266) Box-FILTEREDDROPDOWN: refined implementation with TOT/UNI support, improved placeholders, always shows all items from selectFrom
* [Issue 262](https://github.com/AmpersandTarski/prototype/issues/262) Excel importer v2 with Storybook and Cypress tests
* [Issue 268](https://github.com/AmpersandTarski/prototype/issues/268) Fix memory leaks in frontend components

## v2.0.1 (24 aug 2025)

* **Breaking change** [Issue 156](https://github.com/AmpersandTarski/prototype/issues/156) Adapt to introduced namespace in ADL
  * Affects names and labels of all artefacts (concepts, relations, interfaces, rules, views, etc.)
  * Affects metadata from PrototypeContext — update application extensions and running environments accordingly
* [Issue 161](https://github.com/AmpersandTarski/prototype/issues/161) Bugfix ConceptNotDefinedException
* [Issue 180](https://github.com/AmpersandTarski/prototype/issues/180) Bugfix Php warning about undefined array key 'ifcPath'
* [Issue 218](https://github.com/AmpersandTarski/prototype/issues/218) Improve error reporting for exceptions in excel importer
* [Issue 221](https://github.com/AmpersandTarski/prototype/issues/221) Bugfix typescript type object property naming by surrounding view labels with quotes
* Add Box-FILTEREDDROPDOWN template (initial implementation)
* Improve import UI
* Migrate frontend test framework from Karma to Jest; add code coverage reporting
* Add regression tests to automatically check if ADL scripts generate valid backend and frontend files
* Upgrade to Ampersand compiler v5.3
* Align release policy with the Ampersand repository ([Issue 272](https://github.com/AmpersandTarski/prototype/issues/272))

## v1.19.0 (24 jul 2025)

* Maintenance release on v1 branch
* [Issue 246](https://github.com/AmpersandTarski/prototype/issues/246) Bugfix

## v2.0.0-alpha.5 (10 jan 2025)

* Fix php 8.3 compatibility for ResourceEvent

## v2.0.0-alpha.4 (25 dec 2024)

* Fix php 8.3 compatibility
* Dispatch resource events for PUT, PATCH, POST and DELETE actions

## v2.0.0-alpha.3 (3 dec 2024)

* [Issue 210](https://github.com/AmpersandTarski/prototype/issues/210) Bugfix using TableType enum for method to empty a relation population
* Bugfixes for using Flysystem v3

## v2.0.0-alpha.2 (16 nov 2024)

* Update Flysystem library to v3

## v2.0.0-alpha.1 (1 november 2024)

* Library updates (in particular Symfony)
* Refactoring configuration of logger

## v1.18.0 (27 april 2024)

* [Issue 79](https://github.com/AmpersandTarski/prototype/issues/79) Add support for delimited multi value columns in excel importer
* [Issue 115](https://github.com/AmpersandTarski/prototype/issues/115) Bugfix name ConceptNotDefinedException to match class filename
* [Issue 165](https://github.com/AmpersandTarski/prototype/issues/165) Only show warning 'Generated model is changed...'on startup of browser app
* Catch FileNotFoundException and log warning when corresponding file of deleted FileObject is not found on filesystem
* Skip tracking affected relations and concepts for RELATION lastAccess[SESSION*DateTime]
* Add codebase of new developed frontend. Not integrated in building image yet.
  * This is a major change. Justifies major version update to v2.x

## v1.17.0 (24 may 2023)

* [Issue 91](https://github.com/AmpersandTarski/prototype/issues/91) Display errors/exceptions in the frontend that occur before the API ExceptionHandler is initialized. E.g. database connection failure
* [Issue 97](https://github.com/AmpersandTarski/prototype/issues/97) Fix issue with Excel importer date value 0. Improve error message with cell location
* [Issue 100](https://github.com/AmpersandTarski/prototype/issues/100) Add admin API to cleanup metapopulation (i.e. removing interfaces that don't exists anymore)
* Add workflow to automatically trigger documentation update for our landingpage

## v1.16.0 (27 july 2022)

* [Issue 92](https://github.com/AmpersandTarski/prototype/issues/92) Bugfix issue with exec-engine function to merge atoms that not exist (anymore)
* Update to Ampersand compiler v4.7.z

## v1.15.1 (5 may 2022)

* Bugfix default values for new atom

## v1.15.0 (9 apr 2022)

* [Issue 90](https://github.com/AmpersandTarski/prototype/issues/90) Bugfix php warning when logging non-string setting value
* **Backward incompatible changes**
  * Remove Ampersand specific RotatingFileHandler for logger. Logs are written to stderr and stdout by default. Projects can add their own handlers
  * Config 'global.dataPath' must now be an absolute path. Method Settings::getDataDirectory() now returns trailing directory separator
* Bugfix undefined parts (settings and/or extensions) of a settings.yaml file
* Add env variable AMPERSAND_DATA_DIR to set global.dataPath config

## v1.14.1 (6 feb 2022)

* Bugfix wrong return type of method Session::getSessionAccount()

## v1.14.0 (30 jan 2022)

* **Note! Contains backward incompatible changes for projects that extend or customize the backend framework**
* [Issue 70](https://github.com/AmpersandTarski/prototype/issues/70) Improve exec engine error message
* [Issue 88](https://github.com/AmpersandTarski/prototype/issues/88) Initialize model before registering current ampersand model version during reinstall of application
* UI Fix for longer notification (error) messages. Message now overflows on new line
* Add missing parameter and return type declarations for all methods. Remove unneeded docblock comments
* Add class property type declarations. Remove unneeded docblock comments
* Refactor use enums for TableType, RuleType, SrcOrTgt, MenuType, concept TType
* Simplify and unify exception handling
* Remove default response Content-Type header (was 'application/json;charset=utf-8'). Must be set per API (group) seperately
* Remove/refactor global variable $angularApp. Use MenuItemRegistry::addMenuItem() and AmpersandApp::frontend() instead
* Refactor bootstrapping of framework, including API definitions. PHP files with API routes in './bootstrap/api' are bootstrapped automatically

## v1.13.0 (10 dec 2021)

* [Issue 68](https://github.com/AmpersandTarski/prototype/issues/68) Update Docker image to PHP v8.1. Make backend compatible with php 8.x
* **Backward incompatible change** Refactor Resource::all() to return array of Resource instead ResourceList object
* **Backward incompatible change** Remove ArrayAccess methods from Resource class
* Add methods Resource::value() and Resource::values() to replace ArrayAccess methods offsetGet()

## v1.12.0 (2 dec 2021)

* Add transaction reference to AtomEvent and LinkEvent
* Update to Ampersand compiler from v4.5.z to v4.6.z. **Backward incompatible change**. See compiler [releases](https://github.com/AmpersandTarski/Ampersand/releases)

## v1.11.0 (11 nov 2021)

* [Issue 1189](https://github.com/AmpersandTarski/Ampersand/issues/1189) Add support for DEFAULT SRC/TGT atoms for RELATION statement
* [Issue 1208](https://github.com/AmpersandTarski/Ampersand/issues/1208) Fix warning when a reinstall of the database may be needed based on model hash
* Use main branch instead of master
* Update to Ampersand compiler v4.5.z

## v1.10.4 (16 sept 2021)

* [Issue 1170](https://github.com/AmpersandTarski/Ampersand/issues/1170) Rename Concept::inSameClassificationTree to inSameClassificationBranch. Adapt error messages.
* [Issue 1212](https://github.com/AmpersandTarski/Ampersand/issues/1212) Change database collation to utf8mb4_nopad_bin to fix issue with trailing whitespace
* Update build-push to docker hub. We are moving to master branch only, no development anymore
* Fix a warning in the devcontainer

## v1.10.3 (14 aug 2021)

* Bugfix issue with unhandled exception in frontend when API patch returns error
* Bugfix redirect to previous url after navigating manually to login page

## v1.10.2 (12 aug 2021)

* [Issue 993](https://github.com/AmpersandTarski/Ampersand/issues/993) Don't call API to save field when value is unchanged
* Provide more specific error message when file upload failed (e.g. exceeded maximum filesize)

## v1.10.1 (14 jul 2021)

* Library update indicated by dependabot
* Add helper methods to Population class

## v1.10.0 (14 may 2021)

* Remove OAuth module. Should be implementated by applications that use the framework instead.
* Remove frontend package from dist folder. Package is now build every time.
* Update frontend dev libraries (npm update)
* Update backend libraries (composer update)

## v1.9.3 (14 apr 2021)

* [Issue 1155](https://github.com/AmpersandTarski/Ampersand/issues/1155) Fix issue with MrgAtom exec-engine function in case of non-existing atoms
* Hotfix bug introduced in v1.9.2 related to query data optimization

## v1.9.2 (13 apr 2021)

* Fix query data optimization in case of transformation from Atom to Resource. Improves performance by reducing amount of queries significantly.

## v1.9.1 (1 apr 2021)

* [Issue 1151](https://github.com/AmpersandTarski/Ampersand/issues/1151) Bugfix boolean population transformation to database
* [Issue 1152](https://github.com/AmpersandTarski/Ampersand/issues/1152) Handle violation segments for expressions with multiple targets
* Add OAuth2 handler for Microsoft accounts

## v1.9.0 (19 feb 2021)

* [Issue 1103](https://github.com/AmpersandTarski/Ampersand/issues/1103) Adapt templates TABS and FORM: by default don't show 'hamburger' item to nav to other interfaces. Add template attribute 'showNavMenu'
* [Issue 1143](https://github.com/AmpersandTarski/Ampersand/issues/1143) For template TABS change interpretation of hideOnNoRecords from a single TAB to the complete TABSET
* [Issue 1144](https://github.com/AmpersandTarski/Ampersand/issues/1144) For templates TABS and FORM add attribute hideSubOnNoRecords to hide a single sub interface when result set is empty
* [Issue 1146](https://github.com/AmpersandTarski/Ampersand/issues/1146) For template TABLE hide complete table (including title) when attribute hideOnNoRecords is set
* [Issue 1148](https://github.com/AmpersandTarski/Ampersand/issues/1148) Allow exec-engine delpair function to handle multiple src and/or tgt atoms

## v1.8.6 (7 feb 2021)

* Update EasyRdf library to v1.x

## v1.8.5 (28 jan 2021)

* Set content-type to mime type when downloading files using /file api. Let browser determine if content can be loaded in the browser

## v1.8.4 (8 jan 2021)

* Use Ampersand compiler v4.1.3. First time we use an explicit version number instead of development tag
* Meta population installer: cleanup interface atoms from database when they are not defined in the Ampersand model (anymore)

## v1.8.3 (5 jan 2021)

* Bugfix auto create (crudC) for non-ident interface expressions

## v1.8.2 (1 dec 2020)

* Try to create missing data directory
* Remove unused extensions folder from Phan static analysis check
* Update dev dependency Phan to v3.x

## v1.8.1 (1 december 2020)

* Bugfix word-wrap css class

## v1.8.0 (30 november 2020)

* **Breaking change**: OAuthLogin module is not an extension anymore, but built in the framework.
  * Changes are required in configuration files, see [readme](./config/README.md)
  * Implemented state token to prevent CSRF
  * Removed whole CA certificates stuff, because this is already provided and configured by the php-apache docker image
* [Issue 1125](https://github.com/AmpersandTarski/Ampersand/issues/1125) Use UUIDs when automatically creating atom identifiers instead of a timestamp
* Bugfix link to population exporter API
* Add administrator api functionality to regenerate atom identifiers for all concepts or a specific concept

## v1.7.1 (9 november 2020)

* [Issue 1104](https://github.com/AmpersandTarski/Ampersand/issues/1104) Built-in template revisions
* Add `hideOnNoRecords` functionality to TABS template

## v1.7.0 (12 august 2020)

* [Issue 1070](https://github.com/AmpersandTarski/Ampersand/issues/1070) Don't pick up database configurations from Ampersand compiler anymore
* [Issue 1096](https://github.com/AmpersandTarski/Ampersand/issues/1096) Show more usefull error message when composer autoloader file can not be found
* [Issue 1097](https://github.com/AmpersandTarski/Ampersand/issues/1097) Implement file system interface to allow for other storage solutions
* [Issue 1098](https://github.com/AmpersandTarski/Ampersand/issues/1098) Implementation of event dispatcher. Started with add/del atom and link and transaction related events
* Removed Hook class implementation. Replaced by event dispatcher

## v1.6.1 (24 july 2020)

* [Issue 1067](https://github.com/AmpersandTarski/Ampersand/issues/1067) Update CI scripts. Add script to build-push to Docker Hub instead of Github package repository
* Update to PHP version 7.4
* Update cacert.pem file for OAuthLogin extension. Automatically download latest version during Docker build

## v1.6.0 (18 july 2020)

* Introduction of BOX attributes functionality
  * See [readme about templates](./templates/README.md)
  * Template `FORM` replaces `ROWS`, `ROWSNL`, `HROWS`, `HROWSNL`
  * Template `TABLE` replaces `COLS`, `SCOLS`, `COLSNL`, `HCOLS` and `SHCOLS`
  * Template `RAW` replaces `DIV`, `CDIV`, `RDIV`

## v1.5.1 (12 may 2020)

* Upgrade unmaintained phpexcel package to newer library phpoffice/phpspreadsheet
* Allow to configure database username and password using environment variables

## v1.5.0 (21 april 2020)

* [Issue 1009](https://github.com/AmpersandTarski/Ampersand/issues/1009) Fix 404 session not found when session is expired
* Bugfix issue due to not taking into account [php's short circuit evaluation](https://stackoverflow.com/questions/5694733/does-php-have-short-circuit-evaluation)
* Bugfix uncaught AccessDeniedException for patches on top-level interface atoms
* Improve stack trace. Now also showing trace of previous errors/exceptions
* Add config for setting data dir (needed for containerizing Ampersand backend application). Uploads folder is now fixed relative to data folder
* Add option to set certain configurations via environment variables. Starting with AMPERSAND_DEBUG_MODE, AMPERSAND_PRODUCTION_MODE, AMPERSAND_DBHOST and AMPERSAND_SERVER_URL, AMPERSAND_DBNAME
* Simplify ways to specify configurations. Removed recent option of environment folder structure (was introduced in v1.4.0)
* OAuthLogin extension: allow to specify urls in config file relative to global.serverURL
* Bugfix. When loading configuration files, first load extensions and after that additional config files
* Add devcontainer configuration to repository
* Don't use php's $_SESSION records anymore. This doesn't fit with containerized design priciples like process disposability principle

## v1.4.0 (3 january 2020)

* Bugfix issue with API interfaces shown in UI to solve signal violations. Caused by wrongly placed parentheses.
* Add sort values for all BOX templates that start with the char 'S' (for SORT). Instead of only for the SCOLS, SHCOLS and SPCOLS templates.
* [Issue 1005](https://github.com/AmpersandTarski/Ampersand/issues/1005) Bugfix deadlock due to un-defined interfaces
* [Issue 426](https://github.com/AmpersandTarski/Ampersand/issues/426) Add support for optimized VIEW expression queries using injection of _SRCATOM
* Add Dockerfile to build Ampersand prototype framework image that can be used by containerized prototype apps
* Improve configuration of prototype for different environments (dev/prod/...). See [readme](./config/README.md)
* Simplify logging. Log to stdout and stderr to work with containerized prototype apps
* Add logs for add/rm/del atom and add/del links
* Add CI using Github Actions to build docker image and perform php static analysis using phan
* [Issue 940](https://github.com/AmpersandTarski/Ampersand/issues/940) Remove company logos from footer
* [Issue 951](https://github.com/AmpersandTarski/Ampersand/issues/951) Refresh page after role (de)select
* [Issue 983](https://github.com/AmpersandTarski/Ampersand/issues/983) Increase default timeout for installing the application to 5 min
* [Issue 1016](https://github.com/AmpersandTarski/Ampersand/issues/1016) Fix for invariant violation in metapopulation that will be resolved by initial population. Installing application is now in a single transaction

## v1.3.0 (15 july 2019)

* Bugfix error message in case of network/connection error
* Many bugfixes (see commit history)
* Implement dynamic RBAC. Accessible interfaces for a given role are now queries from database instead of generated json files

## v1.2.0 (30 april 2019)

* [Issue 787](https://github.com/AmpersandTarski/Ampersand/issues/787) Remove header in interface templates ROWS, HROWS, ROWSNL, HROWSNL. Delete templates ROWSNH (no header)
* [Issue 487](https://github.com/AmpersandTarski/Ampersand/issues/487) Allow application meta-model export in OWL language (first partial implementation)
* [Issue 447](https://github.com/AmpersandTarski/Ampersand/issues/447) Fix issue with certain interface labels that interfere with Restangular method names
* [Issue 583](https://github.com/AmpersandTarski/Ampersand/issues/583) Mark required fields in interfaces (implemented in all atomic/leaf templates)
* [Issue 578](https://github.com/AmpersandTarski/Ampersand/issues/578) Implement meta-model (and meat grinder) for navigation menu
* [Issue 900](https://github.com/AmpersandTarski/Ampersand/issues/900) Bugfix redirect after session login timeout
* [Issue 905](https://github.com/AmpersandTarski/Ampersand/issues/905) Legacy browser support. Added [Babeljs](https://babeljs.io/) transpiler
* Move initialization of all object definitions (Rule, Role, Concept, Relation, etc) to Model class
* Add functionality to export subset of population

## v1.1.1 (21 january 2019)

* Hotfix: bug in delete query when removing multiple links at once

## v1.1.0 (18 january 2019)

* **Major refactoring of backend implementation of prototype framework**
* Minimum requirement of php version >= 7.1 (was >= 7.0)
* Update OAuthLogin extension: use Linkedin API v2, because v1 is phases out by 2019-03-01. Note! in project config file the linkedin 'apiUrl' must be updated to: "https://api.linkedin.com/v2/emailAddress?q=members&projection=(elements*(handle~))'
* [Issue 866](https://github.com/AmpersandTarski/Ampersand/issues/866) Automatically reload javascript resources when needed
* [Issue 792](https://github.com/AmpersandTarski/Ampersand/issues/792) Support for TXT in interface definitions
* [Issue 819](https://github.com/AmpersandTarski/Ampersand/issues/819) Refactor initialization phase of Ampersand application. Config -> Init -> Session -> Run
* [Issue 802](https://github.com/AmpersandTarski/Ampersand/issues/802) Little Things In Prototoypes/Frontend
* [Issue 829](https://github.com/AmpersandTarski/Ampersand/issues/829) Fix issue with database initialization
* Don't automatically create database table. Installer is required.
* Remove dependency injection container for AmpersandApp (for now)
* Move Monolog dependency from Logger class to localsettings
* [Issue 823](https://github.com/AmpersandTarski/Ampersand/issues/823) Add option to ignore invariant violations for default population.
* [Issue 822](https://github.com/AmpersandTarski/Ampersand/issues/822) Fix importer flag on error/invariant violations
* Fix issue with session that timed out after default expiration time of 24 min regardless of user activity.
* Security fix: renew session id after login.
* Interfaces defined with API keyword (as synonym for INTERFACE) are filtered out in navigation menu and don't have UI artefacts (view+controller) anymore
* Improve logging of php errors
* [Issue 395](https://github.com/AmpersandTarski/Ampersand/issues/395) Add ExecEngine termination command
* [Issue 143](https://github.com/AmpersandTarski/Ampersand/issues/143) Introduction of service runs (special kind of exec engines that must be called explicitly)

## v1.0.1 (27 july 2018)

Several bugfixes. See commit messages.

## v1.0.0 (26 june 2018)

Initial version of Ampersand prototype framework in its own repository. Earlier the complete prototype framework was included (zipped) in the [Ampersand generator](https://github.com/AmpersandTarski/Ampersand). As of this release the prototype framework, including a PHP backend and a HTML/JS frontend implementation are maintained in a [seperate repository](https://github.com/AmpersandTarski/Prototype). This enables us to add automated tests and CI/CD more easily. For more background see related issue [Ampersand #756](https://github.com/AmpersandTarski/Ampersand/issues/756).
