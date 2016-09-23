<?php

$id = $_GET['id'];

$data_file = 'data/'.$id;

if (!file_exists($data_file)) {
    touch($data_file);
}

if (file_exists('saved/'.$id.'.png')) {
    $data = array(
        'img' => '/weixin_webapi/saved/'.$id.'.png',
        // 0:有错, 1:新文件, 2:旧文件
        'status' => 1
    );
} else {
    $data = array(
        'status' => 0
    );
}

// 超时
if (time() - filemtime($data_file) > 30) {
    $data = array(
        'status' => 3
    );
}



echo json_encode($data);