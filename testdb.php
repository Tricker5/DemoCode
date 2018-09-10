<?php

require __DIR__.'/vendor/autoload.php';

use WSM\Db;

$conn = microtime(true);
$testDb = new Db;
$conn = microtime(true) - $conn;
echo "connect time: ".$conn.PHP_EOL;

echo "before sleep... ".PHP_EOL;
//sleep(10);
echo "after sleep... ".PHP_EOL;
$start = microtime(true);



if($testarr = $testDb->testFetch())
    print_r($testarr = $testDb->testFetch());

$start = microtime(true) - $start;
echo $start.PHP_EOL;

?>