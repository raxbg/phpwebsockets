<?hh
class ServerManager {
    private Map<int, Server> $servers;

    public function __construct() {
        $this->servers = new Map(null);
    }

    public function startServer(string $host, int $port, string $component) {
        if (!$this->servers->contains($port)) {
            $this->servers->add(Pair($port, new Server('0.0.0.0', $port)));
        }

        $server = $this->servers->get($port);
        if ($server !== null) {
            if (!$server->isRunning()) {
                $server->start();
            }
            $server->addHost($host, $component);
        }
    }
}
