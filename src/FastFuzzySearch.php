<?php

namespace Mihanentalpo\FastFuzzySearch;

/**
 * Класс быстрого нечёткого поиска среди списка.
 * Предназначен для поиска наиболее похожих слов среди загруженного списка.
 * Быстрота достигается за счёт построения специальной структуры данных
 * для быстрого поиска.
 */
class FastFuzzySearch
{

    /**
     * @var array словарь, значения - оригинальные слова
     *                     ключи - слова, подготовленные функцией prepare_word для поиска
     */
    protected $words = array();

    /**
     * @var array массив элементов слов
     */
    protected $wordParts = array();

    /**
     * @var array массив информации о словах
     */
    protected $wordInfo = array();

    /**
     * @var integer минимальная длина кусочка строки
     */
    public $minPart = 2;

    /**
     * @var integer максимальная длина кусочка строки
     */
    public $maxPart = 4;

    /**
     * @var boolean поиск инициализирован?
     */
    protected $isInitialized = false;

    /**
     * @var array массив полей, которые нужно сериализовывать при сохранении индекса
     * и рассериализовывать при загрузке
    */
    protected static $indexFields = array(
        "words", "wordParts", "minPart", "maxPart", "isInitialized", "wordInfo"
    );

    /**
     * Конструктор
     * @param array $words массив слов, по которым нужно будет искать (можно пустой)
     *                     если массив слов указан, будет вызвана функция init()
     * @param integer $minPart минимальная длина кусочка строки
     * @param integer $maxPart максимальная длина кусочка строки
     * @param integer $maxCachedResults максимальное количество результатов по слову,
     *                                  если = 0, кэширование производиться не будет.
     */
    public function __construct($words = array(), $minPart = 2, $maxPart = 4, $maxCachedResults = 10)
    {
        $this->cache = array();
        $this->wordParts = array();
        $this->minPart = $minPart;
        $this->maxPart = $maxPart;
        if (count($words))
        {
            $this->init($words);
        }
    }

    /**
     * Сериализовать индекс для сохранения во вне,
     * и последующей быстрой загрузки
     * @return string строка
     */
    public function serializeIndex()
    {
        foreach (self::$indexFields as $f)
        {
            $index[$f] = $this->{$f};
        }

        $res = json_encode($index, JSON_UNESCAPED_UNICODE);

        return $res;
    }

    /**
     * Рассериализовать индекс полученный из вне
     * (сохранённый ранее с помощью serializeIndex)
     * @param string $serializedIndex сериализованный индекс
     */
    public function unserializeIndex($serializedIndex)
    {
        $index = json_decode($serializedIndex, true);

        foreach (self::$indexFields as $f)
        {
            $this->{$f} = $index[$f];
        }
    }

    /**
     * Инициализировать
     * @param array $words Массив слов, по которым нужно построить индекс
     *                     если массив пустой, то попытка инициализировать
     *                     по тому массиву слов, который был задан в конструкторе
     */
    public function init($words = array())
    {
        if ($words)
        {
            $this->words = array();
            foreach ($words as $word) {
                $this->words[$this->prepare_word($word)] = $word;
            }
        }
        foreach ($this->words as $word)
        {
            $word = $this->prepare_word($word);
            $parts = $this->get_parts($word);

            $this->wordInfo[$word] = array("word" => $word, "num_parts" => count($parts));

            foreach ($parts as $part)
            {
                $size = mb_strlen($part);
                $skey = $size;
                if (!isset($this->wordParts[$skey]))
                {
                    $this->wordParts[$skey] = array();
                }
                if (!isset($this->wordParts[$skey][$part]))
                {
                    $this->wordParts[$skey][$part] = array("words" => array());
                }
                $this->wordParts[$skey][$part]["words"][$word] = $word;
            }
        }
        $this->isInitialized = true;
    }

    /**
     * Получить массив кусочков из слова, размерами от minPart до maxPart
     * @param string $word слово
     * @return array массив кусочков
     */
    protected function get_parts($word)
    {
        $parts = array();
        $word_l = mb_strlen($word);
        $min_l = min($this->minPart, $word_l);
        $max_l = min($this->maxPart, $word_l);
        for ($size = $min_l; $size <= $max_l; $size ++)
        {
            for ($i = 0; $i <= $word_l - $size; $i++)
            {
                $parts[] = mb_substr($word, $i, $size, "UTF-8");
            }
        }
        return $parts;
    }

