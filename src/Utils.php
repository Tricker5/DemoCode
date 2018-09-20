<?php

namespace WSM;

class Utils{

    //数据打包
    static function readyArr($head = null, $body = null){
        $readyarr = array(
            "head" => $head,
            "body" => $body
        );
//        $readydata = json_encode($readydata);
        return $readyarr;
    }

    //查询结果"type"转换
    static function typeConvert($typecode){
        $typeconvertarr = array(
            '8' => '高阻',
            '9' => '手环',
            '10' => '平衡电压',
            '11' => '温度',
            '12' => '低阻',
            '13' => '温度',
        );
        $typename = $typeconvertarr[$typecode] ?: '';
        return $typename;
    }

    //查询结果"status"转换
    static function statusConvert($statuscode){
        $statusconvertarr = array(
            '256' => 'OFFLINE',
            '257' => 'OFF',
            '258' => 'ON',
            '259' => 'ONLINE',
            '260' => 'POWER ON'
        );
        $statusname = $statusconvertarr[$statuscode] ?: '';
        return $statusname;
    }

}

?>