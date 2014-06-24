<?php
class WebChat extends Component {
	const PROTOCOL = "webchat";

	private $clients = array();

	public function onConnect($client_id) {
		$this->clients[] = $client_id;
	}

	public function onMessage($client_id, $data, $dataType = 'text') {
		file_put_contents(DIR_WS_LOG.'/'.md5($data).'.dat', $data);
		$key = array_search($client_id, $this->clients);
		if ($key === false) {
			return false;
		}

		//$this->server->log->control("Client $client_id says: $data");
		foreach ($this->clients as $clientId) {
			if ($client_id == $clientId) continue;
			$this->server->send($clientId, $data, $dataType);
		}
		return true;
	}
}
