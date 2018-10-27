<?php

namespace WSM;

class Task{
    public $name;

    function onTask($server, $task_id, $src_worker_id, $task_arr){
        $logger = Loggers::$loggers["task_worker"];
        //$tick_i = &$server->tick_i;

        $logger->debug('onTask: ', array(
            'task_content' => $task_arr["head"],
            'task_worker_name' => $this->name,
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
            
            case MsgLabel::TASK_CLIENT_CLASSIFY:
                if(count($fd_arr = $this->clientClassify($server)) != 0)
                    return Utils::readyArr(MsgLabel::FINISH_CLIENT_CLASSIFY, $fd_arr);
                break;

            case MsgLabel::TASK_PUSH:
                //$time = microtime(true);
                $this->pushData($server, $task_arr["body"]["monitor_type"], $task_arr["body"]["monitor_id_fd"], $task_arr["body"]["monitor_need"]);
                //echo "Data pushing takes: " . (microtime(true) - $time) . PHP_EOL;
                break;

            default:
                break;
        }
    }

    function tableUpdate($server){
        $server->var_table->incr("table_seq", "int_value");//流水号递增
        $table_seq = $server->var_table->get("table_seq", "int_value");
        $place_table = $server->place_table;
        $device_table = $server->device_table;
        $channel_table = $server->channel_table;
        //更新区域表
        Db::getPlaceTable();
        while($row = Db::$place_table_pre->fetch(\PDO::FETCH_ASSOC)){
            if(!$place_table->exist($row["id"]))
                $status = "POWER ON";//新的区域点状态置为默认值
            else
                $status = $place_table->get($row["id"], "status");
            $place_table->set($row["id"], array(
                "id" => $row["id"],
                "pid" => $row["pid"],
                "name" => $row["name"],
                "level" => $row["level"],
                "status" => $status,
                "p_table_seq" => $table_seq,
            ));
        }
        foreach($place_table as $p_table){ //清理被删除的区域
            if($p_table["p_table_seq"] != $table_seq)
                $place_table->del($p_table["id"]);
        }
        //更新设备表
        Db::getDeviceTable();
        while($row = Db::$device_table_pre->fetch(\PDO::FETCH_ASSOC)){
            //判断该行数据是否发生改变
            $d_table_status = MsgLabel::TABLE_UNCHANGED;
            if($d_table = $device_table->get($row["station_id"])){
                foreach(array_keys($row) as $key){
                    if($d_table[$key] != $row[$key]){
                        $d_table_status = MsgLabel::TABLE_CHANGED;
                    } 
                }
            }else{
                $d_table_status = MsgLabel::TABLE_CHANGED;
            }
            //var_dump($d_table_status);
            $device_table->set("{$row["station_id"]}", array(
                "ip" => $row["ip"],
                "line_id" => $row["line_id"],
                "line_name" => $place_table->get($row["line_id"], "name"),
                "rssi" => $row["rssi"],
                "sn" => $row["sn"],
                "rssi_update_time" => $row["rssi_update_time"],
                "station_id" => $row["station_id"],
                "station_name" => $row["station_name"],
                "d_table_seq" => $table_seq,
                "d_table_status" => $d_table_status,
            ));
        }
        //更新通道监控点表
        Db::getChannelTable();
        $channel_table_change = false;//设置循环体外变量来标识该表是否变化
        while($row = Db::$channel_table_pre->fetch(\PDO::FETCH_ASSOC)){
            $ch_table_status = MsgLabel::TABLE_UNCHANGED;
            if($ch_table = $channel_table->get($row["point_id"])){
                foreach(array_keys($row) as $key){
                    if($ch_table[$key] != $row[$key]){
                        $ch_table_status = MsgLabel::TABLE_CHANGED;
                        $channel_table_change = true;
                    }  
                }
            }else{
                $ch_table_status = MsgLabel::TABLE_CHANGED;
                $channel_table_change = true;
            }
            $channel_table->set("{$row["point_id"]}", array(
                "sn" => $row["sn"],
                "slot" => $row["slot"],
                "port" => $row["port"],
                "type" => $row["type"],
                "point_id" => $row["point_id"],
                "point_name" => $row["point_name"],
                "station_id" => $row["station_id"],
                "station_name" => $row["station_name"],
                "line_id" => $row["line_id"],
                "real_status" => $row["real_status"],
                "ch_table_seq" => $table_seq,
                "ch_table_status" => $ch_table_status,
            )); 
        }
        //若本次通道表更新发生变化，则更新区域表状态
        if($channel_table_change){
            //将重置状态部分写在此处，避免递归更新时出现BUG
            foreach($place_table as $p_table){
                $place_table->set($p_table["id"], array("status" => "POWER ON"));
            }
            foreach($channel_table as $ch_table){
                $this->placeUpdate($place_table, $ch_table["station_id"], Utils::statusConvert($ch_table["type"], $ch_table["real_status"]));
            }
            $server->var_table->set("p_table_status", array("string_value" => MsgLabel::TABLE_CHANGED));
        }
        return true;
    }

    /**
     * 按照推送类型对客户端进行分类
     */
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
                case "index":
                    if($c_table["index_id"])
                        $fd_arr["index"][$c_table["index_id"]][] = $c_table["fd"];
                    break;
                default:
                    break;
            }
        }
        return $fd_arr;
    }

    /**
     * 根据客户端fd的分类进行推送
     */
    function pushData($server, $monitor_type, $monitor_id_fd, $monitor_need){
        $table_seq = $server->var_table->get("table_seq", "int_value");
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
            case "index":
                $msg_label = MsgLabel::DATA_INDEX;
                $get_data_func = "getIndexData";
                break;
            default:
                echo "非法 monitor_type" . $monitor_type . PHP_EOL;
                return;
                break;
        }
        foreach(array_keys($monitor_id_fd) as $monitor_id){
            if($monitor_data = $this->$get_data_func($server, $monitor_id, $table_seq, $monitor_need)){
                if($monitor_type == "region"){ //若推送类型为区域，则对数据进行分页处理
                    foreach($monitor_id_fd[$monitor_id] as $fd){
                        if($client_table->exist($fd)){
                            $rows = [];
                            $region_page = $client_table->get($fd, "region_page");
                            for($i = ($region_page - 1) * Config::LINE_NUM_PER_PAGE;
                                $i <= $region_page * Config::LINE_NUM_PER_PAGE - 1; 
                                ++$i){
                                if(isset($monitor_data["rows"][$i]))
                                    $region_data["rows"][] = $monitor_data["rows"][$i];
                            }
                            $region_data["totals"] = $monitor_data["totals"];
                            $server->push($fd, json_encode(Utils::readyArr($msg_label, $region_data)));
                        }
                    }
                }else{
                    foreach($monitor_id_fd[$monitor_id] as $fd){
                        if($client_table->exist($fd))
                            $server->push($fd, json_encode(Utils::readyArr($msg_label, $monitor_data)));
                    }
                }
                echo "Data Pushed\n";
            }
        }        
    }

    function getRegionData($server, $region_id, $table_seq, $monitor_need){
        $region_data = ["totals" => 0, "rows" => array()];//提前定义该数组避免当所选区域下无线体时 rows 未定义的报警
        $line_num = 0;
        $data_change = false;
        $channel_table = $server->channel_table;
        $place_table = $server->place_table;
        $line_id_arr = [];//临时数组，用于快速寻找 line_id 对应的行数
        //先整理该区域下所有线体，避免为了兼容输出格式而对 channel_table 多次遍历
        foreach($place_table as $p_table){
            if($p_table["pid"] == $region_id){
                $region_data["rows"][$line_num] = array(
                    "line_id" => $p_table["id"],
                    "line_name" => $p_table["name"],
                );
                $line_id_arr[$p_table["id"]] = $line_num;//记录该线体id对应输出数据的行数
                $line_num ++;
            }
        }
        //先生成未过期结果集，避免在判断数据有效性时丢失数据
        foreach($channel_table as $ch_table){
            if($place_table->get($ch_table["line_id"], "pid") == $region_id &&
                $ch_table["ch_table_seq"] == $table_seq){
                $region_data["rows"][$line_id_arr[$ch_table["line_id"]]]["line"][] = array(
                    "slot" => $ch_table["slot"],
                    "cport" => $ch_table["port"],
                    "type" => $ch_table["type"],
                    "sn" => $ch_table["sn"],
                    "status" => $ch_table["real_status"],
                    "station_id" => $ch_table["station_id"],
                    "station_name" => $ch_table["station_name"]
                );
                //检测是否数据发生变化
                if($ch_table["ch_table_status"] == MsgLabel::TABLE_CHANGED){
                    $data_change = true;
                }
            } 
        }
        //清理该数据整理方式所可能产生的某些未开监控点的线体
        foreach($region_data["rows"] as $r_data_row){
            if(!isset($r_data_row["line"]))
                unset($region_data["rows"][$line_id_arr[$r_data_row["line_id"]]]);
        }
        $region_data["totals"] = count($region_data["rows"]);

        if($data_change || $monitor_need){
            return $region_data;
        }else
            return false;
    }

    function getLineData($server, $line_id, $table_seq, $monitor_need){
        $data_change = false;
        $channel_table = $server->channel_table;
        $line_data = [];
        foreach($channel_table as $ch_table){
            if($line_id == $ch_table["line_id"] &&
                $ch_table["ch_table_seq"] == $table_seq){
                $line_data[]= array(
                    "slot" => $ch_table["slot"],
                    "cport" => $ch_table["port"],
                    "type" => Utils::typeConvert($ch_table["type"]),
                    "sn" => $ch_table["sn"],
                    "status" => $ch_table["real_status"],
                    "id" => $ch_table["station_id"],
                    "p5name" => $ch_table["station_name"]
                );
                if($ch_table["ch_table_status"] == MsgLabel::TABLE_CHANGED)
                    $data_change = true;
            }
        }
        if($data_change || $monitor_need)
            return $line_data;
        else
            return false;
    }

    function getStationData($server, $station_id, $table_seq, $monitor_need){
        $data_change = false;
        $channel_table = $server->channel_table;
        $station_data = [];
        foreach($channel_table as $ch_table){
            if($station_id == $ch_table["station_id"] &&
                $ch_table["ch_table_seq"] == $table_seq){
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
                if($ch_table["ch_table_status"] == MsgLabel::TABLE_CHANGED)
                    $data_change = true;
            }
        }
        if($data_change || $monitor_need)
            return $station_data;
        else
            return false;
    }   

    function getPlaceData($server, $place_id, $table_seq, $monitor_need){
        $place_table = $server->place_table;
        $place_data = [];
        foreach($place_table as $p_table){
            if($p_table["pid"] == $place_id){
                $place_data["{$p_table["id"]}"] = $p_table["status"];
            }
            //若为了偷懒将重置地点状态写到此处将出现美好的BUG
            //$place_table->set($p_table["id"], array("status" => "POWER ON"));
        }
        //地点实时状态是否更新取决于该全局变量
        if($server->var_table->get("p_table_status", "string_value") == MsgLabel::TABLE_CHANGED || $monitor_need){
            //推送一次后重置状态
            $server->var_table->set("p_table_status", array("string_value" => MsgLabel::TABLE_UNCHANGED));
            return $place_data;
        }else{
            return false;
        }    
    }

    function getRssiData($server, $rssi_line_id, $table_seq, $monitor_need){
        $data_change = false;
        $device_table = $server->device_table;
        $rssi_data = [];
        foreach($device_table as $d_table ){
            if($d_table["line_id"] == $rssi_line_id &&
                $d_table["d_table_seq"] == $table_seq){
                $rssi_data[] = array(
                    "ip" => $d_table["ip"],
                    "line_id" =>$d_table["line_id"],
                    "line_name" => $d_table["line_name"],
                    "rssi" => $d_table["rssi"],
                    "sn" => $d_table["sn"],
                    "updatetime" => $d_table["rssi_update_time"]
                );
                if($d_table["d_table_status"] == MsgLabel::TABLE_CHANGED)
                    $data_change = true;
            }
        }
        if($data_change || $monitor_need)
            return $rssi_data;
        else
            return false;
    }

    function getIndexData($server, $index_id, $table_seq, $monitor_need){
        $index_data = [];
        $data_change = false;
        $place_table = $server->place_table;
        $channel_table = $server->channel_table;
        $total_count = ["FAIL" => 0, "OFFLINE" => 0, "PASS" => 0];
        $type_count = array(8 => [], 9 => [], 10 => [], 11 => [], 12 => [], 13 => [], 14 => [], 15 => [], 16 => [], 17=>[], "mpointTotalNumber" => 0);
        foreach($channel_table as $ch_table){
            if($this->idPaternityTest($place_table, $ch_table["station_id"], $index_id)&&
                $ch_table["ch_table_seq"] == $table_seq){
                //echo "enter the counting\n";
                $status = Utils::statusConvert($ch_table["type"], $ch_table["real_status"]);
                if($status != "STANDBY"){ //STANDBY 状态不认为是报警
                    if($status == "OFFLINE")
                        $total_count["OFFLINE"]++;
                    elseif($status == "PASS")
                        $total_count["PASS"]++;
                    else
                        $total_count["FAIL"]++;
                }
                //抑制首次出现该 $status 时执行该操作的警告
                @$type_count[$ch_table["type"]]["total"]++;
                @$type_count[$ch_table["type"]][$status]++;
                @$type_count["mpointTotalNumber"]++;
                if($ch_table["ch_table_status"] == MsgLabel::TABLE_CHANGED)
                    $data_change = true;
            }
        }

        if($data_change || $monitor_need){
            $index_data["total_count"] = $total_count;
            $index_data["type_count"] = $type_count;
            //print_r($index_data);
            return $index_data;
        }else
            return false;
    }

    /**
     * 递归更新父节点状态
     */
    function placeUpdate($place_table, $id, $status){
        $p_table = $place_table->get($id);
        //若当前区域表地点状态优先级更低（值更大）则将新状态赋予该地点
        if(!$p_table["status"])
            echo "p_table: " . $p_table["status"] . PHP_EOL;
        if(!$status)
            echo "status: " . $status . PHP_EOL;
        if(Utils::statusLevel($p_table["status"]) > Utils::statusLevel($status)){
            $place_table->set($p_table["id"], array("status" => $status));
            if($p_table["level"] !=  1){
                $this->placeUpdate($place_table, $p_table["pid"], $status);
            }
        }else
            return;
    }

    /**
     * 判断该监控点id是否位于某个指定父节点id之下
     * 该函数名翻译之后叫作 ID 亲子鉴定 非常形象 ^_^
     */
    function idPaternityTest($place_table, $child_id, $parent_id){
        if($child_id != $parent_id){
            $level = $place_table->get($child_id, "level");
            if($level === 1){
                //echo "to the top\n";
                return false; 
            }
            //echo " now the level $level doesn't match \n";
            $pid = $place_table->get($child_id, "pid");
            if($this->idPaternityTest($place_table, $pid, $parent_id))
                return true;  
        }else{
            //echo " now the level $level matches \n";
            return true;
        }
            
    }
}

?>