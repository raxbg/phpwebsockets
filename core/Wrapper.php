<?hh
abstract class Wrapper {
    protected Map $config;
    protected Server $server;
    protected Log $log;

    public function __construct(Map $config, Server $server) {
        $this->config = $config;
        $this->server = $server;
        $this->log = $this->server->log;
    }

    abstract public function init();
    abstract public function onConnect(Connection $con);
    abstract public function onDisconnect(Connection $con);
    abstract public function onData(Connection $con, string $data);
    abstract public function onStop();
}
