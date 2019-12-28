# Changelog

## Unreleased changes
* Bugfix issue with API interfaces shown in UI to solve signal violations. Caused by wrongly placed parentheses.
* Add sort values for all BOX templates that start with the char 'S' (for SORT). Instead of only for the SCOLS, SHCOLS and SPCOLS templates.
* [Issue 1005](https://github.com/AmpersandTarski/Ampersand/issues/1005) Bugfix deadlock due to un-defined interfaces
* [Issue 426](https://github.com/AmpersandTarski/Ampersand/issues/426) Add support for optimized VIEW expression queries using injection of _SRCATOM
* Add Dockerfile to build Ampersand prototype framework image that can be used by containerized prototype apps
* Improve configuration of prototype for different environments (dev/prod/...). See [readme](./config/README.md)
* Simplify logging. Log to stdout and stderr to work with containerized prototype apps
* Add logs for add/rm/del atom and add/del links
* [Issue 940](https://github.com/AmpersandTarski/Ampersand/issues/940) Remove company logos from footer
* [Issue 951](https://github.com/AmpersandTarski/Ampersand/issues/951) Refresh page after role (de)select

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