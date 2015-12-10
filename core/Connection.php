<?hh
enum ConnectionState: int {
    OPENED = 1;
    CLOSED = 0;
}

class Connection {
    private ?Wrapper $wrapper;
    private ConnectionState $state = ConnectionState::OPENED;

    public static int $ai_count = 0;
    public int $id;
    public string $ip = '';

    protected $sock;

    public function __construct(&$sock, ?Wrapper $wrapper) {
        $this->sock = $sock;
        $this->id = ++self::$ai_count;//TODO: make sure this does not overlap with other connection ids
        $this->wrapper = $wrapper;

        if ($this->isValid()) {
            stream_set_blocking($this->sock, 0);
            $this->ip = stream_socket_get_name($this->sock, true);
        }
    }

    public function enableSSL(): bool {
        if (!@stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_SSLv3_SERVER)) {
            if (!@stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_SSLv23_SERVER)) {
                if (!@stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_SSLv2_SERVER)) {
                    $this->close();
                    return false;
                }
            }
        }
        return true;
    }

    public function isValid(): bool {
        return is_resource($this->sock);
    }

    public function getResource() {
        return $this->sock;
    }

    public function send(string $data) {
        fwrite($this->sock, $data);
        //TODO: Split these into small chunks that can be sent fast
        //Maybe even implement a job queue, also make this function async
    }

    public function close() {
        if (is_resource($this->sock)) {
            stream_socket_shutdown($this->sock, STREAM_SHUT_RDWR);
            stream_set_blocking($this->sock, false);
            fclose($this->sock);
        }

        $this->state = ConnectionState::CLOSED;
        if ($this->wrapper !== null) {
            $this->wrapper->onDisconnect($this);
        }
    }

    public async function listen(): Awaitable<void> {
        if ($this->state !== ConnectionState::OPENED) return;

        if (feof($this->sock)) {
            $this->close();
            return;
        }

        $code = await stream_await($this->sock, STREAM_AWAIT_READ, 0.010);

        switch ($code) {
            case STREAM_AWAIT_CLOSED:
                $this->close();
                return;
            case STREAM_AWAIT_READY:
                $data = fread($this->sock, 4096);

                if ($this->wrapper !== null) {
                    $this->wrapper->onData($this, $data);
                }
                break;
            case STREAM_AWAIT_TIMEOUT:
                break;
            case STREAM_AWAIT_ERROR:
                //idk, do something, or dont
                break;
        }
    }
}
