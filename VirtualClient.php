<?php

class WebsocketClient{
    const IP = "0.0.0.0";
    const PORT = 7777;

    const NORMALSTR = 0;
    const MOTYPESET =1;
    const DBTEST = 2;
    const MOARR = 3;
    const MOLINESET = 4;
    const MOSTATIONSET = 5;
    
    public $client;
    public $hs_succ;
    //public $msg_label;

    public function __construct(){
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
            $motypeset = json_encode(array("head" => static::MOTYPESET, "body" => "line"));
            $moidset = json_encode(array("head" => static::MOLINESET, "body" => $this->randomLineid()));
        }else{
            $motypeset = json_encode(array("head" => static::MOTYPESET, "body" => "station"));
            $moidset = json_encode(array("head" => static::MOSTATIONSET, "body" => mt_rand(5,99)));
        }
        @$this->client->push($motypeset);
        @$this->client->push($moidset);     
    }

}

const CLIENT_NUM = 500;//支持0～1000个客户端左右

$clients = [];
$i = 0;
$msg_num = 0;
$good_client_num = 0;
$total_msg = 0;
$total_count_label = 0;

/**
 * 采用渐进式连接，避免首次多连接接入导致部分断线
 */
$tick_id = swoole_timer_tick(20, function() use(&$i, &$clients, &$tick_id){
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
        foreach($clients as $client){
            if($client->hs_succ)
                $good_client_num++;
    
            $msg_num += $client->client->i;//统计所有客户端收到的信息数
            $client->client->i = 0;
        }
        if($total_count_label ==1)
            $total_msg += $msg_num - CLIENT_NUM;
        if($good_client_num == CLIENT_NUM)
            $total_count_label = 1;//在客户端数达到设定数且配置稳定时计算总消息数
        echo "clients_num: ".$good_client_num.PHP_EOL;
        echo "msg_num: ".$msg_num.PHP_EOL;
        echo "total_msg: ".$total_msg.PHP_EOL;
});

?>