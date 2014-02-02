<?php
function webchat_autoload($className) {
	include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . $className . ".php";
}

spl_autoload_register('webchat_autoload', true, true);
