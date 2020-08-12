# File System

## Introduction
**Currently we have limited support for working with files**


## Usage in Ampersand application and prototype
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

## The Flysystem component
In the prototype framework backend we've implemented the Flysystem library, which is a file system abstraction for PHP.

> "Flysystem is a filesystem abstraction library for PHP. By providing a unified interface for many different filesystems you’re able to swap out filesystems without application wide rewrites."

Read more about this library here: https://flysystem.thephpleague.com/v1/docs/

## File system property of AmpersandApp object
A concrete implementation of a file system is set as property of the AmpersandApp object. You can access the file system like this to perform e.g. a read:

```php
<?php
/** @var AmpersandApp $app */
$fs = $app->fileSystem();
$fs->read('path/to/file.txt');
```

Other methods of the file system interface include `put()`, `has()`, `listContents()` and others. For API documentation see: https://flysystem.thephpleague.com/v1/docs/usage/filesystem-api/

## Using another file system implementation
By default a 'Local' file system adapter is added to the AmpersandApp. This read/writes files to the '`./data`' folder.

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
