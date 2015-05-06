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
$targetPath = __DIR__ . '/json';
if (!file_exists($targetPath)) {
    mkdir($targetPath, 0777, true);
}
$totalPage = $currentPage = 1;

$fh = fopen(__DIR__ . '/list.csv', 'w');
fputcsv($fh, array(
    'id',
    '發布日期',
    '標題',
    '報驗受理日期',
    '進口商(公司名稱)',
    '製造廠或出口商名稱',
));

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
        $itemTmp = file_get_contents($itemTmpFile);
        $itemOffset = strpos($itemTmp, '<tbody>');
        $itemTmp = substr($itemTmp, $itemOffset, strpos($itemTmp, '</tbody>') - $itemOffset);
        $lines = explode('</tr>', $itemTmp);
        $lineCount = 0;
        $itemObj = array(
            'id' => (string)$item->idx,
        );
        foreach ($lines AS $line) {
            switch (++$lineCount) {
                case 1:
                    $itemObj['標題'] = trim(strip_tags($line));
                    break;
                case 2:
                    $itemObj['圖片'] = 'https://consumer.fda.gov.tw/DownloadMethod/ashx/showImage.ashx?type=unsafefood&id=' . $item->idx;
                    break;
                default:
                    $cols = preg_split('/\\<\\/t(d|h)\\>/', $line);
                    if (isset($cols[1])) {
                        $itemObj[trim(strip_tags($cols[0]))] = trim(strip_tags($cols[1]));
                    }
            }
        }
        fputcsv($fh, array(
            $item->idx,
            $itemObj['發布日期'],
            $itemObj['標題'],
            $itemObj['報驗受理日期'],
            $itemObj['進口商(公司名稱)'],
            $itemObj['製造廠或出口商名稱'],
        ));
        file_put_contents("{$targetPath}/{$item->idx}.json", json_encode($itemObj, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    ++$currentPage;
}