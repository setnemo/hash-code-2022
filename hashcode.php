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
            return $a->dislikedCount <=> $b->dislikedCount;
        });

        return $this;
    }

    public function remove(int $key): self
    {
        unset($this->items[$key]);

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
 * @param  array  $all
 * @param  DataCollection  $dataCollection
 * @param  int  $maxDislikeDepth
 *
 * @return array
 */
function getResultWithDepth(array $all, DataCollection $dataCollection, int $maxDislikeDepth = 0): array
{
    $result = [];
    array_push($all, '');
    sort($all);
    $dataCollection->reorder();
    ;
    foreach ($all as $product) {
        foreach ($dataCollection->all() as $key => $item) {
            if (
                count($item->disliked) <= $maxDislikeDepth
                && ([] === $item->disliked || in_array($product, $item->disliked))
                && !in_array($product, $result)
            ) {
                $result = array_unique(array_merge($result, $item->liked), SORT_REGULAR);
                $dataCollection->remove($key);
            }
        }
    }

    return $result;
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
foreach (range(0,11) as $depth) {
    $result = getResultWithDepth($all, clone $dataCollection, $depth);
    $client = getClientCounter($dataCollection, $result);
    if ($client > $theBest) {
        $badResult = 0;
        $theBest = $client;
        $theBestResults = $result;
    } else {
        $badResult++;
    }
    if ($badResult === 3) {
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
