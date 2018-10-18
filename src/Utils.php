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
    static function statusConvert($typecode, $statuscode){
        $statusconvertarr = array(
            256 => 'OFFLINE',
            257 => 'OFF',
            258 => 'ON',
            259 => 'ONLINE',
            260 => 'POWER ON',
            261 => 'POWER OFF'
        );
        if($statuscode > 255)
            $statusname = $statusconvertarr[$statuscode] ?: '';
        else{
            switch(intval($typecode)){
                case 8:
                case 12:
                    $statusname = $statuscode & 0x20 ? 'FAIL' : 'PASS';
                    break;
                case 9:
                    if ($statuscode & 0x80) {
                        $statusname = 'SERVICE';
                    } else if (!($statuscode & 0x10) && ($statuscode & 0x40)) {
                        $statusname = 'INFRARED';
                    } else if ($statuscode & 0x40) {
                        $statusname = 'STANDBY';
                    } else if ($statuscode & 0x20) {
                        $statusname = 'FAIL';
                    } else {
                        $statusname = 'PASS';
                    }
                    break;
                case 11:
                case 13:
                case 10:
                    if ($statuscode & 0x40) {
                        $statusname = 'STAND BY';
                    } else if ($statuscode & 0x20) {
                        $statusname = 'FAIL';
                    } else {
                        $statusname = 'PASS';
                    }
                    break;
            }        
        }
        return $statusname;
    }

}

?>