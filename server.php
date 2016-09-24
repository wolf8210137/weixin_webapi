<?php

require_once 'libs/function.php';

while (true) {

    $process_list = get_cache('process_list');

    if (!$process_list) {
        sleep(1);
        continue;
    }

    foreach ($process_list as $k => $id) {
        exec('php wx_listener.php ' . $id . ' > log/'.$id.' &');
        _echo($k);
        _echo('启动进程, 用户ID: '.$id);
        $id_info = array('status'=>2);
        set_cache($id, $id_info);
    }

    del_cache('process_list');
}
