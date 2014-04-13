<?php
class Connection {
	public static $ai_count = 0;
	public $id;

	protected $resource;

	public function __construct(&$res) {
		$this->resource = $res;
		$this->id = ++self::$ai_count;
	}

	public function getResource() {
		return $this->resource;
	}
}
