<?php

namespace WSM;

class Task{
    public $name;

    function onTask($server, $task_id, $src_worker_id, $taskarr){
        $logger = Loggers::$loggers["task_worker"];
        //$tick_i = &$server->tick_i;

        $logger->debug('onTask: ', array(
            'task_content' => $taskarr["head"],
            'name' => $this->name,
            'worker_pid' => $server->worker_pid,
            'worker_id' => $server->worker_id,
            'src_worker_id' => $src_worker_id,
            'task_id' => $task_id,
        ));

        switch($taskarr["head"]){                
            case MsgLabel::TASK_TABLE_UPDATE:
                //$time = microtime(true);
                if($this->tableUpdate($server)){
                    //echo "Table updating takes: " . (microtime(true) - $time) . PHP_EOL;
                    return MsgLabel::FINISH_TABLE_UPDATE;
                }
                break;
            
            case MsgLabel::TASK_PLACE_INIT:
                $this->placeInit($server->place_table);
                break;

            case MsgLabel::TASK_PUSH:
                //$time = microtime(true);
                $this->pushData($server);
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
        Db::getChannelTable();
        $place_table = $server->place_table;
        $channel_table = $server->channel_table;
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

    function pushData($server){
        $client_table = $server->client_table;
        $place_table = $server->place_table;
        $channel_table = $server->channel_table;
        $fd_arr = ["region" => [], "line" => [], "station" => [], "place"=>[], "rssi"=>[]];
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
        foreach(array_keys($fd_arr["region"]) as $region_id){
            $region_data = $this->getRegionData($server, $region_id);
            foreach($fd_arr["region"][$region_id] as $fd){
                if($client_table->exist($fd))
                    $server->push($fd, json_encode(Utils::readyArr(MsgLabel::DATA_REGION, $region_data)));
            }
        }
        foreach(array_keys($fd_arr["line"]) as $line_id){
            $line_data = $this->getLineData($server, $line_id);
            //var_dump(count($line_data));
            foreach($fd_arr["line"][$line_id] as $fd){
                if($client_table->exist($fd))
                    $server->push($fd, json_encode(Utils::readyArr(MsgLabel::DATA_LINE, $line_data)));
            }
        }
        foreach(array_keys($fd_arr["station"]) as $station_id){
            $station_data = $this->getStationData($server, $station_id);
            foreach($fd_arr["station"][$station_id] as $fd){
                if($client_table->exist($fd))
                    $server->push($fd, json_encode(Utils::readyArr(MsgLabel::DATA_STATION, $station_data)));
            }
        }
        foreach(array_keys($fd_arr["place"]) as $place_id){
            $place_data = $this->getPlaceData($server, $place_id);
            foreach($fd_arr["place"][$place_id] as $fd){
                if($client_table->exist($fd))
                    $server->push($fd, json_encode(Utils::readyArr(MsgLabel::DATA_PLACE, $place_data)));
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
                        $line_arr = $channel_table->get($channel_table->key());
                        $region_data["rows"]["line"][]= array(
                            "slot" => $line_arr["slot"],
                            "cport" => $line_arr["port"],
                            "type" => $line_arr["type"],
                            "sn" => $line_arr["sn"],
                            "status" => $line_arr["real_status"],
                            "station_id" => $line_arr["station_id"],
                            "station_name" => $line_arr["station_name"]
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
                $line_arr = $channel_table->get($channel_table->key());
                $line_data[]= array(
                    "slot" => $line_arr["slot"],
                    "cport" => $line_arr["port"],
                    "type" => Utils::typeConvert($line_arr["type"]),
                    "sn" => $line_arr["sn"],
                    "status" => $line_arr["real_status"],
                    "id" => $line_arr["station_id"],
                    "p5name" => $line_arr["station_name"]
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
                $station_arr = $channel_table->get($channel_table->key());
                $station_data[] = array(
                    "stationId" => $station_arr["station_id"],
                    "status" => Utils::statusConvert($station_arr["type"], $station_arr["real_status"]),
                    "mpoint_id" => $station_arr["point_id"],
                    "name" => $station_arr["point_name"],
                    "sn" => $station_arr["sn"],
                    "slot" => $station_arr["slot"],
                    "port" => $station_arr["port"],
                    "type" => $station_arr["type"]
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