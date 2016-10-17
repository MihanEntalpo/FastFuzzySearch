<?php
require_once("../FastFuzzySearch.php");

print_measures("wordsInIndex", array(1000, 2000, 3000, 4000, 5000, 6000, 7000, 8000), array("wordsInIndexLength"=>10, "wordsToSearch"=>100, "wordsToSearchLength"=>10));
print_measures("wordsInIndexLength", array(5, 10, 20, 40, 80, 160, 320, 640), array("wordsInIndex"=>100, "wordsToSearch"=>100, "wordsToSearchLength"=>10));
print_measures("wordsToSearchLength", array(4, 8, 16, 32, 64, 128, 256, 512), array("wordsInIndex"=>100, "wordsToSearch"=>100, "wordsInIndexLength"=>20));


function print_measures($variableParam, $variableParamValues, $otherParams)
{
    $allParams = array("wordsInIndex"=>"", "wordsInIndexLength"=>"", "wordsToSearch"=>"", "wordsToSearchLength"=>"");
    $allParamsTitles = array("wordsInIndex"=>"Слов в индексе", "wordsInIndexLength"=>"Средняя длина слова в индексе", "wordsToSearch"=>"Сколько слов искать", "wordsToSearchLength"=>"Средняя длина искомого слова");
    $dataKeysShort = array(
        "init_time"=>"init", "serialize_index_time"=>"ser", "serialized_index_size" => "ser_size", "unserialize_index_time"=>"unser", "find_time"=>"find", "find_time_single"=>"find_single"
    );
    foreach($allParams as $key=>$value)
    {
        if ($key != $variableParam)
        {
            $allParams[$key] = $otherParams[$key];
        }
    }
    $num = 0;
    $len = count($variableParamValues);
    $results = array();
    foreach($variableParamValues as $value)
    {
        $num += 1;
        echo "Замер $num/$len\n";
        $allParams[$variableParam] = $value;
        $data = call_user_func_array("measureTime", $allParams);
        $results[$value] = $data;
    }
    echo "Исходные данные:\n";
    foreach($otherParams as $key=>$value)
    {
        if ($key != $variableParam)
        {
            echo $allParamsTitles[$key] . ":" . $value . "\n";
        }
    }
    echo "Изменяемый параметр: " . $allParamsTitles[$variableParam] . ": " . join(",", $variableParamValues) . "\n";
    echo "Результаты:\n";
    $num = 0;
    foreach($results as $paramValue=>$data)
    {
        $num += 1;
        if ($num == 1)
        {
            echo $allParamsTitles[$variableParam] . ";";
            foreach($data as $key=>$value)
            {
                echo $dataKeysShort[$key] . ";";
            }
            echo "\n";
        }
        echo $paramValue . ";";
        foreach($data as $key=>$value)
        {
            echo round($value, 6) , ";";
        }
        echo "\n";
    }
    echo "\n";
}


function measure($closure){
    $t = microtime(true);
    $closure();
    $t = microtime(true) - $t;
    return $t;
}

/**
 * Создать случайное слово заданной длины
 */ 
function makeRandomWord($length)
{
    $letters = array(
        "а","б","в","г","д","е","ё","ж","з","и","й","к","л","м","н","о","п","р","с","т","у","в",
        "х","ц","ч","ш","щ","ь","ы","ъ","э","ю","я","a","b","c","d","e","f","g","h","i","j","k",
        "l","m","n","o","p","q","r","s","t","u","v","w","x","y","z"
    );
    $lettersN = count($letters);
    $w = "";
    for($i=0;$i<$length;$i++)
    {
        $pos = mt_rand(0, $lettersN-1);
        $w .= $letters[$pos];
    }
    return $w;
}


/**
 * Измерить время выполнения при заданных параметрах
 * @param integer $wordsInIndex Сколько слов должно быть в поисковом индексе
 * @param integer $wordsInIndexLength Средняя длина слова в индексе
 * @param integer $wordsToSearch Сколько нужно найти слов
 * @param integer $wordsToSearchLength средняя длина искомого слова
 * @param integer $wordLenDisp Дисперсия длины слова (на сколько символов +/- может меняться длина слова относительно заданной)
 */ 
function measureTime($wordsInIndex, $wordsInIndexLength, $wordsToSearch, $wordsToSearchLength, $wordLenDisp=3)
{
    $res = array();
    $words = array();
    for($i=0; $i<$wordsInIndex; $i++)
    {
        $wordLen = max(1, $wordsInIndexLength + mt_rand(-$wordLenDisp, $wordLenDisp));
        $words[] = makeRandomWord($wordLen);
    }
    $ffs = new FastFuzzySearch();
    $res['init_time'] = measure(function() use($ffs, $words) {
        $ffs->init($words);
    });
    $serialized = "";
    $res['serialize_index_time'] = measure(function() use($ffs, &$serialized) {
        $serialized = $ffs->serializeIndex();
    });
    $res['serialized_index_size'] = strlen($serialized);
    $res['unserialize_index_time'] = measure(function() use($ffs, &$serialized) {
        $ffs->unserializeIndex($serialized);
    });
    $res['find_time'] = 0;
    for($i=0; $i<$wordsToSearch; $i++)
    {
        $wordLen = max(1, $wordsToSearchLength + mt_rand(-$wordLenDisp, $wordLenDisp));
        $word = makeRandomWord($wordLen);
        
        $res['find_time'] += measure(function() use ($ffs, $word) {
            $s = $ffs->find($word);
        });
    }
    $res['find_time_single'] = $res['find_time'] / $wordsToSearch;
    
    return $res;
}

