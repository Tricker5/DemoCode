<?php

namespace WSM;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Loggers{
    public static $loggers = [];

    static function loggerReg($logname){
        $stream = new StreamHandler(Config::LOG_DIR . "/{$logname}", Config::LOGGER_LEVEL);
        static::$loggers[$logname] = new Logger('main');
        static::$loggers[$logname]->pushHandler($stream); 
        return static::$loggers[$logname];
    }
}

?>