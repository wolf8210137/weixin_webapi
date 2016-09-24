<?php

function _echo($content, $status=0)
{
    echo '[*] '.$content;

    if ($status === true)
        echo ' 成功';

    if ($status === false) {
        echo ' 失败';
        exit();
    }



    echo " \n";
}

/**
 * 获取缓存
 * @param $key
 * @return mixed
 */
function get_cache($key)
{
    $mc = new Memcached();
    $mc->addServer("localhost", 11211);

    return $mc->get($key);
}


/**
 * 设置缓存
 * @param $key
 * @param $value
 * @param int $expiration
 * @return bool
 */
function set_cache($key, $value, $expiration=60)
{
    $mc = new Memcached();
    $mc->addServer("localhost", 11211);

    return $mc->set($key, $value, $expiration);
}

/**
 * 删除缓存
 * @param $key
 */
function del_cache($key)
{
    $m = new Memcached();
    $m->addServer('localhost', 11211);

    $m->delete($key);
}