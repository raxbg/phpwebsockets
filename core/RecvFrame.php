<?php
class RecvFrame {
	public $FIN = false;
	public $RSV1 = false;
	public $RSV2 = false;
	public $RSV3 = false;
	public $opcode = 0;
	public $mask = false;
	public $payload_len = 0;
	public $mask_bytes = array();
	public $data_buffer = array();
	public $dataFirstByteIndex = 0;
	private $parsed_data = '';

	public static function unmaskData($mask, $bytes) {
		$x = 0;
		$i = 0;
		$data_buffer = array();
		while(!empty($bytes[$i])) {
			$data_buffer[] = ($bytes[$i] ^ $mask[$x%4]);
			$i++;
			$x++;
		}
		return call_user_func_array("pack", array_merge(array('C*'), $data_buffer));
	}

	public function __construct($data) {
		$bytes = unpack('C*byte', $data);
		$this->FIN = $bytes['byte1'] & 0x80;
		$this->RSV1 = $bytes['byte1'] & 0x40;
		$this->RSV2 = $bytes['byte1'] & 0x20;
		$this->RSV3 = $bytes['byte1'] & 0x10;
		$this->opcode = $bytes['byte1'] & 0x0f;
		$this->mask = $bytes['byte2'] & 0x80;
		$this->payload_len = ($bytes['byte2'] & 0x7f);

		$i = 3;
		if ($this->mask) {
			switch ($this->payload_len) {
				case 126:
					$this->mask_bytes = array(
						$bytes['byte5'],
						$bytes['byte6'],
						$bytes['byte7'],
						$bytes['byte8']
					);
					$i = 9;
					break;
				case 127:
					$this->mask_bytes = array(
						$bytes['byte11'],
						$bytes['byte12'],
						$bytes['byte13'],
						$bytes['byte14']
					);
					$i = 15;
					break;
				default:
					$this->mask_bytes = array(
						$bytes['byte3'],
						$bytes['byte4'],
						$bytes['byte5'],
						$bytes['byte6']
					);
					$i = 7;
			}
		}
		$this->dataFirstByteIndex = $i;
		$x = 0;
		while(!empty($bytes['byte'.$i])) {
			if ($this->mask) {
				$this->data_buffer[] = ($bytes['byte'.$i] ^ $this->mask_bytes[$x%4]);
			} else {
				$this->data_buffer[] = $bytes['byte'.$i];
			}
			$i++;
			$x++;
		}
	}

	public function getData() {
		return call_user_func_array("pack", array_merge(array('C*'), $this->data_buffer));
	}
}
