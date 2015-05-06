<?php

/*
 * https://consumer.fda.gov.tw/Food/ashx/getUnsafeFoodResult.ashx?selectGroup=1&start=0&pageNo=50
 */

$tmpPath = __DIR__ . '/tmp';
$listTmpPath = $tmpPath . '/list';
if (!file_exists($listTmpPath)) {
    mkdir($listTmpPath, 0777, true);
}
$itemTmpPath = $tmpPath . '/item';
if (!file_exists($itemTmpPath)) {
    mkdir($itemTmpPath, 0777, true);
}
$totalPage = $currentPage = 1;

while ($currentPage <= $totalPage) {
    echo "processing page {$currentPage}\n";
    $pageTmpFile = "{$listTmpPath}/page_{$currentPage}";
    if (!file_exists($pageTmpFile)) {
        $offset = $currentPage - 1;
        file_put_contents($pageTmpFile, file_get_contents("https://consumer.fda.gov.tw/Food/ashx/getUnsafeFoodResult.ashx?start={$offset}&pageNo=50"));
    }
    $xml = simplexml_load_file($pageTmpFile);
    if ($currentPage === $totalPage && $totalPage === 1) {
        $totalPage = ceil((int) $xml->docNo / 50);
    }
    foreach ($xml->results->aResult AS $item) {
        $itemTmpFile = "{$itemTmpPath}/item_{$item->idx}";
        if (!file_exists($itemTmpFile)) {
            $url = 'https://consumer.fda.gov.tw/Food/detail/UnsafeFoodD.aspx';
            $options = array(
                'http' => array(
                    'header' => "Content-type: application/x-www-form-urlencoded; charset=UTF-8\r\n",
                    'method' => 'POST',
                    'content' => 'pid=' . $item->idx,
                ),
            );
            $context = stream_context_create($options);
            file_put_contents($itemTmpFile, file_get_contents($url, false, $context));
        }
    }
    ++$currentPage;
}