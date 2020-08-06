# Changelog

## Unreleased changes
* [Issue 1096](https://github.com/AmpersandTarski/Ampersand/issues/1096) Show more usefull error message when composer autoloader file can not be found
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
* Update OAuthLogin extension: use Linkedin API v2, because v1 is phases out by 2019-03-01. Note! in project config file the linkedin 'apiUrl' must be updated to: 'https://api.linkedin.com/v2/emailAddress?q=members&projection=(elements*(handle~))'
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