<?php

namespace WSM;

class CodeConvert{

    static function typeConvert($arr){
        $typeconvertarr = array(
            '8' => '高阻',
            '9' => '手环',
            '10' => '平衡电压',
            '11' => '温度',
            '12' => '低阻',
            '13' => '温度',
        );
        for($i = 0; $i < sizeof($arr); $i++){
            $typecode = $arr[$i]["type"];
            $typename = $typeconvertarr[$typecode] ?: '';
            $arr[$i]["type"] = $typename;
        }
        return $arr;
    }

    static function statusConvert($arr){
        $statusconvertarr = array(
            '256' => 'OFFLINE',
            '257' => 'OFF',
            '258' => 'ON',
            '259' => 'ONLINE',
            '260' => 'POWER ON'
        );
        for($i = 0; $i < sizeof($arr); $i++){
            $statuscode = $arr[$i]["status"];
            $statusname = $statusconvertarr[$statuscode] ?: '';
            $arr[$i]["status"] = $statusname;
        }
        return $arr;
    }

}

?>