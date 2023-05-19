---
title: The Prototype Framework
---

# Ampersand prototype framework

The [prototype framework](https://github.com/AmpersandTarski/Prototype) provides the runtime environment for Ampersand applications. 
This documentation is intended for developers of the framework and more advanced users of Ampersand. It explains the key concepts, classes and project setup.

Please take note that this part of the documentation is under construction.


## Configuration of the prototpye

### Logging configuration
* The logging configuration is loaded from a `logging.yaml` file in the config folder
* For the specification of the logging file, see: https://github.com/theorchard/monolog-cascade
* The file should be located at: `config/logging.yaml` (default/production)
* A debug logging configuration is available at: `config/logging.debug.yaml`
* You can specify the log configuration via ENV variable `AMPERSAND_LOG_CONFIG`. Set it to 'logging.yaml' (default) or 'logging.debug.yaml'

### Project configuration
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

### Environment variables
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

### Explanation of settings

* global.debugMode (env AMPERSAND_DEBUG_MODE)
  
  This setting determines how much debug information is provided to the user. When set to `true`, the detailed error message, including full stack trace is provided by the api and shown in the frontend. You can view the stack trace by opening (clicking on) the red error message.
  
  When you set the debug mode to `false`, error details are not displayed and the message states: `An error occured (debug information in server log files)`. The end user doesn't see what's wrong.

* global.productionEnv (env AMPERSAND_PRODUCTION_MODE)
  
  This setting determines which management functions are (not) allowed. Most important one is that in production mode `true` reinstalling the database is not allowed, never! This ensures that by accident all data is lost.
  
  This means that when you start the application in production mode `true`, and the database doesn't exist or is outdated (new tables/columns are needed), an exception is thrown. And you are stuck.

## File System

### Introduction
**Currently we have limited support for working with files**


### Usage in Ampersand application and prototype
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

### The Flysystem component
In the prototype framework backend we've implemented the Flysystem library, which is a file system abstraction for PHP.

> "Flysystem is a filesystem abstraction library for PHP. By providing a unified interface for many different filesystems you’re able to swap out filesystems without application wide rewrites."

Read more about this library here: https://flysystem.thephpleague.com/v1/docs/

### File system property of AmpersandApp object
A concrete implementation of a file system is set as property of the AmpersandApp object. You can access the file system like this to perform e.g. a read:

```php
<?php
/** @var AmpersandApp $app */
$fs = $app->fileSystem();
$fs->read('path/to/file.txt');
```

Other methods of the file system interface include `put()`, `has()`, `listContents()` and others. For API documentation see: https://flysystem.thephpleague.com/v1/docs/usage/filesystem-api/

### Using another file system implementation
By default a 'Local' file system adapter is added to the AmpersandApp. This read/writes files to the data directory.

If you want to use another file system adapter you can use the setter method during bootstrap phase of the application. E.g. to use a SFTP file system:

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

For more file system adapter implementations see: https://github.com/thephpleague/flysystem


## Event Dispatcher

### Introduction
The prototype framework dispatches important events in order for other software projects to extend the framework. An example of such an event is the AtomEvent that is dispatched upon added and deleted atoms. 

The event dispatcher is the central object in the event dispatching system and is set as a property of the AmpersandApp class which is everywhere available in the codebase. You can access the event dispatcher like so:
```php
<?php
/** @var AmpersandApp $app */
$dispatcher = $app->eventDispatcher();
$dispatcher->dispatch();
```

### The Symfony event dispatcher component
Currently we use the Symfony event dispatcher component as implementatation. Documentation can be found [here](https://symfony.com/doc/master/components/event_dispatcher.html#introduction).

> "The Symfony EventDispatcher component implements the Mediator and Observer design patterns to make all these things possible and to make your projects truly extensible."

### Dispatched events
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

### Adding a listener
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



## Interface templates
Templates are used to generate prototype user interfaces based on Ampersand INTERFACE definitions.
There are 3 types of templates:
1. Box template -> 
2. Atomic templates -> used for interface leaves nodes (without a user defined VIEW specified)
3. View templates -> used for user defined views

e.g.
```adl
INTERFACE "Project" : I[Project] cRud BOX           <-- the default FORM box template is used
  [ "Name"                : projectName             <-- the default atomic template for a alphanumeric type is used
  , "Description"         : projectDescription
  , "(Planned) start date": projectStartDate 
  , "Active"              : projectActive
  , "Current PL"          : pl <PersonEmail>        <-- a user defined PersonEmail view template is used
  , "Project members"     : member BOX <TABLE>      <-- the built-in TABLE box template is used
    [ "Name"              : personFirstName
    , "Email"             : personEmail
    ]
  ]
```

### BOX templates

#### FORM (=default BOX template)
Interface template for forms structures. For each target atom a form is added. The sub interfaces are used as form fields.
This template replaces former templates: `ROWS`, `HROWS`, `HROWSNL` and `ROWSNL`

Usage `BOX <FORM attributes*>`

For root interface boxes automatically a title is added which equals the interface name. To hide this title use `noRootTitle` attribute.

Examples:
- `BOX <FORM>`
- `BOX <FORM hideLabels>`
- `BOX <FORM hideOnNoRecords>`
- `BOX <FORM title="Title of your form">`
- `BOX <FORM hideLabels hideOnNoRecords noRootTitle>`

Possible attributes are:
| attribute | value | description |
| --------- | ----- | ----------- |
| hideOnNoRecords | n.a. | when attribute is set, the complete form is hidden in the interface when there are no records |
| hideSubOnNoRecords | n.a. | when attribute is set, specific form fields (i.e. sub interfaces) that have no records are hidden |
| hideLabels | n.a. | when attribute is set, no field labels are shown |
| title | string | title / description for the forms. Title is shown above the form |
| noRootTitle | n.a. | hides title; usefull for root interface boxes where a title is automatically is added |
| showNavMenu | n.a. | show 'hamburger' button to navigate to other interfaces designed for target concept of expression |

#### TABLE
Interface template for table structures. The target atoms of the interface make up the records / rows. The sub interfaces are used as columns.
This templates replaces former templates: `COLS`, `SCOLS`, `HCOLS`, `SHCOLS` and `COLSNL`

Usage: `BOX <TABLE attributes*>`

For root interface boxes automatically a title is added which equals the interface name. To hide this title use `noRootTitle` attribute.

Examples:
- `BOX <TABLE>`                              -- was: COLS
- `BOX <TABLE noHeader>`
- `BOX <TABLE hideOnNoRecords>`              -- was: HCOLS
- `BOX <TABLE title="Title of your table">`
- `BOX <TABLE noHeader hideOnNoRecords title="Table with title">`

Possible attributes are:
| attribute | value | description |
| --------- | ----- | ----------- |
| hideOnNoRecords | n.a. | when attribute is set, the complete table is hidden in the interface when there are no records |
| noHeader | n.a. | when attribute is set, no table header is used (all column labels are hidden) |
| title | string | title / description for the table. Title is shown above table |
| noRootTitle | n.a. | hides title; usefull for root interface boxes where a title is automatically is added |
| sortable | n.a. | makes table headers clickable to support sorting on some property of the data. Only applies to univalent fields |
| sortBy | sub interface label | Add default sorting for given sub interface. Use in combination with 'sortable' |
| order | `desc`, `asc` | Specifies default sorting order. Use in combination with 'sortBy'. Use `desc` for descending, `asc` for ascending |
| showNavMenu | n.a. | show 'hamburger' button to navigate to other interfaces designed for target concept of expression |

#### TABS
Interface template for a form structure with different tabs. For each sub interface a tab is added.
This template is used best in combination with univalent interface expressions (e.g. `INTERFACE "Test" : univalentExpression BOX <TABS>`), because for each target atom of the expression a complete form (with all tabs) is shown.

Usage `BOX <TABS attributes*>`

For root interface boxes automatically a title is added which equals the interface name. To hide this title use `noRootTitle` attribute.

Example:
- `BOX <TABS>`
- `BOX <TABS title="Tabs with title">`
- `BOX <TABS noRootTitle>`

Possible attributes are:
| attributes | value | description |
| ---------- | ----- | ----------- |
| title      | string | title / description for the table. Title is shown above tabs structure |
| noRootTitle    | n.a. | hides title; usefull for root interface boxes where a title is automatically is added |
| hideOnNoRecords | n.a. | when attribute is set, the complete tab set is hidden in the interface when there are no records |
| hideSubOnNoRecords | n.a. | when attribute is set, specific tabs (i.e. sub interfaces) that have no records are hidden |

#### RAW
Interface template without any additional styling and without (editing) functionality. Just plain html `<div>` elements
This template replaces former templates: `DIV`, `CDIV` and `RDIV`

Usage: `BOX <RAW attributes*>`

Examples:
- `BOX <RAW>`
- `BOX <RAW form>`
- `BOX <RAW table>`

Possible attributes are:
| attribute | value | description |
| --------- | ----- | ----------- |
| form      | n.a.  | uses simple form structure to display data. Similar to `FORM` template, but without any functionality nor markup. This is the default layout for `RAW` template.
| table     | n.a.  | uses simple table structure to display data. Similar to `TABLE` template (see below), but without any functionality, header and styling

#### PROPBUTTON
Interface template that provides a botton that, when clicked, can set, clear and/or toggle/flip the value of a number of property-relations (i.e. a relation that is [PROP] (or: [SYM,ASY]). 

The interface provides means to:

- construct the label (i.e. the text that shows on the button) from fixed texts (i.e. `TXT "some text here"`) as well as valiues of expression. This allows you to create detailed/customized texts on a button.
- flip, set, and clear (up to 3) property-relations. This allows you to easily create complex state machines, where clicking a single button can flip, set and clear several property-relations simultaneously.
- specify the color of the button, and a different color for when it is disabled.
- hide and/or disable the button by specifying an expression (that must be a [PROP]-type).
- provide a popover text for the button, both when it is enabled and when it is disabled. 

Usage (note that all attributes are optional, and you can rearrange their order as you see fit) :
```
expr cRud BOX <PROPBUTTON> 
  [ "label":  expr or txt    -- text on button = result of expr or txt
  , "label1": expr or txt    -- text on button = label+label1
  , "label2": expr or txt    -- text on button = label+label1+label2
  , "label3": expr or txt    -- text on button = label+label1+label2+label3
  , "property": propRel cRUd -- value of propRel is flipped when the button is clicked (backward compatible)
  , "fliprop1": propRel cRUd -- value of propRel is flipped when the button is clicked
  , "fliprop2": propRel cRUd -- value of propRel is flipped when the button is clicked
  , "fliprop3": propRel cRUd -- value of propRel is flipped when the button is clicked
  , "setprop1": propRel cRUd -- value of propRel is set (made true) when the button is clicked
  , "setprop2": propRel cRUd -- value of propRel is set (made true) when the button is clicked
  , "setprop3": propRel cRUd -- value of propRel is set (made true) when the button is clicked
  , "clrprop1": propRel cRUd -- value of propRel is cleared (made false) when the button is clicked
  , "clrprop2": propRel cRUd -- value of propRel is cleared (made false) when the button is clicked
  , "clrprop3": propRel cRUd -- value of propRel is cleared (made false) when the button is clicked
  , "color": color           -- see below for details.
  , "hide": expr cRud        -- button is hidden (not shown) when expression evaluates to true
  , "disabled": expr         -- button is disabled (not clickable) when expression evaluates to true
  , "disabledcolor": color   -- optional; see below for details.
  , "disabledpopovertext": expr or txt -- text is shown instead of popovertext when button is disabled.
  , "popovertext": expr or txt -- text that is displayed when hovering the button
  ]
```
where:
- `propRel` is an & `[PROP]`-type relation, whose value will be toggled when the user clicks the button.
- `expr` refers to an &-expression that should be univalent (and should be followed by `cRud` except when explicitly mentioned otherwise);
- `txt` refers to the syntax `TXT "some text here"`;
- `color` refers to `TXT "colorword"` can be primary (blue), secondary (grey), success (green), warning (yellow), danger (red), info (lightblue), light (grey), dark (black). So, if you want a red button, you write `"color": TXT "danger" -- button is red`.
It should be possible to precede color names 'outline-' (e.g. 'outline-primary') to make outline buttons (i.e. buttons with only the outline coloured), but that does not yet seem to work properly.


Possible attributes are:
| attribute | value | description |
| --------- | ----- | ----------- |
| *currently there are no attributes for this template*


### Atomic templates (i.e. interface leaves)

#### OBJECT

#### ALPHANUMERIC, BIGALPHANUMERIC, HUGEALPHANUMERIC

#### BOOLEAN

#### DATE, DATETIME

#### INTEGER, FLOAT

#### PASSWORD

#### TYPEOFONE
Special interface for singleton 'ONE' atom. This probably is never used in an prototype user interface. 

#### OBJECTDROPDOWN
Interface template that can be used to populate a relation (whose target concept MUST BE an object) using a dropdown list.
Objects are concepts for which there is no `REPRESENT` statement; non-objects (or values) are concepts for which there is (e.g. `REPRESENT SomeConcept TYPE ALPHANUMERIC`). This template can be used for objects. Use `BOX <VALUEDROPDOWN>` for non-objects.

Usage:
```
expr cRud BOX <OBJECTDROPDOWN>
[ "selectfrom": selExpr cRud <ObjectView> -- population from which the user can make a selection.
, "setrelation": setRel cRUd -- If the relation is [UNI], a newly selected object will replace the existing population.
, "instruction": expr or txt -- Text that shows when nothing is selected yet.
, "selectflag": selectEventFlag cRUd -- [PROP]-type relation that toggles when OBJECT is selected.
, "deselectflag": deselectEventFlag cRUd -- [PROP]-type relation that toggles when NO OBJECT is selected.
]
```

where:
- `expr` is an expression that, if and only if 'TRUE' causes the dropdown box to be shown.
- `selExpr cRud` specifies the objects that the user may select from. 
- `<ObjectView>` the VIEW to be used to show the selectable objects in the dropdown box.
- `setRel cRUd` is the relation whose population is modified as a result of the users actions. 
  - If the relation is `[UNI]` the user may overwrite its value (tgt atom) by selecting an object.
  - If the relation is not `[UNI]`, the user can add values (tgt atoms) by selecting one or more objects.
  - When the user selects the NO OBJECT, the (list of) tgt atom(s) is cleared.
- `expr or txt` in the 'instruction' field specifies the text that the user sees when no object has been selected.
- `selectEventFlag cRUd` specifies a [PROP]-type relation that will be toggled when an object is selected.
- `deselectEventFlag cRUd` specifies a [PROP]-type relation that toggles when NO OBJECT is selected.

NOTE that the `cRud` and `cRUd` usage must be strictly followed here!

#### VALUEDROPDOWN
Interface template that can be used to populate a relation (whose target concept is NOT an object) using a dropdown list. Objects are concepts for which there is no `REPRESENT` statement; non-objects (or values) are concepts for which there is (e.g. `REPRESENT SomeConcept TYPE ALPHANUMERIC`). This template can be used for values (non-objects). Use `BOX <OBJECTDROPDOWN>` for concepts that are objects.

Usage:
```
expr cRud BOX <VALUEDROPDOWN>
[ "selectfrom": selExpr cRud <ValueView> -- population from which the user can make a selection.
, "setrelation": setRel cRUd -- If the relation is [UNI], a newly selected value will replace the existing population.
, "instruction": expr or txt -- Text that shows when nothing is selected yet.
, "selectflag": selectEventFlag cRUd -- [PROP]-type relation that toggles when VALUE is selected.
, "deselectflag": deselectEventFlag cRUd -- [PROP]-type relation that toggles when NO VALUE is selected.
]
```

where:
- `expr` is an expression that, if and only if 'TRUE' causes the dropdown box to be shown.
- `selExpr cRud` specifies the values that the user may select from. 
- `<ValueView>` the VIEW to be used to show the selectable values in the dropdown box.
- `setRel cRUd` is the relation whose population is modified as a result of the users actions. 
  - If the relation is `[UNI]` the user may overwrite its value (tgt atom) by selecting an value.
  - If the relation is not `[UNI]`, the user can add values (tgt atoms) by selecting one or more values.
  - When the user selects the NO VALUE, the (list of) tgt atom(s) is cleared.
- `expr or txt` in the 'instruction' field specifies the text that the user sees when no value has been selected.
- `selectEventFlag cRUd` specifies a [PROP]-type relation that will be toggled when an value is selected.
- `deselectEventFlag cRUd` specifies a [PROP]-type relation that toggles when NO VALUE is selected.

NOTE that the `cRud` and `cRUd` usage must be strictly followed here!

### Built-in VIEW templates

#### FILEOBJECT
The purpose of this template, and the associated code, is to allow users to download and upload files.

To use: add the following statements to your script:

```
  IDENT FileObjectName: FileObject (filePath)
  RELATION filePath[FileObject*FilePath] [UNI,TOT]
  RELATION originalFileName[FileObject*FileName] [UNI,TOT]

  REPRESENT FilePath,FileName TYPE ALPHANUMERIC

  VIEW FileObject: FileObject DEFAULT 
  { apiPath  : TXT "api/v1/file"
  , filePath : filePath
  , fileName : originalFileName
  } HTML TEMPLATE "View-FILEOBJECT.html" ENDVIEW
```

#### LINKTO
This template can be used to specify the interface to which the user must navigate.

Usage:
```
  "label": expr LINKTO INTERFACE "InterfaceName"
```

where:
- `expr` is an ampersand expression, as usual
- `InterfaceName` is the name of an existing interface whose (SRC) concept matches the TGT concept of `expr`.

#### PROPERTY

#### STRONG

#### URL

## Generics folder
A compiled Ampersand script results in different model files that configure the prototype framework and must be placed in a specific. This folder is historically called 'generics'.

::: tip

More about the Ampersand compiler can be found [here](#ampersand-compiler).

:::


### Generated files by Ampersand compiler
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

### Compiler version constraints
As of Ampersand compiler version 5.x, the compiler checks if its version is compatible with the deployed prototype framework. The prototype framework specifies the compatible compiler version(s) by means of semantic versioning constraints specified in [compiler-version.txt](https://github.com/AmpersandTarski/prototype/tree/main/generics/compiler-version.txt).

The compiler uses Haskell package [Salve](https://hackage.haskell.org/package/salve) to check the constraints. See documentation of Salve to understand the contraint language.

### On-board Ampersand compiler
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

### Adding custom HTML templates
If you are using your own `VIEW` definitions and custom `BOX` specifications with custom HTML templates in your Ampersand script files, you need to copy them to the [templates folder](https://github.com/AmpersandTarski/prototype/tree/main/templates/) BEFORE running the compiler.

You can add the following line to your Docker file:
```Dockerfile
# If you have custom templates, they need to be copied to where the Ampersand compiler expects them (/var/www)
RUN cp -r -v /usr/local/project/shared/templates /var/www/
```

### Build with custom Ampersand compiler
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


