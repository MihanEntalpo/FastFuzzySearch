<?php

declare(strict_types=1);

require_once('../src/FastFuzzySearch.php');

$words = ['preved', 'medved', 'hello'];

$ffs = new Mihanentalpo\FastFuzzySearch\FastFuzzySearch();
$ffs->init($words);

print_r($ffs->find('vedpre'));
