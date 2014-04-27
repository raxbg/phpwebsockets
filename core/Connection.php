<?php
class Connection {
	public static $ai_count = 0;
	public $id;
	public $frameDataLength = 0;
	public $frameMask = array();
	public $dataBuffer = '';

	protected $resource;

	public function __construct(&$res) {
		$this->resource = $res;
		$this->id = ++self::$ai_count;
	}

	public function getResource() {
		return $this->resource;
	}

	public function recvFrameDataLength() {
		return strlen($this->dataBuffer);
	}

	public function isFrameComplete() {
		echo "frameDataLength: ".$this->frameDataLength."\n";
		echo "recvFrameDataLength(): ".$this->recvFrameDataLength()."\n";
		return $this->frameDataLength == $this->recvFrameDataLength();
	}
}
