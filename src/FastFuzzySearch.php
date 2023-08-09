<?php

/**
 * Класс быстрого нечёткого поиска среди списка.
 * Предназначен для поиска наиболее похожих слов среди загруженного списка.
 * Быстрота достигается за счёт построения специальной структуры данных
 * для быстрого поиска.
 */

declare(strict_types=1);

namespace Ekhlakov\FastFuzzySearch;

class FastFuzzySearch
{
    /**
     * Массив полей, которые нужно сериализовывать при сохранении индекса и рассериализовывать при загрузке
     */
    protected const INDEX_FIELDS = [
        'words',
        'wordParts',
        'minPart',
        'maxPart',
        'isInitialized',
        'wordInfo',
    ];

    /**
     * @var array<mixed> Словарь, значения - оригинальные слова;
     *                   ключи - слова, подготовленные функцией prepare_word для поиска
     */
    protected array $words = [];

    /**
     * @var array<mixed> Массив элементов слов
     */
    protected array $wordParts = [];

    /**
     * @var array<mixed> Массив информации о словах
     */
    protected array $wordInfo = [];

    /**
     * @var int Минимальная длина кусочка строки
     */
    public int $minPart = 2;

    /**
     * @var int Максимальная длина кусочка строки
     */
    public int $maxPart = 4;

    /**
     * @var bool Поиск инициализирован?
     */
    protected bool $isInitialized = false;

    /**
     * Конструктор
     *
     * @param string[]|null $words   Массив слов, по которым нужно будет искать (можно пустой)
     *                               если массив слов указан, будет вызвана функция init()
     * @param int|null      $minPart Минимальная длина кусочка строки
     * @param int|null      $maxPart Максимальная длина кусочка строки
     */
    public function __construct(?array $words = null, ?int $minPart = 2, ?int $maxPart = 4)
    {
        $this->wordParts = [];
        if (null === $minPart) {
            $minPart = 2;
        }
        if (null === $maxPart) {
            $maxPart = 4;
        }

        if ($minPart > 0) {
            $this->minPart = $minPart;
        }
        if ($maxPart > $minPart) {
            $this->maxPart = $maxPart;
        }
        if ($words) {
            $this->init($words);
        }
    }

