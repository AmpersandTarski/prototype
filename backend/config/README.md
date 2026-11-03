# Configuration of the prototpye

## Project configuration
The prototype framework automatically loads the following configuration files, in the following order:
This order allows to have different environments with the same base project configuration
Configuration values overwrite each other, when specified in multiple files

1. `src/Ampersand/Misc/defaultSettings.yaml` -> framework default settings
2. `generics/settings.json` -> project specific settings from Ampersand compiler
3. `config/project.yaml` -> project specific framework settings

The configuration file has the following components:
* settings: a key/value pair list of config settings
* config: list of other config files that must be imported
* extensions: named list of extensions to be included, its bootstrapping and config files

## Environment variables
Finally certain settings can be set using environment variables.
These are loaded last and overwrite previous set settings.
* AMPERSAND_DEBUG_MODE -> global.debugMode
* AMPERSAND_PRODUCTION_MODE -> global.productionEnv
* AMPERSAND_SERVICE_KEY -> global.serviceKey
* AMPERSAND_DBHOST -> mysql.dbHost
* AMPERSAND_SERVER_URL -> global.serverURL
* AMPERSAND_DBNAME -> mysql.dbName
* AMPERSAND_SESSION_COOKIE_SECURE -> session.cookieSecure (`auto`, `true` or `false`)
* AMPERSAND_DOOR -> session.door (`proxy` switches the door on)
* AMPERSAND_DOOR_GROUPS_HEADER -> session.door.groupsHeader
* AMPERSAND_DOOR_TOKEN_ISSUER -> session.door.tokenIssuer
* AMPERSAND_DOOR_TOKEN_AUDIENCE -> session.door.tokenAudience
* AMPERSAND_DOOR_TRUSTED_PROXIES -> session.door.trustedProxies

The five `AMPERSAND_DOOR` variables let a prototype take the roles of its visitor from an identity service in front of it: from the groups header of a proxy, or from a bearer token that the framework checks itself. See the reference page "The Door" (OK-32).

`AMPERSAND_SERVICE_KEY` is the key with which a machine (a deployment pipeline, for instance) reaches the administrative endpoints while `AMPERSAND_PRODUCTION_MODE` is on. Such a request carries the key in the `X-Ampersand-Service-Key` header. Without a configured key, production mode blocks those endpoints for everybody. See the guide "Configuring Development and Production Environments".
