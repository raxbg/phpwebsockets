<?hh
define('DS', DIRECTORY_SEPARATOR);
define('DIR_WS_ROOT', dirname(__FILE__).DS);
define('DIR_WS_CORE', DIR_WS_ROOT . 'core' . DS);
define('DIR_WS_CORE_SERVER', DIR_WS_ROOT . 'core' . DS . 'server' . DS);
define('DIR_WS_COMPONENTS', DIR_WS_ROOT . 'components' . DS);
define('DIR_WS_LOG', DIR_WS_ROOT . 'logs');

$error_level = E_ALL;

include 'config.php';
include 'autoload.php';
error_reporting($error_level);
