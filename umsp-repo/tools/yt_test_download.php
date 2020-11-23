<?php
$ids = [
    "9fZrBf8wmZI",
    "MdaQ7GYGB9Q",
    "hLUrgHWxWCU",
    "gMT3G5orusU",
    "NkU0Tm0dS6M",
    'tYbP0hNR8xI',
    '6HEGET3fVQc',
    '5lDkcP31dwM',
    'rLppqLxALfQ',
    'pwHqY_4nsJ4',
    'ZN23jd9e8Cc',
    'lj-SnrYI95E',
    'S1uZA8nEWAQ',
];


foreach ($ids as $id) {
    var_dump($id);
    $cmd = "ydl $id -f 22/18 -g";
    $url = trim(shell_exec($cmd));
    var_dump(_TestDownload($url));
}

function _TestDownload($url)
{
    $size    = 500000;
    $content = file_get_contents($url, false, null, 0, $size);
    if ($size == strlen($content)) {
        return true;
    }
    echo("_TestDownload -> size downloaded wrong:" . strlen($content) . "\n");
    return false;
}
