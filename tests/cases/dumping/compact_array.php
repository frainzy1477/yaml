<?php

use Dallgoot\Yaml\Types\Compact;


$o = new \StdClass;

$o->key1 = new Compact([1,2,3]);


return $o;