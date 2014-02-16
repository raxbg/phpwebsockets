<?php
class SendFrame {
	public $FIN = false;
	public $RSV1 = false;
	public $RSV2 = false;
	public $RSV3 = false;
	public $opcode = 0;
	public $mask = false;
	public $payload_len = 0;
	public $data = '';

	public function __construct($data) {
		$this->data = $data;
	}

	public function getFrame() {
		$data_len = strlen($this->data);
		if ($data_len <= 125) {
			return chr(129).chr(strlen($this->data)).$this->data;
		} else if ($data_len <= 65535) {
			echo "Data length: $data_len\n";
			return chr(129).chr(126).pack(n, $data_len).$this->data;
		}
	}
}
