<?php

require __DIR__.'/vendor/autoload.php';

use WSM\Db;


$testDb = new Db;
print_r($testDb->testFetch());


/*
$pdo = new \PDO("sqlsrv: Server = 10.0.2.2, 1433;
                             Database = testdb", 
                            "sa", 
                            "abc123456");

$pdores = $pdo->query("SELECT * FROM testdb.dbo.testTable");
print_r($pdores->fetchall(\PDO::FETCH_ASSOC));
*/
?>