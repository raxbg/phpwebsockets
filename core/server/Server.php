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
    private string $ssl_file;
    private string $ssl_passphrase;

    public string $ip = '';
    public int $port = 0;
    public $log;

    public function __construct(string $ip = '0.0.0.0', int $port = 65000, Map $ssl = Map {}) {
        $this->log = new FileLog();
        $this->ip = $ip;
        $this->port = $port;
        $this->startTime = time();

        $file = $ssl->get('file');
        $pass = $ssl->get('passphrase');

        if (!empty($file) && $file !== null) {//this stupid check is because HHVM is a moron
            $this->ssl_file = $file;
        } else {
            $this->ssl_file = '';
        }

        if (!empty($pass) && $pass !== null) {//this stupid check is because HHVM is a moron
            $this->ssl_passphrase = $pass;
        } else {
            $this->ssl_passphrase = '';
        }
    }

    public function loadWrapper(string $wrapper = 'RawTcp', Map $wrapper_config = Map {}): Server {
        $this->wrapper = new $wrapper($wrapper_config, $this);
        $this->wrapper->init();
        return $this;
    }

    public function isSSL() {
        return !empty($this->ssl_file) && !empty($this->ssl_passphrase);
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

        if ($this->isSSL()) {
            stream_context_set_option($context, 'ssl', 'local_cert', $this->ssl_file);
            stream_context_set_option($context, 'ssl', 'passphrase', $this->ssl_passphrase);
            stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
            stream_context_set_option($context, 'ssl', 'verify_peer', false);
        }

        $this->sock = stream_socket_server('tcp://' . $this->ip . ':' . $this->port, $this->errorcode, $this->errormsg, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        if ($this->sock === false) {
            stream_set_blocking($this->sock, false);
            stream_socket_enable_crypto($this->sock, false);
            $this->saveSocketError();
            return $this;
        }

        $this->log->debug("Server is listening on $this->ip:$this->port");

        $this->state = ServerState::RUNNING;

        return $this;
    }

    public async function loop(): Awaitable<void> {
        if ($this->state !== ServerState::RUNNING) return;

        $con = new Connection(stream_socket_accept($this->sock, 0.01), $this->wrapper);
        while ($con->isValid()) {
            if ($this->wrapper !== null) {
                //$this->wrapper->onConnect($con);
            }

            if ($this->isSSL()) {
                if (!$con->enableSSL()) {
                    $this->log->error('Unable to create secure socket');
                    return;
                }
            }

            $this->connections[$con->id] = $con;//TODO: fix this fucking issue!!!
            $this->log->debug(date('[Y-m-d H:i:s]') . " Client connected from $con->ip");

            $con = new Connection(stream_socket_accept($this->sock, 0.01), $this->wrapper);
        }

        //$awaitables = Vector {};
        //foreach ($this->connections as $con) {
        //    $awaitables->add($con->listen()->getWaitHandle());
        //}
        //await AwaitAllWaitHandle::fromVector($awaitables);
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

    public function onDisconnect(Connection $con) {
        $this->connections->remove($con->id);
        $this->log->debug("Client has disconnected");
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

    private function saveSocketError() {
        $this->log->error(date('[j M Y : H:i:s]')." ($this->errorcode) $this->errormsg");
    }
}
