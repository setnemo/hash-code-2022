<?php

declare(strict_types=1);

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
}

class Data
{
    public function __construct(
        public int $customer,
        public array $liked,
        public string $likedPrefix,
        public int $likedCount,
        public array $disliked,
        public string $dislikedPrefix,
        public int $dislikedCount,
    ) {
    }
}

if (!isset($argv[1])) {
    die("usage: php hashcode.php path_to_file\n");
}
$file = file_get_contents($argv[1]);
$arr1 = explode("\n", $file);
$clientCounter = array_shift($arr1);
$max = 2 * $clientCounter;
$data = $dislikeSummary = $likeSummary = $all = $dislikedAll = $map = [];
$dataCollection = new DataCollection();
for ($i = 1; $i <= $max; ) {
    $likeStr = $arr1[$i - 1];
    $likeArray = explode(' ', $likeStr);
    $likeCounter = array_shift($likeArray);
    $all = array_unique(array_merge($all, $likeArray), SORT_REGULAR);
    $key = 'like';
    $clientCounter = $i % 2 !== 0 ? $clientCounter - 1: $clientCounter;
    $data[$clientCounter][$key] = $likeArray;
    $i++;
    $dislikeStr = $arr1[$i - 1];
    $dislikeArray = explode(' ', $dislikeStr);
    $dislikeCounter = array_shift($dislikeArray);
    $all = array_unique(array_merge($all, $dislikeArray), SORT_REGULAR);
    $dislikedAll = array_unique(array_merge($dislikedAll, $dislikeArray), SORT_REGULAR);
    $key = 'dislike';
    $data[$clientCounter][$key] = $dislikeArray;
    $i++;
    $dataCollection->push(new Data(
        $clientCounter,
        $likeArray,
        implode('_',$likeArray),
        (int) $likeCounter,
        $dislikeArray,
        implode('_',$dislikeArray),
        (int) $dislikeCounter,
    ));
    foreach ($dislikeArray as $disliked) {
        $dislikeSummary[$disliked] = array_merge($dislikeSummary[$disliked] ?? [], $likeArray);
    }
    foreach ($likeArray as $liked) {
        $likeSummary[$liked] = array_merge($likeSummary[$liked] ?? [], $dislikeArray);
    }
    sort($dislikeArray);
    sort($likeArray);
    $map[implode('_', $likeArray)][] = $dislikeArray == [] ? null : implode('_', $dislikeArray);
}
$result = $mapping = [];
array_push($all, '');
sort($all);
foreach ($all as $product) {
    foreach ($dataCollection->all() as &$item) {
        if ($item->dislikedPrefix === $product && !in_array($product, $result)) {
            $result = array_merge($result, $item->liked);
        }
    }
}

$output = strtr(':how :params', [
    ':how' => count($result),
    ':params' => implode(' ', $result)
    ]
);
file_put_contents('result.' . $argv[1], $output);

$checkResult = explode(' ', $output);
array_shift($checkResult);
$client = 0;
foreach ($dataCollection->all() as $item) {
    $itLiked = 0;
    $itDisLiked = 0;
    foreach ($item->liked as $liked) {
        if (in_array($liked, $checkResult)) {
            $itLiked++;
        }
    }
    foreach ($item->disliked as $disliked) {
        if (in_array($disliked, $checkResult)) {
            $itDisLiked++;
        }
    }
    if ($item->likedCount === $itLiked && $itDisLiked === 0) {
        $client++;
    }
}

echo "Clients: {$client}\n";
