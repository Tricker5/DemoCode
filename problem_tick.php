<?php
$server = new swoole_websocket_server("0.0.0.0", 6666);
$server->set(array(
    "worker_num" => 1, 
    "task_worker_num" => 1
));
$server->on("workerstart", function() use($server){
    if(!$server->taskworker){
        //在tick查询之前建立可复用连接
        try{
            $db = new PDO(
                "sqlsrv: Server = 10.0.2.2, 1433; Database = testdb", "sa", "abc123456",
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        }catch(PDOException $e){
            var_dump($e->errorInfo);
            $server->shutdown();
        }
        $i = 0;//查询次数
        $server->tick(1000, function() use($db, &$i, $server){
            echo ++$i." : ";
            try{                    
                if($pre = $db->prepare("SELECT * FROM testTable"))//某个查询语句
                    echo "good pre; ";
                if($pre->execute())
                    echo "good execute; ";
                if($arr = $pre->fetch())
                    echo "good fetch; ".PHP_EOL;
            }catch(PDOException $e){
                var_dump($e->errorInfo);
                $server->shutdown();
            }
        });
    }
});
$server->on("open", function(){});
$server->on("message", function(){});
$server->on("close", function(){});
$server->on("task", function(){});
$server->on("finish",function(){});
    
$server->start();

?>