<?php

namespace WSM;

class MsgLabel{

    const DATA_REGION = "region data";
    const DATA_LINE = "line data";
    const DATA_STATION = "station data";
    const DATA_PLACE = "place data";
    const DATA_RSSI = "rssi data";

    const SET_MONITOR_TYPE = "type set";
    const SET_ID_LINE = "line id set";
    const SET_ID_REGION = "region id set";
    const SET_ID_STATION = "station id set";
    const SET_ID_PLACE = "place id set";
    const SET_ID_RSSI = "rssi line id set";
    
    const TASK_CLIENT_CLASSIFY = "client classify";
    const TASK_TABLE_UPDATE = "channel_table update";
    const TASK_PLACE_INIT = "place_table init";
    const TASK_PUSH = "push data";

    const FINISH_TABLE_UPDATE = "tables updating finished";
    const FINISH_CLIENT_CLASSIFY =  "client classsifying finished";

    const DB_CONN_ERROR = 90;
    const DB_CONN_SUCCESS = 91;
}


?>