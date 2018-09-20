<?php

namespace WSM;

use Monolog\Logger;

class Config{
    /**
     * 数据库参数配置
     */
    const DBHOST = "10.0.2.2, 1433";
    const DBNAME = "kaifaiot_dev_gj";
    const DBUNAME = "sa";
    const DBUPWD = "abc123456";

    /**
     * WebSocket参数配置置
     */
    const WSIP = "0.0.0.0";
    const WSPORT = 7777;

    /**
     * log配置
     */
    const DIR = '/home/tricker_5/code_log/WSM';
    const LOGGER_LEVEL = Logger::CRITICAL; 
}


?>