<?php
require("nbt.class.php");
$nbt = new nbt();
$nbt->verbose = true;
$nbt->loadFile("bigtest.nbt");
$nbt->writeFile($tmp = tempnam(sys_get_temp_dir(), "nbt"));
print_r($nbt->root[0]);
?>
