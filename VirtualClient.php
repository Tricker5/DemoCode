<?php

class WebsocketClient{
    const IP = "0.0.0.0";
    const PORT = 7777;

    const DATA_REGION = "region data";
    const DATA_LINE = "line data";
    const DATA_STATION = "station data";

    const SET_MONITOR_TYPE = "type set";
    const SET_ID_LINE = "line id set";
    const SET_ID_REGION = "region id set";
    const SET_ID_STATION = "station id set";
    
    public $client;
    public $hs_succ;
    public $onmsg_time_1;
    public $onmsg_time_2;
    public $msg_time;
    //public $msg_label;

    public function __construct(){
        //$this->onmsg_time_1 = 0;
        $this->client = new \swoole_http_client(static::IP, static::PORT);
        $this->client->on("connect", [$this, "onConnect"]);
        $this->client->on("message", [$this, "onMessage"]);
        $this->hs_succ = $this->client->upgrade("/", [$this, "onHandshake"]);
        $this->client->on("close",  [$this, "onClose"]);
        $this->client->i = 0;
    }

    function onConnect($client){
        //echo "onConnect: ".microtime(true).PHP_EOL;
    }
    function onHandshake($client){
        @$this->setClient(mt_rand(1, 2));
    }

    function onMessage($client, $frame){
        $this->onmsg_time_2 =  microtime(true);
        $this->msg_time = $this->onmsg_time_2 - $this->onmsg_time_1;//记录收信时间间隔
        $this->onmsg_time_1 = $this->onmsg_time_2; 
        $this->client->i ++;
    }

    function onClose($client){
        //echo "Connection Closed!".PHP_EOL;
    }

    /**
     * 随机生成线体id
     */
    function randomLineid(){
        $lineid_arr = [4, 33, 62, 91, 120, 149, 178, 207, 236, 265];
        return $lineid_arr[mt_rand(0, 9)];
    }


    function setClient($type){
        if($type ==1){
            $motypeset = json_encode(array("head" => static::SET_MONITOR_TYPE, "body" => "line"));
            $moidset = json_encode(array("head" => static::SET_ID_LINE, "body" => $this->randomLineid()));
        }else{
            $motypeset = json_encode(array("head" => static::SET_MONITOR_TYPE, "body" => "station"));
            $moidset = json_encode(array("head" => static::SET_ID_STATION, "body" => mt_rand(5,99)));
        }
        @$this->client->push($motypeset);
        @$this->client->push($moidset);
        $this->onmsg_time_1 = microtime(true);//握手成功时初始化收信时间     
    }
    
}

const CLIENT_NUM = 3000;//支持0～1000个客户端左右

$clients = [];
$i = 0;
$msg_num = 0;
$good_client_num = 0;
$total_msg = 0;
$total_count_label = 0;

/**
 * 采用渐进式连接，避免首次多连接接入导致部分断线
 */
$tick_id = swoole_timer_tick(10, function() use(&$i, &$clients, &$tick_id){
    $clients[$i] = new WebsocketClient;
    $i++;
    if($i == CLIENT_NUM){
        swoole_timer_clear($tick_id);
    }      
});

/*
for($i = 0; $i != CLIENT_NUM; $i++){
    $clients[$i] = new WebsocketClient;
}
*/

swoole_timer_tick(1000, function() 
    use(&$clients, $msg_num, $good_client_num, &$total_msg, &$total_count_label){
        $msg_labels = [];
        $max_msg_time = 0;
        foreach($clients as $client){
            if($client->hs_succ)
                $good_client_num++;
            $msg_num += $client->client->i;//统计所有客户端收到的信息数
            $client->client->i = 0;

            if($client->msg_time > $max_msg_time)
                $max_msg_time = $client->msg_time;//获取客户端中最大的收信间隔
        }

        if($total_count_label ==1)
            $total_msg += $msg_num - CLIENT_NUM;
        if($good_client_num == CLIENT_NUM)
            $total_count_label = 1;//在客户端数达到设定数且配置稳定时计算总消息数
        echo "clients_num: ".$good_client_num.PHP_EOL;
        echo "msg_num: ".$msg_num.PHP_EOL;
        echo "total_msg: ".$total_msg.PHP_EOL;
        echo "max_msg_time: ".$max_msg_time.PHP_EOL;
    }
);

?>