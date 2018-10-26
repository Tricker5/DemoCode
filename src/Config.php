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
     * WebSocket参数配置
     */
    const WSIP = "0.0.0.0";
    const WSPORT = 7777;
    const WORKER_NUM = 2;
    const TASK_WORKER_NUM = 4;

    /**
     * log配置
     */
    const LOG_DIR = __DIR__ . '/..' . '/log';
    const LOGGER_LEVEL = Logger::CRITICAL; 

    /**
     * Table配置
     */
    const DEVICE_NUM = 8192;
    const CLIENT_NUM = 5000;
}


?>