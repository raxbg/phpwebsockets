<?hh
include 'init.php';

//preg_match('/inet\s+([0-9\.]+)\s/', `ifconfig wlan0`, $ipinfo);
//$ip = $ipinfo[1];

$server_manager = new ServerManager();

foreach ($server_config as $port => $config) {
    $wrapper = $config->firstKey();
    $ssl = $config->get('ssl');
    if ($ssl === null) {
        $ssl = Map {};
    }


    if ($wrapper !== null) {
        $wrapper_config = $config->get($wrapper);

        if ($wrapper_config !== null) {
            $server_manager->startServer($port, $wrapper, $wrapper_config, $ssl);
        }
    }
}

$server_manager->run();
