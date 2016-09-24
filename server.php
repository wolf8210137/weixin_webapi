<?php

require_once 'libs/function.php';

while (true) {

    $process_list = get_cache('process_list');

    if (!$process_list) {
        sleep(1);
        continue;
    }

    foreach ($process_list as $id) {
        exec('php wx_listener.php ' . $id . ' >/dev/null &');
    }

    del_cache('process_list');
}
