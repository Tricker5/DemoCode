<?php

namespace WSM;

class Db{

    public static $db;
    
    public static $place_table_sql = 
        "SELECT id, pid, name, level from place where del = 0";
    public static $device_table_sql =
        "SELECT rssi, dr.updatetime as rssi_update_time,
            ip, di.id as station_id, sn,
            p.name as station_name, p.pid as line_id
            from device_rssi as dr
            left join devices_info as di
            on dr.device_id = di.id
            left join place as p
            on p.id = di.id";
    public static $channel_table_sql = 
        "SELECT di.sn as sn, 
            ci.slot as slot, ci.port as port, ci.type as type, 
            m.id as point_id, m.name as point_name,
            mrs.real_status as real_status, 
            p.id as station_id, p.name as station_name, p.pid as line_id 
            from mpoint as m
            left join mpoint_realtime_status as mrs
            on m.id = mrs.mpoint_id
            left join channels_info as ci
            on m.ciid = ci.id
            left join devices_info as di
            on ci.device_id = di.id
            left join place as p
            on p.id = m.pid
            where m.endtime = 0";
    public static $place_table_pre;
    public static $device_table_pre;
    public static $channel_table_pre;

    /**
     * 建立PDO连接
     */
    static function getNewDb(){
        try{
            static::$db = new \PDO(
                "sqlsrv: Server = ".Config::DBHOST.
                "; Database = ".Config::DBNAME.
                "; LoginTimeout = 1",
                Config::DBUNAME,
                Config::DBUPWD,
                array(
                    //\PDO::ATTR_TIMEOUT => 5,
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                )
            );
            static::$place_table_pre = static::$db->prepare(static::$place_table_sql);
            static::$device_table_pre = static::$db->prepare(static::$device_table_sql);
            static::$channel_table_pre = static::$db->prepare(static::$channel_table_sql);
            
            //return MsgLabel::DB_CONN_SUCCESS;
        }catch(\PDOException $e){
            Loggers::$loggers["task_worker"]->critical($e->getMessage());
            //var_dump($e->errorInfo);
            //return MsgLabel::DB_CONN_ERROR;
        }
    }

    static function getPlaceTable(){
        try{
            static::$place_table_pre->execute();
        }catch(\PDOException $e){
            Loggers::$loggers["task_worker"]->critical($e->getMessage());
            Db::getNewDb();
        }
    }

    static function getDeviceTable(){
        try{
            static::$device_table_pre->execute();
        }catch(\PDOException $e){
            Loggers::$loggers["task_worker"]->critical($e->getMessage());
            Db::getNewDb();
        }
    }

    static function getChannelTable(){
        try{
            static::$channel_table_pre->execute();
        }catch(\PDOException $e){
            Loggers::$loggers["task_worker"]->critical($e->getMessage());
            Db::getNewDb();
        }
    }
}


?>