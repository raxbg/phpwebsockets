<?hh
class ServerManager {
    private Map<int, Server> $servers;

    public function __construct() {
        $this->servers = new Map(null);
    }

    public function startServer(string $host, int $port, string $component) {
        if (!$this->servers->contains($port)) {
            $this->servers[$port] = new Server('0.0.0.0', $port);
        }

        $server = $this->servers->get($port);
        if ($server !== null) {
            if (!$server->isRunning()) {
                $server->start();
            }
            $server->addHost($host, $component);
        }
    }

    public function run() {
        stream_set_blocking(STDIN, 0);

        for(;;) {
            if (strpos('WIN', PHP_OS) === false){
                $line = trim(fgets(STDIN));
                if (!empty($line)) {
                    $this->parseCmd($line);
                }
            }

            foreach ($this->servers as $server) {
                $server->loop();
            }

            usleep(20000);
        }
    }

    private function parseCmd($cmd) {
        foreach ($this->servers as $server) {
            switch($cmd) {
            case 'uptime':
                $server->printUptime();
                break;
            case 'stop':
                $server->stop();
                exit;
                break;
            default:
                //foreach ($this->hosts as $component) {
                //    if (method_exists($component, 'parseCmd')) {
                //        $component->parseCmd($cmd);
                //    }
                //}
            }
        }
    }
}
