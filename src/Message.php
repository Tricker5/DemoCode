<?php

namespace WSM;

class Message{

    function onMessage($server, $frame){
        $fd = $frame->fd;
        $data = json_decode($frame->data, true);
        $readyarr = null;
        $logger = Loggers::$loggers['worker'];

        $logger->debug('onMessage: ', array(
            'fd' => $fd,
            'data' => $data
        ));

        //判断message数据头部分head类型并处理
        switch ($data["head"]){
            case MsgLabel::NORMALSTR:
                if($data["body"]){
                    echo "收到来自 $fd 号客户端的信息：".$data["body"].PHP_EOL;
                    $readyarr = Utils::readyArr(MsgLabel::NORMALSTR,  "已收到 ：".$data["body"]);
                }else{
                    echo "收到来自 $fd 号客户端的空白信息".PHP_EOL;
                    $readyarr = Utils::readyArr(MsgLabel::NORMALSTR, "请输入有效内容！");
                }                
                break;
            
            case MsgLabel::DBTEST:
                echo "收到 $fd 号客户端的测试请求...".PHP_EOL;
                $taskarr = Utils::readyArr(MsgLabel::DBTEST,array("fd" => $fd));
                $server->task($taskarr);
                break;

            case MsgLabel::MOTYPESET:
                echo "配置客户端监控类型...".PHP_EOL;
                ClientMgr::setMoType($fd, $data["body"]);
                echo "已将 $fd 号客户端监控类型配置为：".ClientMgr::$clients[$fd]->getMoType().PHP_EOL;
                break;

            case MsgLabel::MOLINESET:
                echo "配置客户端监控线体...".PHP_EOL;
                ClientMgr::setMoLine($fd, $data["body"]);
                echo "已将 $fd 号客户端监控线体ID配置为：".ClientMgr::$clients[$fd]->getMoLine().PHP_EOL;
                break;
            
            case MsgLabel::MOSTATIONSET:
                echo "配置客户端监控工位...".PHP_EOL;
                ClientMgr::setMoStation($fd, $data["body"]);
                echo "已将 $fd 号客户端监控工位ID配置为：".ClientMgr::$clients[$fd]->getMoStation().PHP_EOL;
                break;

            default:
                echo "未能识别来自 $fd 号客户端的信息：".PHP_EOL;
                $readyarr = Utils::readyArr(MsgLabel::NORMALSTR, "信息无法识别！");
                break;
        }
        if($readyarr){
            if(!$server->push($fd, json_encode($readyarr)))
                echo "数据包发送失败！".PHP_EOL;
            
        }
    }
}

?>