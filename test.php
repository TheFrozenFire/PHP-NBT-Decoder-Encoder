<?php
require("nbt.class.php");
$nbt = new nbt();
$nbt->verbose = true;
$nbt->loadFile("bigtest.nbt");
print_r($nbt->root);
?>
