<?hh
enum ServerState: int {
    STOPPED = 0;
    RUNNING = 1;
}

class Server {
    private $sock;
    private int $errorcode = 0;
    private string $errormsg = '';
    private int $backlog = 100;
    private Map<int, Connection> $connections = Map {};
    private int $startTime = 0;
    private ServerState $state = ServerState::STOPPED;
    private ?Wrapper $wrapper;

    public string $ip = '';
    public int $port = 0;
    public $log;

    public function __construct(string $ip = '0.0.0.0', int $port = 65000) {
        $this->log = new FileLog();
        $this->ip = $ip;
        $this->port = $port;
        $this->startTime = time();
    }

    public function loadWrapper(string $wrapper = 'RawTcp', Map $wrapper_config = Map {}): Server {
        $this->wrapper = new $wrapper($wrapper_config, $this);
        $this->wrapper->init();
        return $this;
    }

    public function isRunning() {
        return $this->state == ServerState::RUNNING;
    }

    public function getStartTime() {
        return $this->startTime;
    }

    public function start(): Server {
        $this->startTime = time();

        $context = stream_context_create(array(
            'socket' => array(
                'backlog' => $this->backlog
            )
        ));

        $this->sock = stream_socket_server('tcp://' . $this->ip . ':' . $this->port, $this->errorcode, $this->errormsg, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        if ($this->sock === false) {
            $this->saveSocketError();
            return $this;
        }

        $this->log->debug("Server is listening on $this->ip:$this->port");

        $this->state = ServerState::RUNNING;

        return $this;
    }

    public function loop(): void {
        if ($this->state !== ServerState::RUNNING) return;

        $read = array_merge(array($this->sock), $this->getConnectionsArray());

        $write = NULL;
        $except = NULL;
        if (stream_select($read, $write, $except, 0)) {

            if (in_array($this->sock, $read)) { //new client is connecting
                $new_client = stream_socket_accept($this->sock);
                if ($new_client) {
                    $client_ip = stream_socket_get_name($new_client, true);
                    $key = array_search($this->sock, $read);
                    unset($read[$key]);

                    //$this->log->debug(date('[Y-m-d H:i:s]') . " Client is connecting from $client_ip");

                    $c = new Connection($new_client, $client_ip);
                    $this->connections[$c->id] = $c;

                    if ($this->wrapper !== null) {
                        $this->wrapper->onConnect($c);
                    }
                }
            }

            foreach ($read as $read_resource) {
                $data = fread($read_resource, 1024);

                $con = $this->getConnectionByResource($read_resource);
                if ($con !== null) {
                    if (empty($data)) {
                        $this->disconnect($con);
                    } else {
                        if ($this->wrapper !== null) {
                            $this->wrapper->onData($con, $data);
                        }
                    }
                }
            }
        }
    }

    public function printUptime() {
        $uptime = time() - $this->startTime;
        $hours = ($uptime > 3600) ? (int)($uptime/3600) : 0;
        $uptime -= $hours * 3600;
        $minutes = ($uptime > 60) ? (int)($uptime/60) : 0;
        $uptime -= $minutes*60;
        $seconds = $uptime;
        $this->log->debug(sprintf("[%s:%d] Current uptime is %sh %sm %ss", $this->ip, $this->port, $hours, $minutes, $seconds));
    }

    public function printStatus() {
        $this->log->debug(sprintf("Currently active connections: %d", $this->connections->count()));
    }

    public function disconnect(Connection $con) {
        if ($con !== null){
            if ($this->wrapper !== null) {
                $this->wrapper->onDisconnect($con);
            }
            $con->close();
            unset($this->connections[$con->id]);
        }

        //$this->log->debug("Client has disconnected");
    }

    public function stop(): void {
        if (!$this->isRunning()) return;

        $this->log->debug("Closing connections...");

        if ($this->wrapper !== null) {
            $this->wrapper->onStop();
        }

        foreach($this->connections as $con) {
            $con->close();
        
        }
        fclose($this->sock);
        $this->state = ServerState::STOPPED;

        $this->log->debug("Server is stopped");
    }

    private function getConnectionsArray() {
        $result = array();
        foreach ($this->connections as $con) {
            $result[] = $con->getResource();
        }
        return $result;
    }

    private function getConnectionByResource($resource): ?Connection {
        foreach($this->connections as $con) {
            if ($con->getResource() == $resource) {
                return $con;
            }
        }
        return null;
    }

    private function saveSocketError() {
        $this->log->error(date('[j M Y : H:i:s]')." ($this->errorcode) $this->errormsg");
    }
}
