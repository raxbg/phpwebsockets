<?hh
enum ServerState: int {
    STOPPED = 0;
    RUNNING = 1;
}

class Server {
    private $sock;
    private int $errorcode = 0;
    private string $errormsg = '';
    private int $backlog = 10;
    private Map<int, Connection> $connections = Map {};
    private int $startTime = 0;
    private bool $shouldStopServer = false;
    private ServerState $state = ServerState::STOPPED;
    private Wrapper $wrapper;

    public string $ip = '';
    public int $port = 0;
    public $log;

    public function __construct(string $ip = '0.0.0.0', int $port = 65000, string $wrapper = 'Raw', Map $wrapper_config = Map {}) {
        $this->log = new FileLog(); // TODO move to config
        $this->ip = $ip;
        $this->port = $port;
        $this->startTime = time();
        $this->wrapper = new $wrapper($wrapper_config);
    }

    public function isRunning() {
        return $this->state == ServerState::RUNNING;
    }

    public function getStartTime() {
        return $this->startTime;
    }

    public function start() {
        $this->wrapper->setServer($this);
        $this->wrapper->init();

        $this->shouldStopServer = false;
        $this->startTime = time();

        $context = stream_context_create(array(
            'socket' => array(
                'backlog' => $this->backlog
            )
        ));

        $this->sock = stream_socket_server('tcp://' . $this->ip . ':' . $this->port, $this->errorcode, $this->errormsg, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        if ($this->sock === false) {
            $this->saveSocketError();
            return false;
        }

        $this->log->control("Server is listening on $this->ip:$this->port");

        $this->state = ServerState::RUNNING;
    }

    public function loop() {
        if ($this->shouldStopServer) return;

        $read = array_merge(array($this->sock), $this->getConnectionsArray());

        $write = NULL;
        $except = NULL;
        if (stream_select($read, $write, $except, 0)) {

            if (in_array($this->sock, $read)) { //new client is connecting
                $new_client = stream_socket_accept($this->sock);
                $client_ip = stream_socket_get_name($new_client, true);
                $key = array_search($this->sock, $read);
                unset($read[$key]);

                $this->log->control(date('[Y-m-d H:i:s]') . " Client is connecting from $client_ip");

                $c = new Connection($new_client, $client_ip);
                $this->connections[$c->id] = $c;
                $this->wrapper->onConnect($c);
            }

            foreach ($read as $read_resource) {
                $data = fread($read_resource, 1024);

                $con = $this->getConnectionByResource($read_resource);
                if ($con !== null) {
                    if (empty($data)) {
                        $this->disconnect($con);
                    } else {
                        $this->wrapper->onData($con, $data);
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
        $this->log->control(sprintf("[%s:%d] Current uptime is %sh %sm %ss", $this->ip, $this->port, $hours, $minutes, $seconds));
    }

    public function disconnect(Connection $con) {
        if ($con !== null){
            $this->wrapper->onDisconnect($con);
            fclose($con->getResource());
            unset($this->connections[$con->id]);
        }

        $this->log->control("Client has disconnected");
    }

    public function stop() {
        $this->log->control("Closing connections...");
        $this->wrapper->onStop();
        $this->shouldStopServer = true;
        foreach($this->connections as $con) {
            $con->close();
        }
        fclose($this->sock);
        $this->log->control("Server is stopped");
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
