<?php

namespace WSM;

class Db{

    public $Db;
    public $molinesql;

    public function __construct(){
        
        $this->getNewDb();
        
        $this->molinesql = 
            "SELECT TOP 50
            ci.slot,ci.port as cport,ci.type,di.sn,mrs.raw_status as status,p.id,p.name as p5name          
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
            FROM dbo.fn_GetPlace(?) 
            WHERE level=5)";
        
    }

    function getNewDb(){
        try{
            $this->Db = new \PDO(
                "sqlsrv: Server = ".Config::DBHOST.
                "; Database = ".Config::DBNAME,
                Config::DBUNAME,
                Config::DBUPWD
            );
            $this->Db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }catch(\PDOException $e){
            var_dump($e->errorInfo);
            return MsgLabel::DB_CONN_ERROR;
            //exit;
        }

    }

    public function molinearr($lineid){    
        try{
            $molinepre = $this->Db->prepare($this->molinesql);
            if($molinepre->bindValue(1, $lineid))
                echo "goodbind; ";
            if($molinepre->execute())
                echo "goodexe; ";
            $molinearr = $molinepre->fetchall(); 
            if($molinearr)
                echo "goodfet; ";
        }catch(\PDOException $e){
            var_dump($e->errorInfo);
            $this->getNewDb();
            //exit;
        }
              
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

        try{

            $testpre = $this->Db->prepare("SELECT TOP 50
            ci.slot,ci.port as cport,ci.type,di.sn,mrs.raw_status as status,p.id,p.name as p5name          
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
           //,array(\PDO::ERRMODE_EXCEPTION)
            );
            $testpre->execute();


           $testarr = $testpre->fetch(\PDO::FETCH_ASSOC);
            return $testarr;

        }catch(\PDOException $e){
            var_dump($e->errorInfo);
            $this->getNewDb();
            //exit;
        }
        //$this->molinepre->execute(array(4));        
        //$testArr = $this->molinepre->fetchall(\PDO::FETCH_ASSOC);
        //return $testArr;
    }

    function testInsert($id){
        echo $id.": ";
        try{
        $insertpre = $this->Db->prepare(
            "INSERT INTO kaifaiot_dev_gj.dbo.testTable (id) VALUES (?) "
        );
        if($insertpre->bindValue(1, $id))
            echo "goodbind ";
        if($insertpre->execute())
            echo "goodexe ".PHP_EOL;
        }catch(\PDOException $e){
            var_dump($e->errorInfo);
            $this->getNewDb();
            //exit;
        }
    }


}


?>