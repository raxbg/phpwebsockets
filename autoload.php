<?php
function webchat_autoload($className) {
	$incl_dirs = array(
		'core' => DIR_WS_CORE,
		'components' => DIR_WS_COMPONENTS
	);
	foreach ($incl_dirs as $dir) {
		$filename = $dir . $className . ".php";
		if (file_exists($filename)) {
			require_once $filename;
			return;
		}
	}
}

spl_autoload_register('webchat_autoload', true, true);
