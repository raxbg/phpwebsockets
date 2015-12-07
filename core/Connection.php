<?php
class ConnectionState {
    const OPENED = 1;
    const CLOSED = 0;
}

class Connection {
    private $wrapper;
    private $state = ConnectionState::OPENED;

    public static $ai_count = 0;
    public $id;
    public $ip = '';

    protected $sock;

    public function __construct($sock, $wrapper) {
        $this->sock = $sock;
        $this->id = ++self::$ai_count;//TODO: make sure this does not overlap with other connection ids
        $this->wrapper = $wrapper;

        if ($this->isValid()) {
            stream_set_blocking($sock, false);
            $this->ip = stream_socket_get_name($sock, true);
        }
    }

    public function enableSSL() {
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

    public function isValid() {
        return $this->sock !== false;
    }

    public function getResource() {
        return $this->sock;
    }

    public function send($data) {
        fwrite($this->sock, $data);
        //TODO: Split these into small chunks that can be sent fast
        //Maybe even implement a job queue, also make this function async
    }

    public function close() {
        fclose($this->sock);
        $this->state = ConnectionState::CLOSED;
    }

    public function listen() {
        if ($this->state !== ConnectionState::OPENED) return;

        if (feof($this->sock)) {
            $this->close();
            if ($this->wrapper !== null) {
                //$this->wrapper->onDisconnect($this);
            }
        }

        $read = array($this->sock);
        $write = $except = null;

        if (stream_select($read, $write, $except, 0, 10)) {
            $data = fread($this->sock, 1024);

            if (!empty($data) && $this->wrapper !== null) {
                $this->wrapper->onData($this, $data);
            }
        }
    }
}
