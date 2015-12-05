<?hh
class SendFrame {
	public int $FIN = 0x80;
	public int $RSV1 = 0x0;
	public int $RSV2 = 0x0;
	public int $RSV3 = 0x0;
	public int $opcode = 0x1;
	public bool $mask = false;
	public int $payload_len = 0;
	public string $data = '';

	public function __construct(string $data = '') {
		$this->data = $data;
	}

	public function getFrame(): string {
		$response = $this->FIN | $this->RSV1 | $this->RSV2 | $this->RSV3;
		$response << 8;
		$response = $response | $this->opcode;

		$data_len = strlen($this->data);
		if ($data_len <= 125) {
			return chr($response).chr($data_len).$this->data;
		} else if ($data_len <= 65535) {
			return chr($response).chr(126).pack('n', $data_len).$this->data;
		} else if ($data_len > 65535) {
		    return chr($response).chr(127).pack('NN', $data_len).$this->data;
		}

        return '';
	}

	public function getPingFrame(): string {
		$this->opcode = 0x9;
		return $this->getFrame();
	}

	public function getPongFrame(): string {
		$this->opcode = 0xA;
		return $this->getFrame();
	}
}
