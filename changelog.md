# Changelog

## Unreleased changes
* [Issue 787](https://github.com/AmpersandTarski/Ampersand/issues/787) Remove header in interface templates ROWS, HROWS, ROWSNL, HROWSNL. Delete templates ROWSNH (no header)
* [Issue 487](https://github.com/AmpersandTarski/Ampersand/issues/487) Allow application meta-model export in OWL language (first partial implementation)
* [Issue 447](https://github.com/AmpersandTarski/Ampersand/issues/447) Fix issue with certain interface labels that interfere with Restangular method names
* [Issue 900](https://github.com/AmpersandTarski/Ampersand/issues/900) Bugfix redirect after session login timeout

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