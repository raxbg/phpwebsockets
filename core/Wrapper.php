<?hh
abstract class Wrapper {
    protected Map $config;
    protected ?Server $server = null;

    public function __construct(Map $config) {
        $this->config = $config;
    }

    public function setServer(Server $server) {
        $this->server = $server;
    }

    protected function log(string $msg = '', bool $debug = false):void {
        if ($this->server !== null) {
            if ($debug) {
                $this->server->log->control($msg);
            } else {
                $this->server->log->error($msg);
            }
        }
    }

    protected function disconnect(Connection $con) {
        if ($this->server !== null) {
            $this->server->disconnect($con);
        }
    }

    abstract public function init();
    abstract public function onConnect(Connection $con);
    abstract public function onDisconnect(Connection $con);
    abstract public function onData(Connection $con, string $data);
    abstract public function onStop();
}
