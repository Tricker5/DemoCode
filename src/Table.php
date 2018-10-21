<?php

namespace WSM;

class Table{
    function __construct($server){
        //$time = microtime(true);
        $this->createTable($server);
        //echo "Table creating takes: " . (microtime(true) - $time) . PHP_EOL;
    }

    function createTable($server){
        //创建客户端信息表
        $client_table = new \swoole_table(Config::CLIENT_NUM * 4);
        $client_table->column("fd", \swoole_table::TYPE_INT);
        $client_table->column("monitor_type", \swoole_table::TYPE_STRING, 32);
        $client_table->column("region_id", \swoole_table::TYPE_INT);
        $client_table->column("line_id", \swoole_table::TYPE_INT);
        $client_table->column("station_id", \swoole_table::TYPE_INT);
        $client_table->column("rssi_line_id", \swoole_table::TYPE_INT);
        $client_table->column("place_id", \swoole_table::TYPE_INT);
        $server->client_table = $client_table;
        $server->client_table->create();

        //创建区域表
        $place_table = new \swoole_table(Config::DEVICE_NUM * 4);
        $place_table->column("id", \swoole_table::TYPE_INT);
        $place_table->column("pid", \swoole_table::TYPE_INT);
        $place_table->column("name", \swoole_table::TYPE_STRING, 32);
        $place_table->column("level", \swoole_table::TYPE_INT, 1);
        $place_table->column("status", \swoole_table::TYPE_STRING, 16);
        $server->place_table = $place_table;
        $server->place_table->create();

        //创建设备表
        $device_table = new \swoole_table(Config::DEVICE_NUM * 4);
        $device_table->column("sn", \swoole_table::TYPE_INT, 8);
        $device_table->column("ip", \swoole_table::TYPE_STRING, 32);
        $device_table->column("rssi", \swoole_table::TYPE_INT, 2);
        $device_table->column("station_id", \swoole_table::TYPE_INT);
        $device_table->column("station_name", \swoole_table::TYPE_STRING, 32);
        $device_table->column("line_id", \swoole_table::TYPE_INT);
        $device_table->column("line_name", \swoole_table::TYPE_STRING, 32);
        $device_table->column("rssi_update_time", \swoole_table::TYPE_INT, 8);
        $server->device_table = $device_table;
        $server->device_table->create();

        //创建通道监控点表
        $channel_table = new \swoole_table(Config::DEVICE_NUM * 32 * 4);

        $channel_table->column("sn", \swoole_table::TYPE_INT, 8);
        $channel_table->column("slot", \swoole_table::TYPE_INT, 1);
        $channel_table->column("port", \swoole_table::TYPE_INT, 1);
        $channel_table->column("type", \swoole_table::TYPE_STRING, 32);

        $channel_table->column("point_id", \swoole_table::TYPE_INT);
        $channel_table->column("point_name", \swoole_table::TYPE_STRING, 32);
        $channel_table->column("station_id", \swoole_table::TYPE_INT);
        $channel_table->column("station_name", \swoole_table::TYPE_STRING, 32);
        $channel_table->column("line_id", \swoole_table::TYPE_INT);

        //$channel_table->column("seq", \swoole_table::TYPE_INT);
        //$channel_table->column("pre_status", \swoole_table::TYPE_INT, 2);
        $channel_table->column("real_status", \swoole_table::TYPE_STRING, 16);
        
        //$channel_table->column("dt", \swoole_table::TYPE_INT, 8);
        //$channel_table->column("pcdt", \swoole_table::TYPE_INT, 8);

        $server->channel_table = $channel_table;
        $server->channel_table->create();
    }
}

?>