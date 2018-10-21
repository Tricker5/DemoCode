<?php

namespace WSM;

class Task{
    public $name;

    function onTask($server, $task_id, $src_worker_id, $task_arr){
        $logger = Loggers::$loggers["task_worker"];
        //$tick_i = &$server->tick_i;

        $logger->debug('onTask: ', array(
            'task_content' => $task_arr["head"],
            'name' => $this->name,
            'worker_pid' => $server->worker_pid,
            'worker_id' => $server->worker_id,
            'src_worker_id' => $src_worker_id,
            'task_id' => $task_id,
        ));

        switch($task_arr["head"]){                
            case MsgLabel::TASK_TABLE_UPDATE:
                //$time = microtime(true);
                if($this->tableUpdate($server)){
                    //echo "Table updating takes: " . (microtime(true) - $time) . PHP_EOL;
                    return Utils::readyArr(MsgLabel::FINISH_TABLE_UPDATE);
                }
                break;
            
            case MsgLabel::TASK_PLACE_INIT:
                $this->placeInit($server->place_table);
                break;
            
            case MsgLabel::TASK_CLIENT_CLASSIFY:
                if(count($fd_arr = $this->clientClassify($server)) != 0)
                    return Utils::readyArr(MsgLabel::FINISH_CLIENT_CLASSIFY, $fd_arr);
                break;

            case MsgLabel::TASK_PUSH:
                //$time = microtime(true);
                $this->pushData($server, $task_arr["body"]["monitor_type"], $task_arr["body"]["monitor_id_fd"]);
                //echo "Data pushing takes: " . (microtime(true) - $time) . PHP_EOL;
                break;

            default:
                break;
        }
    }

    function placeInit($place_table){
        Db::getPlaceTable();
        while($row = Db::$place_table_pre->fetch(\PDO::FETCH_ASSOC)){
            $place_table->set("{$row["id"]}", array(
                "id" => $row["id"],
                "pid" => $row["pid"],
                "name" => trim($row["name"]),
                "level" => $row["level"],
                "status" => "POWER ON"
            ));
        }
    }

    function tableUpdate($server){
        $place_table = $server->place_table;
        $device_table = $server->device_table;
        $channel_table = $server->channel_table;
        //更新设备表
        Db::getDeviceTable();
        while($row = Db::$device_table_pre->fetch(\PDO::FETCH_ASSOC)){
            $device_table->set("{$row["station_id"]}", array(
                "ip" => $row["ip"],
                "line_id" => $row["line_id"],
                "line_name" => $place_table->get($row["line_id"], "name"),
                "rssi" => $row["rssi"],
                "sn" => $row["sn"],
                "rssi_update_time" => $row["rssi_update_time"],
                "station_id" => $row["station_id"],
                "station_name" => trim($row["station_name"]),
            ));
        }
        //更新通道监控点表
        Db::getChannelTable();
        while($row = Db::$channel_table_pre->fetch(\PDO::FETCH_ASSOC)){
            $channel_table->set("{$row["point_id"]}", array(
                "sn" => $row["sn"],
                "slot" => $row["slot"],
                "port" => $row["port"],
                "type" => $row["type"],
                "point_id" => $row["point_id"],
                "point_name" => trim($row["point_name"]),
                "station_id" => $row["station_id"],
                "station_name" => trim($row["station_name"]),
                "line_id" => $row["line_id"],
                "real_status" => $row["real_status"],
            ));
            $this->placeUpdate($place_table, $row["station_id"], Utils::statusConvert($row["type"], $row["real_status"]));
        }
        return true;
    }

    function clientClassify($server){
        $client_table = $server->client_table;
        $fd_arr = [];
        foreach($client_table as $c_table){
            switch($c_table["monitor_type"]){
                case "region":
                    if($c_table["region_id"])
                        $fd_arr["region"][$c_table["region_id"]][] = $c_table["fd"];
                    break;
                case "line":
                    if($c_table["line_id"])
                        $fd_arr["line"][$c_table["line_id"]][] = $c_table["fd"];
                    break;
                case "station":
                    if($c_table["station_id"])
                        $fd_arr["station"][$c_table["station_id"]][] = $c_table["fd"];
                case "rssi":
                    if($c_table["rssi_line_id"])
                        $fd_arr["rssi"][$c_table["rssi_line_id"]][] = $c_table["fd"];
                    break;
                case "place":
                    if($c_table["place_id"])
                        $fd_arr["place"][$c_table["place_id"]][] = $c_table["fd"];
                    break;
                default:
                    break;
            }
        }
        return $fd_arr;
    }