    /**
     * Получить количество кусочков, которые могут быть получены из слова
     * @param integer $len длина слова, количество кусочков которого нужно выяснить
     * @return integer количество кусочков
     */
    protected function get_num_parts($len)
    {
        $min_l = min($len, $this->minPart);
        $max_l = max($len, $this->maxPart);

        $n = $len - $min_l + 1;
        $d = $max_l - $min_l;
        $m = $n - $d;

        $s1 = $m * ($d + 1);
        $s2 = $d * ($d + 1) / 2;
        $s = $s1 + $s2;

        return (int) $s;
    }

    /**
     * Подготовить слово - удаляет из слова все символы кроме русских и английских букв
     * @param string $word слово
     * @return string результат обработки
     */
    public function prepare_word($word)
    {
        return preg_replace(
                "[^а-яА-Яa-zA-Z]", "", str_replace(
                        array("ё", "Ё"), array("е", "е"), mb_strtolower($word, "UTF-8")
                )
        );
    }

    /**
     * Поиск наиболее похожих слов
     * @param string $word слово, которое надо искать
     * @param integer $result Количество результатов
     * @return array массив результатов, элементы которого - массивы,
     *               вида array("word"=>"Слово", "percent"=0.77)
     *               (слово и процент от 0 до 1)
     */
    public function find($word, $results = 1)
    {
        $word = $this->prepare_word($word);

        $parts = $this->get_parts($word);

        $foundWords = array();

        foreach ($parts as $part)
        {
            $size = mb_strlen($part);
            $skey = $size;
            if (!isset($this->wordParts[$skey][$part]))
                continue;
            $words = $this->wordParts[$skey][$part]['words'];
            foreach ($words as $word)
            {
                if (!isset($foundWords[$word]))
                {
                    $foundWords[$word] = 0;
                }
                $foundWords[$word] += 1;
            }
        }

        $resWords = array();

        foreach ($foundWords as $word => $num)
        {
            $numparts = $this->wordInfo[$word]['num_parts'] > count($parts) ? $this->wordInfo[$word]['num_parts'] : count($parts);
            $foundWords[$word] = $num / $numparts;
        }

        uasort($foundWords, function($wc1, $wc2)
        {
            if ($wc1 > $wc2)
                return -1;
            if ($wc1 < $wc2)
                return 1;
            return 0;
        });

        $num = 0;
        foreach ($foundWords as $word => $percent)
        {
            $num += 1;
            $resWords[] = array("word" => $this->words[$word], "percent" => $percent);
            if ($num >= $results)
                break;
        }

        return $resWords;
    }

    /**
     * Функция поиска с помощью расстояний левенштейна,
     * добавлена исключительно для тестирования быстродействия.
     * Работает СИЛЬНО медленнее функции find
     */
    public function findByLevestaine($word, $results = 1)
    {
        $word = $this->prepare_word($word);

        $data = array();
        $maxDistance = 0;

        foreach ($this->wordInfo as $wordInfo)
        {
            $curWord = $wordInfo['word'];
            $distance = levenshtein($word, $curWord);
            $data[] = array("word" => $curWord, "percent" => $distance);
            $maxDistance = max(array($distance, $maxDistance));
        }

        foreach ($data as $key => $value)
        {
            $data[$key]['percent'] = 1 - $data[$key]['percent'] / $maxDistance;
        }

        uasort($data, function($v1, $v2)
        {
            if ($v1['percent'] > $v2['percent'])
                return -1;
            if ($v1['percent'] < $v2['percent'])
                return 1;
            return 0;
        });

        return array_slice($data, 0, $results);
    }

    /**
     * Функция поиска с помощью функции similar_text,
     * добавлена исключительно для тестирования быстродействия.
     * Работает СИЛЬНО медленнее функции find
     */
    public function findBySimilarText($word, $results = 1)
    {
        $word = $this->prepare_word($word);

        $data = array();
        $maxDistance = 0;

        foreach ($this->wordInfo as $wordInfo)
        {
            $curWord = $wordInfo['word'];
            $distance = similar_text($word, $curWord);
            $data[] = array("word" => $curWord, "percent" => $distance);
        }


        uasort($data, function($v1, $v2)
        {
            if ($v1['percent'] > $v2['percent'])
                return -1;
            if ($v1['percent'] < $v2['percent'])
                return 1;
            return 0;
        });

        return array_slice($data, 0, $results);
    }

}
