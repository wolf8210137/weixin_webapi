<?php

function _echo($content, $status=0)
{
    echo '[*] '.$content;

    if ($status === true)
        echo ' 成功';

    if ($status === false)
        echo ' 失败';



    echo " \n";
}