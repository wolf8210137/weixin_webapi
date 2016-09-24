<?php

require 'libs/function.php';

$id = $_GET['id'];

// 检查进程是否生成
$uuid = get_cache($id);

// 添加到生成进程的队列中
$process_list = get_cache('process_list');

if ($process_list == false) {
    $process_list = array();
} else {

    // 没有生成进程
    if (!$uuid) {
        $process_list[] = $id;

        set_cache('process_list', array_unique($process_list));
    }
}

// 已生成二维码
if ($uuid) {
    $data = array(
        'img' => '/weixin_webapi/saved/'.$id.'.png',
        'status' => 1
    );
} else {
    $data = array(
        'status' => 0
    );
}

echo json_encode($data);