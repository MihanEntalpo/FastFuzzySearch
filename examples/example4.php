<?php

require_once("../src/FastFuzzySearch.php");

$words = array(
    "preved", "medved", "hello"
);

$ffs = new Mihanentalpo\FastFuzzySearch\FastFuzzySearch();
$ffs->init($words);

print_r($ffs->find("vedpre"));
