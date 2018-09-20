<?php

namespace WSM;

class Db{

    public $db;
    public $molinesql;
    public $mostationsql;

    public function __construct(){
        
        $this->getNewDb();
        
        $this->molinesql = //线体监控SQL语句
            "SELECT 
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
        $this->mostationsql = //工位点监控SQL语句
            "SELECT p.id as stationId, mrs.raw_status as status, m.id as mpoint_id, m.name, d.sn, c.slot, c.port, c.type
            from mpoint_realtime_status as mrs
            left join mpoint as m
            on mrs.id = m.id
            left join channels_info as c
            on m.ciid = c.id
            left join place as p
            on m.pid = p.id
            left join devices_info as d
            on c.device_id = d.id
            where p.id in 
            (select id from dbo.fn_GetPlace(?) where level = 5) ";
    }

    /**
     * 建立PDO连接
     */
    function getNewDb(){
        try{
            $this->db = new \PDO(
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
        }catch(\PDOException $e){
            var_dump($e->errorInfo);
            return MsgLabel::DB_CONN_ERROR;
            //exit;
        }

    }

    /**
     * 数据库实时查询函数
     * @param const $motype 监控类型
     * @param mixed $id 监控对象ID
     * @return array
     */
    public function moarr($motype, $id){
        $mosql = null;
        switch($motype){
            case MsgLabel::TASK_MOLINE:
                $mosql = $this->molinesql; 
                break;

            case MsgLabel::TASK_MOSTATION:
                $mosql = $this->mostationsql;
                break;

            default:
                break;
        }    
        try{
            $mopre = $this->db->prepare($mosql);
            $mopre->bindValue(1, $id);
                //echo "goodbind; ";
            $mopre->execute();
                //echo "goodexe; ";
            $moarr = $mopre->fetchall(\PDO::FETCH_ASSOC); 
            //if($moarr)
                //echo "goodfet; ";
        }catch(\PDOException $e){
            var_dump($e->errorInfo);
            $this->getNewDb();
            //exit;
        }

        for($i = 0; $i < sizeof($moarr); $i++){
            $moarr[$i]["type"] = Utils::typeConvert($moarr[$i]["type"]);
            $moarr[$i]["status"] = Utils::statusConvert($moarr[$i]["status"]);
        }

        return $moarr;
    }

    //测试专用
    function testFetch(){
        try{
            $testpre = $this->db->prepare($this->molinesql);
           //,array(\PDO::ERRMODE_EXCEPTION)
            $testpre->bindValue(1, 4);
            $testpre->execute();
            $testarr = $testpre->fetchall(\PDO::FETCH_ASSOC);
            return $testarr;
        }catch(\PDOException $e){
            var_dump($e->errorInfo);
            $this->getNewDb();
            //exit;
        }
    }

}


?>