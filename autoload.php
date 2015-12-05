<?hh
function system_autoload($className) {
    $incl_dirs = array(
        'core' => DIR_WS_CORE,
        'core_server' => DIR_WS_CORE_SERVER,
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

spl_autoload_register('system_autoload', true, true);