    function pushData($server, $monitor_type, $monitor_id_fd){
        $client_table = $server->client_table;
        $place_table = $server->place_table;
        $channel_table = $server->channel_table;

        switch($monitor_type){
            case "region":
                $msg_label = MsgLabel::DATA_REGION;
                $get_data_func = "getRegionData";
                break;
            case "line":
                $msg_label = MsgLabel::DATA_LINE;
                $get_data_func = "getLineData";
                break;
            case "station":
                $msg_label = MsgLabel::DATA_STATION;
                $get_data_func = "getStationData";
                break;
            case "place":
                $msg_label = MsgLabel::DATA_PLACE;
                $get_data_func = "getPlaceData";
                break;
            case "rssi":
                $msg_label = MsgLabel::DATA_RSSI;
                $get_data_func = "getRssiData";
                break;
        }
        foreach(array_keys($monitor_id_fd) as $monitor_id){
            $monitor_data = $this->$get_data_func($server, $monitor_id);
            foreach($monitor_id_fd[$monitor_id] as $fd){
                if($client_table->exist($fd))
                    $server->push($fd, json_encode(Utils::readyArr($msg_label, $monitor_data)));
            }
        }        
    }

    function getRegionData($server, $region_id){
        $region_data = [];
        $line_num = 0;
        $channel_table = $server->channel_table;
        $place_table = $server->place_table;
        foreach($place_table as $p_table){
            if($p_table["pid"] == $region_id){
                $line_num++;
                $region_data["rows"]["line_id"] = $p_table["id"]; 
                $region_data["rows"]["line_name"] = $p_table["name"];
                foreach($channel_table as $ch_table){
                    if($p_table["id"] == $ch_table["line_id"]){
                        $region_data["rows"]["line"][]= array(
                            "slot" => $ch_table["slot"],
                            "cport" => $ch_table["port"],
                            "type" => $ch_table["type"],
                            "sn" => $ch_table["sn"],
                            "status" => $ch_table["real_status"],
                            "station_id" => $ch_table["station_id"],
                            "station_name" => $ch_table["station_name"]
                        );
                    }
                }
            }
        }
        $region_data["totals"] = $line_num;
        return $region_data;
    }

    function getLineData($server, $line_id){
        $channel_table = $server->channel_table;
        $line_data = [];
        foreach($channel_table as $ch_table){
            if($line_id == $ch_table["line_id"]){
                $line_data[]= array(
                    "slot" => $ch_table["slot"],
                    "cport" => $ch_table["port"],
                    "type" => Utils::typeConvert($ch_table["type"]),
                    "sn" => $ch_table["sn"],
                    "status" => $ch_table["real_status"],
                    "id" => $ch_table["station_id"],
                    "p5name" => $ch_table["station_name"]
                );
            }
        }
        return $line_data;
    }

    function getStationData($server, $station_id){
        $channel_table = $server->channel_table;
        $station_data = [];
        foreach($channel_table as $ch_table){
            if($station_id == $ch_table["station_id"]){
                $station_data[] = array(
                    "stationId" => $ch_table["station_id"],
                    "status" => Utils::statusConvert($ch_table["type"], $ch_table["real_status"]),
                    "mpoint_id" => $ch_table["point_id"],
                    "name" => $ch_table["point_name"],
                    "sn" => $ch_table["sn"],
                    "slot" => $ch_table["slot"],
                    "port" => $ch_table["port"],
                    "type" => $ch_table["type"]
                );
            }
        }
        return $station_data;
    }

    function getPlaceData($server, $place_id){
        $place_table = $server->place_table;
        $place_data = [];
        foreach($place_table as $p_table){
            if($p_table["pid"] == $place_id){
                $place_data["{$p_table["id"]}"] = $p_table["status"];
            }
            $place_table->set($p_table["id"], array("status" => "POWER ON"));
        }
        return $place_data;
    }

    function getRssiData($server, $rssi_line_id){
        $device_table = $server->device_table;
        $rssi_data = [];
        foreach($device_table as $d_table){
            if($d_table["line_id"] == $rssi_line_id){
                $rssi_data[] = array(
                    "ip" => $d_table["ip"],
                    "line_id" =>$d_table["line_id"],
                    "line_name" => $d_table["line_name"],
                    "rssi" => $d_table["rssi"],
                    "sn" => $d_table["sn"],
                    "updatetime" => $d_table["rssi_update_time"]
                );
            }
        }
        return $rssi_data;
    }

    /**
     * 递归更新父节点状态
     */
    function placeUpdate($place_table, $id, $status){
        $p_table = $place_table->get($id);
        if(Utils::statusLevel($p_table["status"]) > Utils::statusLevel($status)){
            $place_table->set($p_table["id"], array("status" => $status));
            if($p_table["level"] !=  1)
                $this->placeUpdate($place_table, $p_table["pid"], $status);
        }else
            return;
    }
}

?>