<?php
require_once("../FastFuzzySearch.php");

function measure($closure){
    $t = microtime(true);
    $closure();
    $t = microtime(true) - $t;
    return $t;
}
//Загрузим города из файла
$search_in = require("./cities.php");
//Увеличим количество городов искуственно
$search_in = array_merge($search_in, $search_in);
$search_in = array_merge($search_in, $search_in);
$search_in = array_merge($search_in, $search_in);
$search_in = array_merge($search_in, $search_in);

$ffs = new FastFuzzySearch();

$time_init = measure(function() use(&$ffs, $search_in){

    $ffs->init($search_in);

});

$sindex = "";

$time_ser = measure(function()use(&$ffs, &$sindex){
    $sindex = $ffs->serializeIndex();
});

file_put_contents("./index.cache", $sindex);

$sindex = file_get_contents("./index.cache");

$time_unser = measure(function() use (&$ffs, $sindex){
    $ffs->unserializeIndex($sindex);
});

$words = array();
//Сколько слов нужно для теста
$numwords = 200;
//Сколько случайных изменений делать в каждом слове для теста
$wrongLetters = 2;
//Буквы для вставки
$letters = "абвгдеёжзиёклмнопрстуфхцчшщьыъэюя";

for($i = 0; $i<$numwords; $i++)
{
    //Возьмём существующий город
    $n = mt_rand(0, count($search_in)-1);
    $random_word = $search_in[$n];
    //Сделаем несколько случайных изменений
    for($j =0; $j<$wrongLetters; $j++)
    {
        $r = mt_rand(0,100);
        //Удалим букву
        if ($r<33)
        {
            $p = mt_rand(1, mb_strlen($random_word)-1);
            $random_word = mb_substr($random_word,0,$p) . mb_substr($random_word, $p+1);
        }
        //Заменим букву
        else if ($r<66)
        {
            $p = mt_rand(1, mb_strlen($random_word)-1);
            $letter = mb_substr($letters, mt_rand(0, mb_strlen($letters)-1), 1);
            $random_word = mb_substr($random_word,0,$p) . $letter . mb_substr($random_word, $p+1);
        }
        //Вставим букву
        else
        {
            $p = mt_rand(1, mb_strlen($random_word)-1);
            $letter = mb_substr($letters, mt_rand(0, mb_strlen($letters)-1), 1);
            $random_word = mb_substr($random_word,0,$p) . $letter . mb_substr($random_word, $p);
        }
    }
    $words[] = $random_word;    
}

echo "Testing FastFuzzyCompare...\n";

$t = microtime(true);

foreach($words as $word)
{
    $ffs->find($word);
}

$find_time = microtime(true) - $t;


echo "Testing FindByLevenstaine...\n";

$t = microtime(true);

foreach($words as $word)
{
    $ffs->findByLevestaine($word);
}

$find_lev = microtime(true) - $t;

echo "Testing FindBySimilarText...\n";

$t = microtime(true);

foreach($words as $word)
{
    $ffs->findBySimilarText($word);
}

$find_sim = microtime(true) - $t;


echo "Поиск $numwords среди " . count($search_in) . " слов\n";
echo "Результаты:\n";
echo "Инициализация с нуля: " . round($time_init,5) . " сек.\n";
echo "Сериализация индекса: " . round($time_ser,5) . " сек.\n";
echo "Инициализация из сериализованного индекса: " . round($time_unser,5) . " сек.\n";
echo "FastFuzzySearch: " . round($find_time,5) . " сек.\n";
echo "Levensteine:     " . round($find_lev,5) . " сек. (медленнее чем find в " . round($find_lev / $find_time, 2) . " раз)\n";
echo "SimilarText:     " . round($find_sim,5) . " сек. (медленнее чем find в " . round($find_sim / $find_time, 2) . " раз)\n";


