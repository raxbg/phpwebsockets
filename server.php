<?hh
include 'init.php';

//preg_match('/inet\s+([0-9\.]+)\s/', `ifconfig wlan0`, $ipinfo);
//$ip = $ipinfo[1];

$server_manager = new ServerManager();

foreach ($hosts as $host => $config) {
    if ($config->contains('ports')) {
        foreach ($config->get('ports') as $port => $component) {
            $server_manager->startServer($host, $port, $component);
        }
    }
}
