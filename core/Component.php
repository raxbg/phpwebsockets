<?php
class Component {
	public $log;

	private $clients = array();
	private $releaseClientCallback;

	public function addClient(&$client) {
		$this->clients[] = $client;
	}

	public function releaseClient(&$client) {
		$key = array_search($client, $this->clients);
		if ($key !== false) {
			socket_close($client);
			call_user_func($this->releaseClientCallback, $client);
			unset($this->clients[$key]);
			return true;
		}
		return false;
	}

	public function releaseClients() {
		foreach ($this->clients as $client) {
			socket_close($client);
		}
		unset($this->clients);
	}

	public function setReleaseClientCallback($callback) {
		$this->releaseClientCallback = $callback;
	}
}
