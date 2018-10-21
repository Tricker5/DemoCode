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
            
            case MsgLabel::SET_ID_PLACE:
                echo "配置客户端监控地点...".PHP_EOL;
                $client_table->set("$fd", ["place_id" => $data["body"]]);
                echo "已将 $fd 号客户端监控地点ID配置为：".$client_table->get("$fd", "place_id").PHP_EOL;
                break;

            case MsgLabel::SET_ID_RSSI:
                echo "配置客户端监控信号线体...".PHP_EOL;
                $client_table->set("$fd", ["rssi_line_id" => $data["body"]]);
                echo "已将 $fd 号客户端监控信号线体ID配置为：".$client_table->get("$fd", "rssi_line_id").PHP_EOL;
                break;

            default:
                echo "未能识别来自 $fd 号客户端的信息：".PHP_EOL;
                break;
        }

        //设定参数满足时立刻推送
        if($client_table->exist($fd) && 
            ($monitor_type = $client_table->get("$fd", "monitor_type")) != 0 &&
            ($monitor_id = $client_table->get("$fd", "$monitor_type" . "_id")) != 0){
                $server->task(Utils::readyArr(MsgLabel::TASK_PUSH, array(
                    "monitor_type" => $monitor_type,
                    "monitor_id_fd" => array($monitor_id => $fd) 
                )));
        }
    }
}

?>