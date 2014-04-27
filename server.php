<?php
include 'init.php';

preg_match('/IPv4 Address[. :]+([0-9\.]+)\s/', `ipconfig`, $ipinfo);
$ip = $ipinfo[1];
$server = new Server($ip, 65000);
$server->loadComponent('WebChat');
$server->start();

