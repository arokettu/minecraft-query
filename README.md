Minecraft Server Query
======================

A PHP library rewritten from python
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