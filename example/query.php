<?php

// manually add namespace to composer as we didn't install our package as a dependency
/** @var \Composer\Autoload\ClassLoader $autoload */
$autoload = require __DIR__. '/../vendor/autoload.php';
$autoload->addPsr4('SandFoxIM\\Minecraft\\', __DIR__. '/../classes');

$minecraft = new \SandFoxIM\Minecraft\ServerQuery($argv[1], $argv[2], 2);

echo "Basic info:\n";
var_export($minecraft->getStatus());

echo "Full info:\n";
var_export($minecraft->getRules());
