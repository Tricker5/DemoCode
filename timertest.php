<?php

    $time = 60-time()%60;
    echo $time.PHP_EOL;
    echo date("Y-m-d, H:i:s").PHP_EOL;
    swoole_timer_after(1000*$time, function(){
        echo date("Y-m-d, H:i:s").PHP_EOL;
    });
?>