<?php
class Server {
	public $ip = '';
	public $port = 0;
	private $sock;
	private $errorcode;
	private $errormsg;
	private $backlog = 10;
	private $clients = array();
	private $unauth_clients = array();
	private $log;
	private $buffer = array();
	private $ws_guid = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

	public function __construct($ip = '127.0.0.1', $port = '65000') {
		$this->log = new FileLog();
		$this->ip = $ip;
		$this->port = $port;
	}

	public function start() {
		$this->sock = socket_create(AF_INET, SOCK_STREAM, 0);
		if (!$this->sock) {
			$this->saveSocketError();
			return false;
		}

		if (!socket_bind($this->sock, '192.168.1.10', $this->port)) {
			$this->saveSocketError();
			return false;
		}

		if (!socket_listen($this->sock, $this->backlog)) {
			$this->saveSocketError();
			return false;
		}

		$this->log->control("Server is listening on $this->ip:$this->port");

		for (;;) {
			$read = array_merge(array($this->sock), $this->clients, $this->unauth_clients);

			if (socket_select($read, $write = NULL, $except = NULL, 0)) {

				if (in_array($this->sock, $read)) { //new client is connecting
					$this->unauth_clients[] = $new_client = socket_accept($this->sock);
					socket_getpeername($new_client, $client_ip);
					$this->log->control("Client is connecting from $client_ip");
					$this->notifyClients("New client connected: $client_ip", array($this->sock, $new_client));
					$key = array_search($this->sock, $read);
					unset($read[$key]);
				}

				foreach ($read as $read_client) {
					$data = @socket_read($read_client, 1024, PHP_BINARY_READ);

					if (empty($data)) {
						$key = array_search($read_client, $this->clients);
						if ($key !== false) {
							unset($this->clients[$key]);
						} else {
							$key = array_search($read_client, $this->unauth_clients);
							unset($this->unauth_clients[$key]);
						}

						$this->log->control("Client has disconnected");
					} else {
						if (in_array($read_client, $this->unauth_clients)) {//check if this client is trying to authenticate
							$headers = $this->parse_headers($data);
							if ($this->validateWsHeaders($headers)) {
								$response = $this->buildHandshake($headers);
								socket_write($read_client, $response);
								$this->clients[] = $read_client;
							} else {
								$this->log->control("Header validation failed.");
								socket_close($read_client);
							}
							$key = array_search($read_client, $this->unauth_clients);
							unset($this->unauth_clients[$key]);
						} else {
							$this->log->control('Parsing frame...');
							$frame = new RecvFrame($data);
							//TODO: Implement ping pong
							if ($frame->opcode & 0x8) { //disconnect code
								$this->log->control("Client has disconnected");
								socket_close($read_client);
								$key = array_search($read_client, $this->clients);
								unset($this->clients[$key]);
							} else if (($frame->opcode & 0x1) && $frame->FIN) {
								socket_getpeername($read_client, $data_ip);
								$msg = $frame->getData();
								$this->log->control("Client $data_ip says: $msg");
								if (!empty($msg)) {
									$message = new SendFrame($msg);
									$msgFrame = $message->getFrame();
									foreach ($this->clients as $client) {
										if ($client == $this->sock || $client == $read_client) continue;
										socket_getpeername($client, $send_to_ip);
										$this->log->control("Sending data to client $send_to_ip");
										socket_write($client, $msgFrame);
									}
								}
							}
						}
					}
				}
			}
		}

		foreach ($this->clients as $client) {
			socket_close($client);
		}
		socket_close($this->sock);
	}

	public function getLastError() {
		return array($this->errorcode, $this->errormsg);
	}

	private function notifyClients($msg, $excluded = array()) {
		$message = new SendFrame(utf8_encode($msg));
		$msgFrame = $message->getFrame();
		foreach ($this->clients as $client) {
			if (in_array($client, $excluded)) continue;
			socket_getpeername($client, $send_to_ip);
			$this->log->control("Sending data to client $send_to_ip");
			socket_write($client, $msgFrame);
		}
	}

	private function saveSocketError() {
		$this->errorcode = socket_last_error();
		$this->errormsg = socket_strerror($this->errorcode);
		$this->log->error(date('[j M Y : H:i:s]')." ($this->errorcode) $this->errormsg");
	}

	private function validateWsHeaders(&$headers) {
		return (!empty($headers['Upgrade']) && !empty($headers['Connection']) && !empty($headers['Sec-WebSocket-Key']) && !empty($headers['Sec-WebSocket-Version']));
	}

	private function parse_headers($data) {
		$lines = explode("\r\n", $data);
		$headers = array();
		if (!empty($lines)) {
			foreach ($lines as $line) {
				if (strpos($line, ':') !== false) {
					$header = trim(substr($line, 0, strpos($line, ':')));
					$value = trim(substr($line, strpos($line, ':')+1));
					if (!empty($header) && !empty($value)) {
						$headers[$header] = $value;
					}
				}
			}
		}
		return $headers;
	}

	private function buildHandshake(&$headers) {
		$resp_headers = array();
		$resp_headers['Sec-WebSocket-Accept'] = base64_encode(sha1($headers['Sec-WebSocket-Key'].$this->ws_guid, true));
		$resp_headers['Upgrade'] = 'websocket';
		$resp_headers['Connection'] = 'Upgrade';
		if (!empty($headers['Sec-WebSocket-Protocol'])) {
			$protocols = explode(',', $headers['Sec-WebSocket-Protocol']);
			$resp_headers['Sec-WebSocket-Protocol'] = $protocols[0]; //just accept the first protocol for now
		}

		$resp = "HTTP/1.1 101 Switching Protocols\r\n";
		foreach ($resp_headers as $header=>$value) {
			$resp .= $header.": ".$value."\r\n";
		}
		return $resp."\r\n";
	}
}
