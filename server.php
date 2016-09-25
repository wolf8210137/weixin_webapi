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
        _echo('启动进程, 用户ID: '.$id);

        $online_list = get_cache('online_list');
        $online_list[] = $id;
        set_cache('online_list', array_unique($online_list));

        _echo('当时在线进程数: '.count($online_list));

        $id_info = array('status'=>2);
        set_cache($id, $id_info);
    }

    del_cache('process_list');
}
