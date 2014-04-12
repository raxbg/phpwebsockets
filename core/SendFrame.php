<?php
class SendFrame {
	public $FIN = 0x80;
	public $RSV1 = 0x0;
	public $RSV2 = 0x0;
	public $RSV3 = 0x0;
	public $opcode = 0x1;
	public $mask = false;
	public $payload_len = 0;
	public $data = '';

	public function __construct($data) {
		$this->data = $data;
	}

	public function getFrame() {
		$response = $this->FIN | $this->RSV1 | $this->RSV2 | $this->RSV3 | $this->opcode;

		$data_len = strlen($this->data);
		if ($data_len <= 125) {
			return chr($response).chr($data_len).$this->data;
		} else if ($data_len <= 65535) {
			echo "Data length: $data_len\n";
			return chr(129).chr(126).pack('n', $data_len).$this->data;
		}
	}

	public function getPingFrame() {
		$this->opcode = 0x9;
		return $this->getFrame();
	}

	public function getPongFrame() {
		$this->opcode = 0xA;
		return $this->getFrame();
	}
}
