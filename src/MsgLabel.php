<?php

namespace WSM;

class MsgLabel{

    const DATA_REGION = "region data";
    const DATA_LINE = "line data";
    const DATA_STATION = "station data";

    const SET_MONITOR_TYPE = "type set";
    const SET_ID_LINE = "line id set";
    const SET_ID_REGION = "region id set";
    const SET_ID_STATION = "station id set";
    
    const TASK_TABLE_UPDATE = "channel_table update";
    const TASK_PLACE_INIT = "place_table init";
    const TASK_PUSH = "push data";

    const FINISH_TABLE_UPDATE = "tables updating finished";

    const DB_CONN_ERROR = 90;
    const DB_CONN_SUCCESS = 91;
}


?>