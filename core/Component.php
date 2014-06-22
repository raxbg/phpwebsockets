<?php
abstract class Component {
	protected $server;

	public function __construct($server) {
		$this->server = $server;
	}
	
	abstract public function onMessage($client, $data);
}
