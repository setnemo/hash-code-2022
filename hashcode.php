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

    public function remove(int $key): self
    {
        $this->items[$key] = null;
        unset($this->items[$key]);

        return $this;
    }

    public function invert(bool $invert): self
    {
        if ($invert) {
            return $this->reorder(true);
        }

        return $this->reorder(false);
    }

    public function reorder(bool $way = true): self
    {
        if ($way) {
            usort($this->items, static function(Data $a, Data $b) {
                return $a->dislikedCount < $b->dislikedCount;
            });
        } else {
            usort($this->items, static function(Data $a, Data $b) {
                return $a->likedCount < $b->likedCount;
            });
        }


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
$dislikeSummary = $likeSummary = [];
$dataCollection = new DataCollection();
for ($i = 1; $i <= $max; ) {
    $clientCounter = $i % 2 !== 0 ? $clientCounter - 1: $clientCounter;
    $likeStr = $fileExploded[$i - 1];
    $likeArray = explode(' ', $likeStr);
    $likeCounter = array_shift($likeArray);
    $i++;
    $dislikeStr = $fileExploded[$i - 1];
    $dislikeArray = explode(' ', $dislikeStr);
    $dislikeCounter = array_shift($dislikeArray);
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
#[Pure] function secondAlgo(DataCollection $dataCollection): array
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
    foreach ($dataCollectionCopy->all() as $item) {
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


/**
 * @param  DataCollection  $dataTmp2
 * @param  DataCollection  $dataCopy
 * @param  array  $results
 * @param  int  $customer
 * @param  bool  $exit
 * @param  bool  $skip
 *
 * @return array
 */
function thirdAlgo(DataCollection $dataTmp2, DataCollection $dataCopy, array &$results, int $customer, bool &$exit, bool $skip = true): array
{
    foreach ($dataCopy->all() as $it => $item2) {
        if ($skip && $it < $customer) {
            continue;
        }
        if ($skip === false && $it > $customer) {
            continue;
        }
        $itDisLiked = 0;
        $disliked   = $liked = [];
        foreach ($item2->disliked as $itemDisliked) {
            if (in_array($itemDisliked, $results[$customer]['liked'])) {
                $itDisLiked++;
            }
            $disliked[$itemDisliked] = true;
        }
        foreach ($item2->liked as $itemLiked) {
            $liked[$itemLiked] = true;
            if (in_array($itemLiked, $results[$customer]['disliked'])) {
                $itDisLiked++;
            }
        }
        if ($itDisLiked === 0) {
            $exit                           = false;
            $results[$customer]['disliked'] = array_merge($results[$customer]['disliked'], array_keys($disliked));
            $results[$customer]['liked']    = array_merge($results[$customer]['liked'], array_keys($liked));
            $results[$customer]['clients']++;
            $dataTmp2 = $dataCopy->remove($it);
        }
    }

    return array($exit, $results, $dataTmp2);
}

/**
 * 1554+1721+2+5+4
 * 3286
 */
$results = [];
$prevResult = $skip = 0;
$currentResult = 1;
$skips = intval(count($dataCollection->all()) / 5);
$order = 0;
foreach ($dataCollection->all() as $customer => $item) {
    if ($skips > 500 && ($customer % 100) > 3) {
        continue;
    }

    $results[$customer] = [
        'liked' => $item->liked,
        'disliked' => $item->disliked,
        'clients' => 1,
    ];
    $order++;
    $match = $order % 3;
    $dc = clone $dataCollection;
    $dataTmp2 = $dc->remove($customer);
    $dataTmp1 = $dataTmp2;
    while (true) {
        $exit = true;
        $dataCopy = $dataTmp1;
        [$exit, $results, $dataTmp2] = thirdAlgo($dataTmp2, $dataCopy, $results, $customer, $exit);
        [$exit, $results, $dataTmp2] = thirdAlgo($dataTmp2, $dataCopy, $results, $customer, $exit, false);
        $results[$customer]['disliked'] = array_unique($results[$customer]['disliked'], SORT_REGULAR);
        $results[$customer]['liked'] = array_unique($results[$customer]['liked'], SORT_REGULAR);
        $results[$customer]['counter'] = count($results[$customer]['liked']);
        if ($exit) {
            break;
        }
    }
    $clients = array_column($results, 'clients');
    $counter = array_column($results, 'counter');
    array_multisort($clients, SORT_DESC, $counter, SORT_ASC, $results);
    unset($results[20]);
}
$theBestResults = $results[0]['liked'];
$theBest = $results[0]['clients'];

$output = strtr(':how :params', [
        ':how' => count($theBestResults),
        ':params' => implode(' ', $theBestResults)
    ]
);

file_put_contents('output/3.' . $argv[1] . '.out.txt', $output);
echo "[3] Clients: {$theBest}\n";


/**
 * 1516+1794+2+5+4
 * 3321
 */
$theBest = $client = $badResult = 0;
$theBestResults = [];
$dataCollection->reorderBack();
$tt = $badResult = 0;
$sliceDataCollection = $dataCollection;
while (true) {
    [$client, $result] = secondAlgo($sliceDataCollection);
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
file_put_contents('output/2.' . $argv[1] . '.out.txt', $output);
echo "[2] Clients: {$theBest}\n";


/**
 * 2+5+5+1537+1690
 */
$results = [];
$results[] = ['liked' => [], 'disliked' => [], 'clients' => 0];
$depth = intval(count($dataCollection->all())/20);
$depth = $depth > 10 ? $depth : 10;
foreach ($dataCollection->all() as $customer => $item) {
    if ($customer > 0 && count($results) < $depth) {
        $results[] = ['liked' => [], 'disliked' => [], 'clients' => 0];
    }
    foreach ($results as $it => $result) {
        $flag = false;
        foreach ($item->disliked as $itemDisliked) {
            if (in_array($itemDisliked, array_keys($result['liked']))) {
                $flag = true;
            }
        }
        if ($flag) {
            continue;
        }
        foreach ($item->liked as $itemLiked) {
            if (in_array($itemLiked, array_keys($result['disliked']))) {
                $flag = true;
            }
        }
        if ($flag) {
            continue;
        }
        foreach ($item->disliked as $itemDisliked) {
            $results[$it]['disliked'][$itemDisliked] = true;
        }
        foreach ($item->liked as $itemLiked) {
            $results[$it]['liked'][$itemLiked] = true;

        }
        $results[$it]['clients']++;
    }
    usort($results, static function(array $a, array $b) {
        return $a['clients'] < $b['clients'];
    });
    unset($results[$depth]);
}
$output = strtr(':how :params', [
        ':how' => count($results[0]['liked']),
        ':params' => implode(' ', array_keys($results[0]['liked']))
    ]
);
file_put_contents('output/1.' . $argv[1] . '.out.txt', $output);
echo "[1] Clients: {$results[0]['clients']}\n";


