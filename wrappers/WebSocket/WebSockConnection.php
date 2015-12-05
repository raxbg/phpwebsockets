<?hh
class WebSockConnection {
    private Connection $con;
    private bool $is_authorized = false;

    public int $frameDataLength = 0;
    public string $multiFrameBuffer = '';
    public $frameMask = array();
    public string $dataBuffer = '';
    public string $dataType = '';
    public int $lastFrameOpcode = 0;
    public bool $is_last_frame = true;
    public string $protocol = '';

    public function __construct(Connection $con) {
        $this->con = $con;
    }

    public function sendRaw(string $data) {
        $this->con->send($data);
    }

    public function send(string $data, bool $send_as_binary): void {
        if (!empty($data)) {
            $message = new SendFrame($data);
            if ($send_as_binary) {
                $message->opcode = 0x2;
            }
            $msgFrame = $message->getFrame();
            $this->con->send($msgFrame);
        }
    }

    public function getConnection(): Connection {
        return $this->con;
    }

    public function isAuthorized(): bool {
        return $this->is_authorized;
    }

    public function setAuthorized(bool $state) {
        $this->is_authorized = $state;
    }

    public function recvFrameDataLength(): int {
        return strlen($this->dataBuffer);
    }

    public function isFrameComplete(): bool {
        return $this->frameDataLength == $this->recvFrameDataLength();
    }

    public function wasLastFrameFinal(): bool {
        return $this->is_last_frame;
    }
}
