<?php

namespace WSM;

class Message{

    function onMessage($server, $frame){
        $fd = $frame->fd;
        $client_table = $server->client_table;
        $data = json_decode($frame->data, true);
        $readyarr = null;
        $logger = Loggers::$loggers['worker'];

        $logger->debug('onMessage: ', array(
            'fd' => $fd,
            'data' => $data
        ));

        //判断message数据头部分head类型并处理
        switch ($data["head"]){

            case MsgLabel::SET_MONITOR_TYPE:
                echo "配置客户端监控类型...".PHP_EOL;
                $client_table->set("$fd", ["monitor_type" => $data["body"]]);
                echo "已将 $fd 号客户端监控类型配置为：".$client_table->get("$fd", "monitor_type").PHP_EOL;
                break;

            case MsgLabel::SET_ID_REGION:
                echo "配置客户端监控区域...".PHP_EOL;
                $client_table->set("$fd", ["region_id" => $data["body"]]);
                echo "已将 $fd 号客户端监控区域ID配置为：".$client_table->get("$fd", "region_id").PHP_EOL;
                break;
            
            case MsgLabel::SET_ID_LINE:
                echo "配置客户端监控线体...".PHP_EOL;
                $client_table->set("$fd", ["line_id" => $data["body"]]);
                echo "已将 $fd 号客户端监控线体ID配置为：".$client_table->get("$fd", "line_id").PHP_EOL;
                break;
            case MsgLabel::SET_ID_STATION:
                echo "配置客户端监控工位...".PHP_EOL;
                $client_table->set("$fd", ["station_id" => $data["body"]]);
                echo "已将 $fd 号客户端监控工位ID配置为：".$client_table->get("$fd", "station_id").PHP_EOL;
                break;

            default:
                echo "未能识别来自 $fd 号客户端的信息：".PHP_EOL;
                break;
        }
    }
}

?>