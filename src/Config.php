<?php

namespace WSM;

use Monolog\Logger;

class Config{
    const DBHOST = "10.0.2.2, 1433";
    const DBNAME = "kaifaiot_dev_gj";
    const DBUNAME = "sa";
    const DBUPWD = "abc123456";

    const WSIP = "0.0.0.0";
    const WSPORT = 7777;

    const DIR = '/home/tricker_5/code_log/WSM';
    const LOGGER_LEVEL = Logger::CRITICAL; 
}


?>