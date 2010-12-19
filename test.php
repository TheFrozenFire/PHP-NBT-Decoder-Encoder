<?php
require("nbt.class.php");
$nbt = new nbt();
$nbt->verbose = true;
$nbt->loadFile("bigtest.nbt");
print_r($nbt->root);
$nbt->writeFile("bigtest.written.nbt");
$nbt->purge();
$nbt->loadFile("bigtest.written.nbt");
print_r($nbt->root);
?>
