<?hh
class ServerManager {
    private Map<int, Server> $servers;

    public function __construct() {
        $this->servers = new Map(null);
    }

    public function startServer(int $port, string $wrapper, Map $wrapper_config, Map $ssl = Map) {
        if (!$this->servers->contains($port)) {
            $this->servers[$port] = new Server('0.0.0.0', $port, $ssl);
            $this->servers[$port]->loadWrapper($wrapper, $wrapper_config)->start();
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

            $awaitables = Vector {};
            foreach ($this->servers as $server) {
                if ($server->isRunning()) {
                    $awaitables->add($server->loop()->getWaitHandle());
                }
            }
            AwaitAllWaitHandle::fromVector($awaitables)->getWaitHandle()->join();
            //SleepWaitHandle::create(20000)->join();
            usleep(20000);
        }
    }

    private function parseCmd($cmd) {
        if ($cmd == 'quit') {
            foreach ($this->servers as $server) {
                $server->stop();
            }
            exit;
        }

        foreach ($this->servers as $server) {
            switch($cmd) {
            case 'uptime':
                $server->printUptime();
                break;
            case 'status':
                $server->printStatus();
                break;
            case 'stop':
                $server->stop();
                break;
            case 'start':
                $server->start();
                break;
            }
        }
    }
}
