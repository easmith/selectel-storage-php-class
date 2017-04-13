selectel-storage-php-class
==========================

composer.json

```js

"require": {
    "easmith/selectel-storage": "~1.0",
}

```

```php

<?php
    
require_once("vendor/autoload.php");
$selectelStorage = new SelectelStorage("User", "Pass");

```

### Create Container 
```php
$container = $selectelStorage->createContainer('selectel', array("X-Container-Meta-Type: public"));
// get container info
$container->getInfo()
```

### Containers list
```php
$containerList = $selectelStorage->listContainers();
```

### Create directory
```php
$container->createDirectory('php/test')
```

### List
```php
$dirList = $container->listFiles($limit = 10000, $marker = null, $prefix = null, $path = "");
// files
$fileList = $container->listFiles($limit = 10000, $marker = null, $prefix = null, $path = 'php/');
```

### Put File
```php
$res = $container->putFile(__FILE__, 'example.php',["Content-Type: text/html"]);
```

### File info
```php
$fileInfo = $container->getFileInfo('example.php');
```

### Get file
```php
$file = $container->getFile($fileList[0]);
```

### Copy file
```php
$copyRes = $container->copy('example.php', 'php/test/Examples_copy.php5');
```

### Delete
```php
$deleteRes = $container->delete('example.php');
```