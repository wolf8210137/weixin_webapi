<?php


$data_list = array();

if ($dh = opendir('data')){
    while (($file = readdir($dh)) !== false){

        if ($file == '.' || $file == '..') {
            continue;
        }

        $data_list[] = $file;

        if (time() - filemtime('data/'.$file) > 60) {
            unlink('data/'.$file);
            //file_exists('log/'.$file.'.log') && unlink('log/'.$file.'.log');
        }
    }
    closedir($dh);
}


$qrcode_list = array();
if ($dh = opendir('saved')){
    while (($file = readdir($dh)) !== false){
        if ($file == '.' || $file == '..') {
            continue;
        }

        if (time() - filemtime('saved/'.$file) > 60) {
            unlink('saved/'.$file);
        }

        $qrcode_list[] = str_replace('.png', '', $file);
    }
    closedir($dh);
}

$diff = array_diff($data_list, $qrcode_list);

foreach ($diff as $file) {
    echo $file."\n";
}
