<?hh
include 'init.php';

//preg_match('/inet\s+([0-9\.]+)\s/', `ifconfig wlan0`, $ipinfo);
//$ip = $ipinfo[1];
$server = new Server();
$server->loadComponent('WebChat');
$server->loadComponent('EchoComponent');
$server->start();

