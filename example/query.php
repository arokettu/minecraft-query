<?php

// run 'composer install' in parent directory for this to work

require __DIR__. '/../vendor/autoload.php';

$minecraft = new \SandFoxIM\Minecraft\ServerQuery($argv[1], $argv[2], 2);

echo "Basic info:\n";
var_export($minecraft->getStatus());

echo "Full info:\n";
var_export($minecraft->getRules());
