Это клон проекта "mihanentalpo/fast-fuzzy-search" с доработками и оптимизациями для PHP 8.

FastFuzzySearch aimed to search an array of words for the most similiar to the specified one

The used algorythm is much faster than using levenstein distance, or similiar_text functions.

*Usage:*

```php

require_once('./FastFuzzySearch.php');

//Get words array: (it's english names, starting from A,B,C)
$words = [
    'Abbott', 'Abe', 'Addison', 'Adrian', 'Aiken', 'Ainsley', 'Al', 'Alan', 
    'Alaric', 'Alban', 'Albert', 'Albion', 'Aldrich', 'Alec', 'Alex', 'Alexander', 
    'Alexis', 'Alf', 'Alfie', 'Alfred', 'Alger', 'Algernon', 'Alick', 'Allan', 
    'Allen', 'Alton', 'Alvin', 'Ambrose', 'Andrew', 'Andy', 'Anthony', 'Archer', 
    'Armstrong', 'Arnold', 'Ashley', 'Aston', 'Atwater', 'Aubrey', 'Austin', 
    'Avery', 'Bailey', 'Baldwin', 'Barclay', 'Barrett', 'Bartholomew', 'Barton', 
    'Basil', 'Baxter', 'Baz', 'Benedict', 'Benjamin', 'Bennett', 'Benson', 'Bentley', 
    'Berkley', 'Bernard', 'Bert', 'Bill', 'Blake', 'Bob', 'Bobby', 'Bond', 'Brad', 
    'Bradley', 'Brent', 'Bret', 'Brewster', 'Brian', 'Brigham', 'Brooke', 'Bruce', 
    'Bruno', 'Bryant', 'Buck', 'Bud', 'Burgess', 'Burton', 'Byron', 'Cade', 'Caesar', 
    'Caldwell', 'Calvert', 'Calvin', 'Carl', 'Carlton', 'Carter', 'Carver', 'Cary', 
    'Casey', 'Cassian', 'Cecil', 'Cedric', 'Chad', 'Chandler', 'Chapman', 'Charles', 
    'Charlie', 'Charlton', 'Chase', 'Chester', 'Chris', 'Christian', 'Christopher', 
    'Chuck', 'Clarence', 'Claude', 'Clay', 'Clayton', 'Clement', 'Cliff', 'Clifford', 
    'Clifton', 'Clive', 'Clyde', 'Cole', 'Coleman', 'Colin', 'Conrad', 'Constant', 
    'Conway', 'Corwin', 'Courtney', 'Craig', 'Crispin', 'Crosby', 'Curtis', 'Cuthbert', 'Cyril'
];


//Create FastFuzzySearch object:
$ffs = new FastFuzzySearch($words);

//Lets pretend, this is user's input:
$input = "charter";

//Lets get three most similiar english names:
$results = $ffs->find($input, 3);

//End output it:
print_r($results);

```

Results would be:

```
Array
(
    [0] => Array
        (
            [word] => carter
            [percent] => 0.75
        )

    [1] => Array
        (
            [word] => chad
            [percent] => 0.33333333333333
        )

    [2] => Array
        (
            [word] => charlie
            [percent] => 0.26666666666667
        )

)
```

