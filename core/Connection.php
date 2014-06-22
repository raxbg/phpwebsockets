<?php
class Connection {
	public static $ai_count = 0;
	public $id;
	public $frameDataLength = 0;
	public $multiFrameBuffer = '';
	public $frameMask = array();
	public $dataBuffer = '';
	public $dataType = '';
	public $lastFrameOpcode = 0;
	public $is_last_frame = true;

	protected $resource;

	public function __construct(&$res) {
		$this->resource = $res;
		$this->id = ++self::$ai_count;
	}

	public function &getResource() {
		return $this->resource;
	}

	public function recvFrameDataLength() {
		return strlen($this->dataBuffer);
	}

	public function isFrameComplete() {
		//echo "frameDataLength: ".$this->frameDataLength."\n";
		//echo "recvFrameDataLength(): ".$this->recvFrameDataLength()."\n";
		return $this->frameDataLength == $this->recvFrameDataLength();
	}
	
	public function wasLastFrameFinal() {
	    return $this->is_last_frame;
	}
}
