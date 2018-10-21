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

    static function statusLevel($status){
        $status_level_arr = array(
            'SERVICE' => 1, //service
            'FAIL' => 2, //fail
            'INFRARED' => 3, //infrared
            'PASS' => 4, //pass
            'STANDBY' => 5, //standby
            'OFFLINE' => 6, //OFFLINE
            'OFF' => 7, // OFF
            'ON' => 8, //ON
            'ONLINE' => 9, //ONLINE
            'POWER ON' => 10, //POWER ON 
        );
        return $status_level_arr[$status];
    }

    //查询结果"type"转换
    static function typeConvert($typecode){
        $typeconvertarr = array(
            '8' => 'GND_H',
            '9' => 'WS',
            '10' => 'VB',
            '11' => 'TEMP',
            '12' => 'GND_L',
            '13' => 'HUMI',
            '14' => 'ESI_V',
            '15' => 'ESI_R',
            '16' => 'ESD_V',
            '17' => 'ESD_R',
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
                case 14:
                case 15:
                case 16:
                case 17:
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
                        $statusname = 'STANDBY';
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