<?php
class WebChat extends Component implements iComponent {
	private $supportedProtocol = "webchat";

	public function selectProtocol(&$protocols) {
		foreach($protocols as $protocol) {
			if ($protocol == $this->supportedProtocol) return $protocol;
		}
		return false;
	}

	public function getProtocol() {
		return $this->supportedProtocol;
	}

	public function getClients() {
		return $this->clients;
	}

	public function onMessage(&$client, &$data) {
		$key = array_search($client, $this->clients);
		if ($key === false) {
			return false;
		}

		socket_getpeername($client, $data_ip);
		$this->log->control("Client $data_ip says: $data");
		if (!empty($data)) {
			$message = new SendFrame($data);
			$msgFrame = $message->getFrame();
			socket_write($client, $msgFrame);
			/*foreach ($this->clients as $client) {
				if ($client == $this->sock || $client == $read_client) continue;
				socket_getpeername($client, $send_to_ip);
				$this->log->control("Sending data to client $send_to_ip");
				socket_write($client, $msgFrame);
			}*/
		}
		return true;
	}
}
