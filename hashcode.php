<?php

declare(strict_types=1);

use JetBrains\PhpStorm\Pure;

class DataCollection
{
    /**
     * @var array|Data[]
     */
    public array $items = [];

    public function push(Data $data)
    {
        array_push($this->items, $data);
    }

    /**
     * @return array
     */
    public function all(): array
    {
        return $this->items;
    }

    public function reorder(): self
    {
        usort($this->items, static function(Data $a, Data $b) {
            return $a->dislikedCount < $b->dislikedCount;
        });

        return $this;
    }
    public function reorderBack(): self
    {
        usort($this->items, static function(Data $a, Data $b) {
            sort($a->disliked);
            sort($b->disliked);
            $implodeA      = implode('_', $a->disliked);
            $substrCountA = substr_count($implodeA, '_');
            $implodeB     = implode('_', $b->disliked);
            $substrCountB = substr_count($implodeB, '_');
            if ($implodeA === '' || $implodeB === '') {
                return strlen($implodeA) > strlen($implodeB);
            }
            return $substrCountA > $substrCountB;
        });

        return $this;
    }

    public function moveTopToTheEnd(): self
    {
        $result = [];
        foreach ($this->items as $item) {
            $key = implode('_', $item->disliked);
            $result[$key] = isset($result[$key]) ? ++$result[$key] : 1;
        }
        uasort($result, static function(string $a, string $b) {
                $substrCountA = substr_count($a, '_');
                $substrCountB = substr_count($b, '_');
                if ($substrCountA === $substrCountB) {
                    return 0;
                }
                return $substrCountA < $substrCountB ? 1 : -1;
        });
        $ret = new DataCollection();
        $ret->items = $this->items;
        $filter = array_key_first($result);
        if ($filter === '') {
            $ret->items = array_filter($ret->items, static function(Data $data) use ($filter) {
                return implode('_', $data->disliked) === $filter;
            });
            $this->items = array_filter($this->items, static function(Data $data) use ($filter) {
                return implode('_', $data->disliked) !== $filter;
            });
        } else {
            $substrCount = substr_count($filter, '_');
            $ret->items = array_filter($ret->items, static function(Data $data) use ($substrCount) {
                return substr_count(implode('_', $data->disliked), '_') <= $substrCount;
            });
            $this->items = array_filter($this->items, static function(Data $data) use ($substrCount) {
                return substr_count(implode('_', $data->disliked), '_') > $substrCount;
            });
        }
        $this->items = array_filter($this->items);
        $this->items = array_merge($this->items, $ret->items);

        return $this;
    }
}

class Data
{
    public function __construct(
        public int $customer,
        public array $liked,
        public int $likedCount,
        public array $disliked,
        public int $dislikedCount,
    ) {
    }
}

if (!isset($argv[1])) {
    die("usage: php hashcode.php path_to_file\n");
}
$file = file_get_contents($argv[1]);
$fileExploded = explode("\n", $file);
$clientCounter = array_shift($fileExploded);
$max = 2 * $clientCounter;
$dislikeSummary = $likeSummary = $all = [];
$dataCollection = new DataCollection();
for ($i = 1; $i <= $max; ) {
    $clientCounter = $i % 2 !== 0 ? $clientCounter - 1: $clientCounter;
    $likeStr = $fileExploded[$i - 1];
    $likeArray = explode(' ', $likeStr);
    $likeCounter = array_shift($likeArray);
    $all = array_unique(array_merge($all, $likeArray), SORT_REGULAR);
    $i++;
    $dislikeStr = $fileExploded[$i - 1];
    $dislikeArray = explode(' ', $dislikeStr);
    $dislikeCounter = array_shift($dislikeArray);
    $all = array_unique(array_merge($all, $dislikeArray), SORT_REGULAR);
    $i++;
    $dataCollection->push(new Data(
        $clientCounter,
        $likeArray,
        (int) $likeCounter,
        $dislikeArray,
        (int) $dislikeCounter,
    ));
}

/**
 * @param  DataCollection  $dataCollection
 *
 * @return array
 */
#[Pure] function getResultWithDepth(DataCollection $dataCollection): array
{
    $disliked = $liked = [];
    $client = 0;
    $data     = $dataCollection->all();
    foreach ($data as $item) {
        $flag = false;
        foreach ($item->disliked as $itemDisliked) {
            if (in_array($itemDisliked, array_keys($liked))) {
                $flag = true;
            }
        }
        if ($flag) {
            continue;
        }
        foreach ($item->liked as $itemLiked) {
            if (in_array($itemLiked, array_keys($disliked))) {
                $flag = true;
            }
        }
        if ($flag) {
            continue;
        }
        foreach ($item->disliked as $itemDisliked) {
            $disliked[$itemDisliked] = true;
        }
        foreach ($item->liked as $itemLiked) {
            $liked[$itemLiked] = true;

        }
        $client++;
    }

    return [$client, array_keys($liked)];
}

/**
 * @param  DataCollection  $dataCollectionCopy
 * @param  array  $result
 *
 * @return int
 */
#[Pure] function getClientCounter(DataCollection $dataCollectionCopy, array $result): int
{
    $client = 0;
    foreach ($dataCollectionCopy->all() as $key => $item) {
        $itLiked    = 0;
        $itDisLiked = 0;
        foreach ($item->liked as $liked) {
            if (in_array($liked, $result)) {
                $itLiked++;
            }
        }
        foreach ($item->disliked as $disliked) {
            if (in_array($disliked, $result)) {
                $itDisLiked++;
            }
        }
        if ($item->likedCount === $itLiked && $itDisLiked === 0) {
            $client++;
        }
    }

    return $client;
}

$theBest = $client = $badResult = 0;
$theBestResults = [];
array_push($all, '');
sort($all);
$dataCollection->reorderBack();
$tt = $badResult = 0;
$maxx = (int) (count($dataCollection->all()) / 10);
$sliceDataCollection = $dataCollection;
while (true) {
    [$client, $result] = getResultWithDepth($sliceDataCollection);
    if ($client > $theBest) {
        $badResult = 0;
        $theBest = $client;
        $theBestResults = $result;
        $sliceDataCollection = $sliceDataCollection->moveTopToTheEnd();
    } else {
        $badResult++;
    }
    if ($badResult === 10) {
        break;
    }
}


$output = strtr(':how :params', [
        ':how' => count($theBestResults),
        ':params' => implode(' ', $theBestResults)
    ]
);
file_put_contents('result.' . $argv[1], $output);
echo "Clients: {$theBest}\n";
$theBest2 = getClientCounter($dataCollection, $theBestResults);
echo "Clients: {$theBest2}\n";
