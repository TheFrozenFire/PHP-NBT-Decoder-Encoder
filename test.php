<?php
error_reporting(E_ALL);
require("nbt.class.php");
$nbt = new nbt();
$nbt->verbose = true;
$nbt->loadFile("smalltest.nbt");
$nbt->loadFile("bigtest.nbt");
$nbt->writeFile($tmp = tempnam(sys_get_temp_dir(), "nbt"));
echo "Small Test".PHP_EOL;
print_r($nbt->root[0]);
echo "Big Test".PHP_EOL;
print_r($nbt->root[1]);
?>
