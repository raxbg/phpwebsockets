<?hh
class Connection {
	public static int $ai_count = 0;
	public int $id;
	public int $frameDataLength = 0;
	public string $multiFrameBuffer = '';
	public $frameMask = array();
	public string $dataBuffer = '';
	public string $dataType = '';
	public int $lastFrameOpcode = 0;
	public bool $is_last_frame = true;

	protected $resource;

	public function __construct(&$res) {
		$this->resource = $res;
		$this->id = ++self::$ai_count;
	}

	public function &getResource() {
		return $this->resource;
	}

	public function recvFrameDataLength(): int {
		return strlen($this->dataBuffer);
	}

	public function isFrameComplete(): bool {
		//echo "frameDataLength: ".$this->frameDataLength."\n";
		//echo "recvFrameDataLength(): ".$this->recvFrameDataLength()."\n";
		return $this->frameDataLength == $this->recvFrameDataLength();
	}
	
	public function wasLastFrameFinal(): bool {
	    return $this->is_last_frame;
	}
}
