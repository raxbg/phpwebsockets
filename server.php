<?php
include 'init.php';

preg_match('/inet\s+([0-9\.]+)\s/', `ifconfig wlan0`, $ipinfo);
$ip = $ipinfo[1];
$server = new Server($ip, 65000);
$server->loadComponent('WebChat');
$server->start();

