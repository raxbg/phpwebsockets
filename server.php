<?php
include 'init.php';

//preg_match('/inet\s+([0-9\.]+)\s/', `ifconfig wlan0`, $ipinfo);
//$ip = $ipinfo[1];

$server_manager = new ServerManager();

foreach ($server_config as $port => $config) {
    $keys = array_keys($config);
    $wrapper = array_shift($keys);//$config->firstKey();
    $ssl = !empty($config['ssl']) ? $config['ssl'] : null;
    if ($ssl === null) {
        $ssl = array();
    }


    if ($wrapper !== null) {
        $wrapper_config = !empty($config[$wrapper]) ? $config[$wrapper] : null;

        if ($wrapper_config !== null) {
            $server_manager->startServer($port, $wrapper, $wrapper_config, $ssl);
        }
    }
}

$server_manager->run();