    /**
     * Сериализовать индекс для сохранения во вне, и последующей быстрой загрузки
     *
     * @return string строка
     */
    public function serializeIndex(): string
    {
        $index = [];
        foreach (self::INDEX_FIELDS as $f) {
            $index[$f] = $this->{$f};
        }

        $res = null;
        try {
            $res = json_encode($index, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\Exception $exception) {
            error_log($exception->getMessage(), 0);
        }

        return $res ?? '';
    }

    /**
     * Рассериализовать индекс полученный из вне (сохранённый ранее с помощью serializeIndex)
     *
     * @param string $serializedIndex Сериализованный индекс
     */
    public function unserializeIndex(string $serializedIndex): void
    {
        if ($serializedIndex) {
            $index = [];
            try {
                $index = json_decode($serializedIndex, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Exception $exception) {
                error_log($exception->getMessage(), 0);
            }

            if ($index) {
                foreach (self::INDEX_FIELDS as $f) {
                    $this->{$f} = $index[$f];
                }
            }
        }
    }

    /**
     * Инициализировать
     *
     * @param string[] $words Массив слов, по которым нужно построить индекс.
     *                        Если массив пустой, то попытка инициализировать
     *                        по тому массиву слов, который был задан в конструкторе
     */
    public function init(array $words): void
    {
        if ($words) {
            $this->words = [];
            foreach ($words as $word) {
                $this->words[$this->prepare_word($word)] = $word;
            }
        }
        foreach ($this->words as $word) {
            $word = $this->prepare_word($word);
            $parts = $this->get_parts($word);

            $this->wordInfo[$word] = ['word' => $word, 'num_parts' => count($parts)];

            foreach ($parts as $part) {
                $size = mb_strlen($part);
                $skey = $size;
                if (!isset($this->wordParts[$skey])) {
                    $this->wordParts[$skey] = [];
                }
                if (!isset($this->wordParts[$skey][$part])) {
                    $this->wordParts[$skey][$part] = ['words' => []];
                }
                $this->wordParts[$skey][$part]['words'][$word] = $word;
            }
        }
        $this->isInitialized = true;
    }

    /**
     * Получить массив кусочков из слова, размерами от minPart до maxPart
     *
     * @param string $word Слово
     *
     * @return string[] Массив кусочков
     */
    protected function get_parts(string $word): array
    {
        $parts = [];
        $word_l = mb_strlen($word);
        $min_l = min($this->minPart, $word_l);
        $max_l = min($this->maxPart, $word_l);
        for ($size = $min_l; $size <= $max_l; $size ++) {
            for ($i = 0; $i <= $word_l - $size; $i++) {
                $parts[] = mb_substr($word, $i, $size, 'UTF-8');
            }
        }

        return $parts;
    }

    /**
     * Подготовить слово - удаляет из слова все символы кроме русских и английских букв
     *
     * @param string $word Слово
     *
     * @return string Результат обработки
     */
    public function prepare_word(string $word): string
    {
        $result = preg_replace(
            '/[^а-яА-Яa-zA-Z]/u',
            '',
            str_replace(['ё', 'Ё'], ['е', 'е'], mb_strtolower($word, 'UTF-8'))
        );

        return $result ?? '';
    }

    /**
     * Поиск наиболее похожих слов
     *
     * @param string   $word    Слово, которое надо искать
     * @param int|null $results Количество результатов
     *
     * @return array{int?: array{'word': string, 'percent': float}} Массив результатов, элементы которого - массивы,
     *                                                              вида array("word"=>"Слово", "percent"=0.77)
     *                                                              (слово и процент от 0 до 1)
     */
    public function find(string $word, ?int $results = 1): array
    {
        $word = $this->prepare_word($word);

        $parts = $this->get_parts($word);

        $foundWords = [];

        foreach ($parts as $part) {
            $size = mb_strlen($part);
            $skey = $size;
            if (!isset($this->wordParts[$skey][$part])) {
                continue;
            }
            $words = $this->wordParts[$skey][$part]['words'];
            foreach ($words as $tmpWord) {
                if (!isset($foundWords[$tmpWord])) {
                    $foundWords[$tmpWord] = 0;
                }
                ++$foundWords[$tmpWord];
            }
        }

        $resWords = [];

        $countParts = count($parts);
        foreach ($foundWords as $tmpWord => $num) {
            $numparts = $this->wordInfo[$tmpWord]['num_parts'] > $countParts
                ? $this->wordInfo[$tmpWord]['num_parts'] : $countParts;
            $foundWords[$tmpWord] = $numparts > 0 ? $num / $numparts : 0;
        }

        uasort($foundWords, static function($wc1, $wc2) {
            return $wc2 <=> $wc1;
        });

        $num = 0;
        foreach ($foundWords as $tmpWord => $percent) {
            ++$num;
            $resWords[] = ['word' => $this->words[$tmpWord], 'percent' => $percent];
            if ($num >= $results) {
                break;
            }
        }

        return $resWords;
    }

    /**
     * Функция поиска с помощью расстояний левенштейна, добавлена исключительно для тестирования быстродействия.
     * Работает СИЛЬНО медленнее функции find
     *
     * @return array<mixed>
     */
    public function findByLevestaine(string $word, ?int $results = 1): array
    {
        $word = $this->prepare_word($word);

        $data = [];
        $maxDistance = 0;

        foreach ($this->wordInfo as $wordInfo) {
            $curWord = $wordInfo['word'];
            $distance = levenshtein($word, $curWord);
            $data[] = ['word' => $curWord, 'percent' => $distance];
            $maxDistance = max([$distance, $maxDistance]);
        }

        foreach ($data as $key => $value) {
            $data[$key]['percent'] = 0 === $maxDistance ? 0 : 1 - $value['percent'] / $maxDistance;
        }

        uasort($data, static function($v1, $v2) {
            return $v2['percent'] <=> $v1['percent'];
        });

        return array_slice($data, 0, $results);
    }

    /**
     * Функция поиска с помощью функции similar_text, добавлена исключительно для тестирования быстродействия.
     * Работает СИЛЬНО медленнее функции find
     *
     * @return array<mixed>
     */
    public function findBySimilarText(string $word, ?int $results = 1): array
    {
        $word = $this->prepare_word($word);

        $data = [];

        foreach ($this->wordInfo as $wordInfo) {
            $curWord = $wordInfo['word'];
            $distance = similar_text($word, $curWord);
            $data[] = ['word' => $curWord, 'percent' => $distance];
        }

        uasort($data, static function($v1, $v2) {
            return $v2['percent'] <=> $v1['percent'];
        });

        return array_slice($data, 0, $results);
    }
}
