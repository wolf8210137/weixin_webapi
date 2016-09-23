#!/usr/bin/env bash

dir=$(cd "$(dirname "$0")"; pwd)

while true
do
    filelist=`php diff.php`

    for file in $filelist
    do
        php wx_listener.php $file > $dir/log/$file.log 2>&1 &
        echo $file
    done

    sleep 2
done