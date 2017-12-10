Minecraft Server Query
======================

[![Code Climate](https://img.shields.io/codeclimate/maintainability/sandfoxme/minecraft-query.svg?maxAge=2592000)](https://codeclimate.com/github/sandfoxme/minecraft-query)

A PHP library for checking the status of an enabled Minecraft server ported from python
[https://github.com/Dinnerbone/mcstatus](https://github.com/Dinnerbone/mcstatus)

Usage
-----

```php
$minecraft = new \SandFoxIM\Minecraft\ServerQuery($argv[1], $argv[2], 2);

echo "Basic info:\n";
var_export($minecraft->getStatus());

echo "Full info:\n";
var_export($minecraft->getRules());
```
