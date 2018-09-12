<?php

$server = new swoole_websocket_server("0.0.0.0", 6666);
$server->set(array(
    "worker_num" => 1, 
    "task_worker_num" => 1
));
$server->on("workerstart", function() use($server){
    if(!$server->taskworker){
        try{
            $db = new PDO(
                "sqlsrv: Server = 10.0.2.2, 1433; Database = kaifaiot_dev_gj", "sa", "abc123456",
            //    "sqlsrv: Server = 10.0.2.2, 1433; Database = testdb", "sa", "abc123456",
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        }catch(PDOException $e){
            var_dump($e->errorInfo);
            $server->shutdown();
        }
        $i = 0;
        $server->tick(1000, function() use($db, &$i, $server){
            echo ++$i." : ";
            try{
                
                if($pre = $db->prepare("SELECT TOP 10
                    ci.slot,ci.port as cport,ci.type,di.sn,mrs.raw_status as status,p.id,p.name as p5name          
                    FROM mpoint_realtime_status AS mrs
                    LEFT JOIN mpoint AS m
                    ON mrs.mpoint_id = m.id
                    LEFT JOIN place AS p
                    ON m.pid = p.id
                    LEFT JOIN channels_info AS ci
                    ON ci.id = m.ciid
                    LEFT JOIN devices_info AS di
                    ON ci.device_id = di.id                 
                    WHERE p.id in(SELECT id
                    FROM dbo.fn_GetPlace(4) 
                    WHERE level=5)"))
                    
                //if($pre = $db->prepare(
                    //"SELECT * FROM dbo.testTable"))
                    echo "good pre; ";
                //if($pre->bindValue(1, 4))
                    //echo "good bind; ";
                if($pre->execute())
                    echo "good execute; ";
                if($arr = $pre->fetch())
                    echo "good fetch; ".PHP_EOL;
                //print_r($arr);
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