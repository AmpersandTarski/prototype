# The Prototype Framework

The prototype framework provides the [runtime environment for Ampersand applications](https://github.com/AmpersandTarski/Prototype "Link to the github repository"). 
This documentation is intended for developers of the framework and more advanced users of Ampersand. It explains the key concepts, classes and project setup.

Please take note that this part of the documentation is under construction.


## Configuration of the prototpye

#### Logging configuration
* The logging configuration is loaded from a `logging.yaml` file in the config folder
* The specification of the logging file is defined in [Monolog Cascade](https://github.com/theorchard/monolog-cascade "Link to the GitHub repo")
* The file should be located at: `config/logging.yaml` (default/production)
* A debug logging configuration is available at: `config/logging.debug.yaml`
* You can specify the log configuration via ENV variable `AMPERSAND_LOG_CONFIG`. Set it to 'logging.yaml' (default) or 'logging.debug.yaml'

#### Project configuration
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

#### Environment variables
Finally certain settings can be set using environment variables.
These are loaded last and overwrite previous set settings.
* AMPERSAND_DEBUG_MODE -> global.debugMode
* AMPERSAND_PRODUCTION_MODE -> global.productionEnv
* AMPERSAND_SERVER_URL -> global.serverURL
* AMPERSAND_DATA_DIR -> global.dataPath
* AMPERSAND_DBHOST -> mysql.dbHost
* AMPERSAND_DBNAME -> mysql.dbName
* AMPERSAND_DBUSER -> mysql.dbUser
* AMPERSAND_DBPASS -> mysql.dbPass

#### Explanation of settings

* global.debugMode (env AMPERSAND_DEBUG_MODE)
  
  This setting determines how much debug information is provided to the user. When set to `true`, the detailed error message, including full stack trace is provided by the api and shown in the frontend. You can view the stack trace by opening (clicking on) the red error message.
  
  When you set the debug mode to `false`, error details are not displayed and the message states: `An error occured (debug information in server log files)`. The end user doesn't see what's wrong.

* global.productionEnv (env AMPERSAND_PRODUCTION_MODE)
  
  This setting determines which management functions are (not) allowed. Most important one is that in production mode `true` reinstalling the database is not allowed, never! This ensures that by accident all data is lost.
  
  This means that when you start the application in production mode `true`, and the database doesn't exist or is outdated (new tables/columns are needed), an exception is thrown. And you are stuck.

## File System

#### Introduction
**Currently we have limited support for working with files**


#### Usage in Ampersand application and prototype
To work with files and uploads you need to add a FileObject model and view to your ADL script:
```adl
CONCEPT FileObject ""
IDENT FileObjectName : FileObject (filePath)

RELATION filePath[FileObject*FilePath]
RELATION originalFileName[FileObject*FileName]
REPRESENT FilePath TYPE ALPHANUMERIC
REPRESENT FileName TYPE ALPHANUMERIC

VIEW FileObject : FileObject DEFAULT {
    apiPath : TXT "api/v1/file",
    filePath : filePath,
    fileName : originalFileName
} HTML TEMPLATE "View-FILEOBJECT.html" ENDVIEW
```

The template `View-FILEOBJECT.html` is a built-in template, but you can substitute this with your own template.

#### The Flysystem component
In the prototype framework backend we've implemented the [Flysystem library](https://flysystem.thephpleague.com/v1/docs/ "link to the documentation of the Flysystem library), which is a file system abstraction for PHP.

> "Flysystem is a filesystem abstraction library for PHP. By providing a unified interface for many different filesystems you’re able to swap out filesystems without application wide rewrites."

#### File system property of AmpersandApp object
A concrete implementation of a file system is set as property of the AmpersandApp object. You can access the file system like this to perform e.g. a read:

```php
<?php
/** @var AmpersandApp $app */
$fs = $app->fileSystem();
$fs->read('path/to/file.txt');
```

Other [methods](https://flysystem.thephpleague.com/v1/docs/usage/filesystem-api/ "Link to the API documentation of Flysystem") of the file system interface include `put()`, `has()`, `listContents()` and others. see: 

#### Using another file system implementation
By default a 'Local' file system adapter is added to the AmpersandApp. This read/writes files to the data directory.

If you want to use [another file system adapter](https://github.com/thephpleague/flysystem "more file system adapter implementations") you can use the setter method during bootstrap phase of the application. E.g. to use a SFTP file system:

```php
<?php

use League\Flysystem\Filesystem;
use League\Flysystem\Sftp\SftpAdapter;

$filesystem = new Filesystem(new SftpAdapter([
    'host' => 'example.com',
    'port' => 22,
    'username' => 'username',
    'password' => 'password',
    'privateKey' => 'path/to/or/contents/of/privatekey',
    'root' => '/path/to/root',
    'timeout' => 10,
]));

/** @var AmpersandApp $app */
$app->setFileSystem($filesystem);
```

## Event Dispatcher

#### Introduction
The prototype framework dispatches important events in order for other software projects to extend the framework. An example of such an event is the AtomEvent that is dispatched upon added and deleted atoms. 

The event dispatcher is the central object in the event dispatching system and is set as a property of the AmpersandApp class which is everywhere available in the codebase. You can access the event dispatcher like so:
```php
<?php
/** @var AmpersandApp $app */
$dispatcher = $app->eventDispatcher();
$dispatcher->dispatch();
```

#### The Symfony event dispatcher component
Currently we use the Symfony event dispatcher component as implementatation. Documentation can be found [here](https://symfony.com/doc/master/components/event_dispatcher.html#introduction).

> "The Symfony EventDispatcher component implements the Mediator and Observer design patterns to make all these things possible and to make your projects truly extensible."

#### Dispatched events
Below a list of dispatched events. More events will be added upon request. Please create an issue for that in the repository.

| Event class | Event name | Comment |
| ----------- | ---------- | ------- |
| AtomEvent   | ADDED | When a new (non-existing) atom is created
| AtomEvent   | DELETED | When an atom is deleted
| LinkEvent   | ADDED | When a new (non-existing) link is created
| LinkEvent   | DELETED | When a link is deleted
| TransactionEvent | STARTED | When a new Ampersand transaction is created/started
| TransactionEvent | COMMITTED | When an Ampersand transaction is committed (i.e. invariant rules hold)
| TransactionEvent | ROLLEDBACK | When an Ampersand transaction is rolled back (i.e. invariant rules do not hold)

#### Adding a listener
You can easily connect a listener to the dispatcher so that it can be notified/called when certain events are dispatched. A listener can be any valid [PHP callable](https://www.php.net/manual/en/language.types.callable.php).

See [documentation of Symfony](https://symfony.com/doc/master/components/event_dispatcher.html#connecting-listeners)

Below two examples of connecting a listener to the atom added event
```php
<?php
// Listener class method example
class MyListener
{
    public function methodToCall(AtomEvent $event) {}
}

$listener = new MyListener();
$dispatcher->addListener(AtomEvent::ADDED, [$listener, 'methodToCall']);
```

```php
<?php
// Closure example (i.e. anonymous function)
$dispatcher->addListener(AtomEvent::ADDED, function (AtomEvent $event) {
    /* code here */
});
```



## Generics folder
A compiled Ampersand script results in different model files that configure the prototype framework and must be placed in a specific. This folder is historically called 'generics'.

:::tip

More about the Ampersand compiler can be found [here](#ampersand-compiler).

:::


#### Generated files by Ampersand compiler
The generated config and ampersand model files include:
* concepts.json
* conjuncts.json
* interfaces.json
* mysql-installer.json
* relations.json
* roles.json
* rules.json
* settings.json
* views.json

## Ampersand compiler
The prototype framework depends on a compatible [Ampersand compiler](https://github.com/AmpersandTarski/Ampersand) to takes your Ampersand script (ADL files) and transform it into model files for the backend and generate a UI for the frontend.

Backend model files are generated in the [generics folder](https://github.com/AmpersandTarski/prototype/tree/main/generics). These are picked up by the backend implementation. See [README](https://github.com/AmpersandTarski/prototype/tree/main/generics/README.md) for more information about which files are generated.

Frontend UI is generated using the HTML and JS templates specified in [templates folder](https://github.com/AmpersandTarski/prototype/tree/main/templates/). These output components consisting of HTML views and JS controllers are put into [public/app/project folder](https://github.com/AmpersandTarski/prototype/tree/main/public/app/project/). These are picked up by the frontend application.

Together with the prototype framework, the backend model files and the generated frontend files provide for a complete prototype application.

#### Compiler version constraints
As of Ampersand compiler version 5.x, the compiler checks if its version is compatible with the deployed prototype framework. The prototype framework specifies the compatible compiler version(s) by means of semantic versioning constraints specified in [compiler-version.txt](https://github.com/AmpersandTarski/prototype/tree/main/generics/compiler-version.txt).

The compiler uses Haskell package [Salve](https://hackage.haskell.org/package/salve) to check the constraints. See documentation of Salve to understand the contraint language.

#### On-board Ampersand compiler
The [Docker file](https://github.com/AmpersandTarski/prototype/tree/main/Dockerfile) of the prototype framework includes a compatible Ampersand compiler in the container. Somewhere in the build script the following line is specified
> `COPY --from=ampersandtarski/ampersand:v4.6 /bin/ampersand /usr/local/bin`

This copies a pre-compiled and released Ampersand compiler from related image from Docker Hub.

You can make use of this compiler when building your own prototype application. Simply by extending the prototype-framework image and calling the compiler in a `RUN` statement like so:

```Dockerfile
FROM ampersandtarski/prototype-framework:v1.14

# The script content
COPY model /usr/local/project/

# Generate prototype application using Ampersand compiler
RUN ampersand proto /usr/local/project/script.adl \
  --proto-dir /var/www \
  --verbose

RUN chown -R www-data:www-data /var/www/log /var/www/data

WORKDIR /var/www
```

For a complete example and template folder for your project take a look at the [project-template repository](https://github.com/AmpersandTarski/project-template)

#### Adding custom HTML templates
If you are using your own `VIEW` definitions and custom `BOX` specifications with custom HTML templates in your Ampersand script files, you need to copy them to the [templates folder](https://github.com/AmpersandTarski/prototype/tree/main/templates/) BEFORE running the compiler.

You can add the following line to your Docker file:
```Dockerfile
# If you have custom templates, they need to be copied to where the Ampersand compiler expects them (/var/www)
RUN cp -r -v /usr/local/project/shared/templates /var/www/
```

#### Build with custom Ampersand compiler
For developers that work on the Ampersand compiler itself it may be convenient to copy a locally build Ampersand compiler into the prototype-framework. You can do this by
a) injecting the custom Ampersand compiler in a specific prototype project directly or b) locally building a new prototype-framework image.

#### Option A: inject custom compiler in prototype image
The quickest and most easiest way is to inject a custom Ampersand compiler directly in your prototype image. Update your Docker file and add the following line BEFORE running the compiler:

Custom compiler that is released on Github:
```Dockerfile
# Lines to add specific compiler version (from Github releases)
ADD https://github.com/AmpersandTarski/Ampersand/releases/download/Ampersand-v4.1.0/ampersand /usr/local/bin/ampersand
RUN chmod +x /usr/local/bin/ampersand
```

Custom compiler from specific (local) Docker image
```Dockerfile
# Line to add specific compiler version from some (local) Docker image
COPY --from=ampersandtarski/ampersand:local /bin/ampersand /usr/local/bin
```

Custom compiler from local binary
```Dockerfile
COPY /path/to/bin/ampersand /usr/local/bin
```

#### Option B: locally build prototype-framework image
For option A: replace the following line in the [Docker file](https://github.com/AmpersandTarski/prototype/tree/main/Dockerfile) of this repo:
> `COPY --from=ampersandtarski/ampersand:v4.6 /bin/ampersand /usr/local/bin`

Copy the compiler from a locally build Ampersand image ór from local bin directly instead of the `ampersandtarski/ampersand` image.


