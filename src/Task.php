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
                if($this->tableUpdate($server->channel_table)){
                    return MsgLabel::FINISH_TABLE_UPDATE;
                }
                break;
            
            case MsgLabel::TASK_PLACE_INIT:
                $this->placeInit($server->place_table);
                break;

            case MsgLabel::TASK_PUSH:
                $this->pushData($server);
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
                "level" => $row["level"]
            ));
        }
    }

    function tableUpdate($channel_table){
        Db::getChannelTable();
        while($row = Db::$channel_table_pre->fetch(\PDO::FETCH_ASSOC)){
            $channel_table->set("{$row["point_id"]}", array(
                "sn" => $row["sn"],
                "slot" => $row["slot"],
                "port" => $row["port"],
                "type" => Utils::typeConvert($row["type"]),
                "point_id" => $row["point_id"],
                "point_name" => trim($row["point_name"]),
                "station_id" => $row["station_id"],
                "station_name" => trim($row["station_name"]),
                "line_id" => $row["line_id"],
                //"seq" => $row["seq"],
                //"pre_status" => $row["pre_status"],
                "real_status" => Utils::statusConvert($row["type"], $row["real_status"]),
                //"dt" => $row["dt"],
                //"pcdt" => $row["pcdt"]
            ));
        }
        return true;
    }

    function pushData($server){
        $client_table = $server->client_table;
        $place_table = $server->place_table;
        $channel_table = $server->channel_table;
        $fd_arr = ["region" => [], "line" => [], "station" => []];
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
                default:
                    break;
            }
        }
        foreach(array_keys($fd_arr["region"]) as $region_id){
            $region_data = $this->getRegionData($server, $region_id);
            foreach($fd_arr["region"][$region_id] as $fd){
                $server->push($fd, json_encode(Utils::readyArr(MsgLabel::DATA_REGION, $region_data)));
            }
        }
        foreach(array_keys($fd_arr["line"]) as $line_id){
            $line_data = $this->getLineData($server, $line_id);
            //var_dump(count($line_data));
            foreach($fd_arr["line"][$line_id] as $fd){
                $server->push($fd, json_encode(Utils::readyArr(MsgLabel::DATA_LINE, $line_data)));
            }
        }
        foreach(array_keys($fd_arr["station"]) as $station_id){
            $station_data = $this->getStationData($server, $station_id);
            foreach($fd_arr["station"][$station_id] as $fd){
                $server->push($fd, json_encode(Utils::readyArr(MsgLabel::DATA_STATION, $station_data)));
            }
        }
        
    }

    function getRegionData($server, $region_id){
        $region_data = [];
        foreach($server->place_table as $p_table){
            if($p_table["pid"] == $region_id)
                $region_data = array_merge($region_data, $this->getLineData($server, $p_table["id"]));
        }
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
                    "type" => $line_arr["type"],
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
                    "status" => $station_arr["real_status"],
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
}

?>