<?php

use System\InvalidParamException;

require 'vendor/autoload.php';
require 'system/helpers.php';
require 'TrademarkSearch.php';

$searchQuery = $argv[1] ?? null;

if (!$searchQuery) {
    die("Usage: php Script.php <search_query>\n");
}

try {
    $trademarkSearch = new TrademarkSearch();
    $trademarkSearch->searchTrademarks($searchQuery);
    $trademarkSearch->setOutput();
} catch (InvalidParamException $e) {
    echo $e->getMessage();
} catch (Exception $e) {
    echo $e->getMessage();
}
