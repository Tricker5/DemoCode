<?php

namespace WSM;

class Db{

    public $Db;

    public function __construct(){
        try{
            $this->Db = new \PDO(
                "sqlsrv: Server = ".Config::DBHOST.
                "; Database = ".Config::DBNAME,
                Config::DBUNAME,
                Config::DBUPWD
            );
        }catch(\PDOException $e){
            var_dump($e->errorInfo);
            exit;
        }
    }

    public function molinearr($lineid){
        $molinesql = 
            "SELECT ci.slot,ci.port as cport,ci.type,di.sn,mrs.raw_status as status,p.id,p.name as p5name          
            FROM mpoint_realtime_status AS mrs
            LEFT JOIN mpoint AS m
            ON mrs.mpoint_id = m.id
            LEFT JOIN place AS p
            ON m.pid = p.id
            LEFT JOIN channels_info AS ci
            ON ci.id = m.ciid
            LEFT JOIN devices_info AS di
            ON ci.device_id = di.id                 
            WHERE p.id in(SELECT id
            FROM dbo.fn_GetPlace($lineid) 
            WHERE level=5)";
        $molinerst = $this->Db->query($molinesql);
        $molinearr = $molinerst->fetchall(); 
        $typeconvertarr = array(
            '8' => '高阻',
            '9' => '手环',
            '10' => '平衡电压',
            '11' => '温度',
            '12' => '低阻',
            '13' => '温度',
        );

        for($i = 0; $i < sizeof($molinearr); $i++){
            $typecode = $molinearr[$i]["type"];
            $typename = $typeconvertarr[$typecode] ?: '';//若为null设为空字符串
            $molinearr[$i]["type"] = $typename;
        }

        return $molinearr;
    }

    //测试专用
    function testFetch(){
        $testRes=$this->Db->query(
            "SELECT ci.slot,ci.port as cport,ci.type,di.sn,mrs.raw_status as status,p.id,p.name as p5name          
            FROM mpoint_realtime_status AS mrs
            LEFT JOIN mpoint AS m
            ON mrs.mpoint_id = m.id
            LEFT JOIN place AS p
            ON m.pid = p.id
            LEFT JOIN channels_info AS ci
            ON ci.id = m.ciid
            LEFT JOIN devices_info AS di
            ON ci.device_id = di.id                 
            WHERE p.id in(SELECT id
            FROM dbo.fn_GetPlace(4) 
            WHERE level=5)"
        );
        $testArr = $testRes->fetchall(\PDO::FETCH_ASSOC);
        return $testArr;
    }
}


?>