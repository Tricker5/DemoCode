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

    public function __construct(){
        $this->client = new \swoole_http_client(static::IP, static::PORT);
        $this->client->on("message", [$this, "onMessage"]);
        $this->client->upgrade("/", [$this, "onHandshake"]);
        $this->client->on("close",  [$this, "onClose"]);
        $this->client->i = 0;
    }

    function onHandshake($client){
        $this->setClient();
    }

    function onMessage($client, $frame){
        $this->client->i++;
    }

    function onClose($client){
        //echo "Connection Closed!".PHP_EOL;
    }

    function setClient(){
        $motypeset = json_encode(array("head" => static::MOTYPESET, "body" => "line"));
        $mostationset = json_encode(array("head" => static::MOLINESET, "body" => "4"));
        $this->client->push($motypeset);
        $this->client->push($mostationset);
    }

}

const CLIENT_NUM = 150;

$clients = [];
for($i = 0; $i < CLIENT_NUM; $i++){
    $clients[$i] = new WebsocketClient();
}

$msg_num = 0;

swoole_timer_tick(1000, function() use($clients, $msg_num){
    foreach($clients as $client){
        $msg_num += $client->client->i;
    }
    echo $msg_num.PHP_EOL;
});

?>