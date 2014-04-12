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
	private $extensions = array();

	public function __construct($ip = '127.0.0.1', $port = '65000') {
		$this->log = new FileLog();
		$this->ip = $ip;
		$this->port = $port;
	}

	public function releaseClientCallback($client) {
		$key = array_search($client, $this->clients);
		if ($key !== false) {
			unset($this->clients[$key]);
		}
	}

	public function loadComponent($ext) {
		$e = new $ext;
		if ($e instanceof iComponent) {
			$e->log = $this->log;
			$e->setReleaseClientCallback(array($this, "releaseClientCallback"));
			$this->extensions[$e->getProtocol()] = $e;
		} else {
			$this->log->control("Failed to load extension $ext. It does not implement the iComponent interface.");
		}
		unset($e);
	}

	public function start() {
		$this->sock = socket_create(AF_INET, SOCK_STREAM, 0);
		if (!$this->sock) {
			$this->saveSocketError();
			return false;
		}

		if (!socket_bind($this->sock, $this->ip, $this->port)) {
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
					$key = array_search($this->sock, $read);
					unset($read[$key]);
				}

				foreach ($read as $read_client) {
					$data = @socket_read($read_client, 1024, PHP_BINARY_READ);

					if (empty($data)) {
						$this->releaseClient($read_client);
					} else {
						if (in_array($read_client, $this->unauth_clients)) {
							$this->authClient($read_client, $data);
						} else {
							$this->processData($read_client, $data);
						}
					}
				}
			}
		}
		$this->stop();
	}

	private function authClient(&$client, &$data) {
			$headers = $this->parse_headers($data);
			if ($this->validateWsHeaders($headers)) {
				$protocol = $this->selectProtocol($headers);
				if ($protocol) {
					$response = $this->buildHandshake($headers, $protocol);
					socket_write($client, $response);
					$this->extensions[$protocol]->addClient($client);
					$this->clients[] = $client;
				} else {
					$this->log->control("Unsupported protocol. Disconnecting client...");
					socket_close($client);
				}
			} else {
				$this->log->control("Header validation failed.");
				socket_close($client);
			}
			$key = array_search($client, $this->unauth_clients);
			unset($this->unauth_clients[$key]);
	}

	private function releaseClient(&$client) {
			$key = array_search($client, $this->unauth_clients);
			if ($key !== false) {
				unset($this->unauth_clients[$key]);
			} else {
				foreach($this->extensions as &$ext) {
					if ($ext->releaseClient($client)) {
						break;
					}
				}
			}
			$this->log->control("Client has disconnected");
	}

	private function processData(&$client, &$data) {
		$frame = new RecvFrame($data);
		if ($frame->opcode & 0x8) { //disconnect code
			$this->log->control("Client wants to disconnect");
			$this->releaseClient($client);
		} else if (($frame->opcode & 0x1) && $frame->FIN) {
			$msg = $frame->getData();
			foreach($this->extensions as &$ext) {
				if ($ext->onMessage($client, $msg)) {
					break;
				}
			}
		}
	}

	public function stop() {
		$this->log("Closing connections...");
		foreach($this->extensions as &$ext) {
			$ext->releaseClients();
		}
		socket_close($this->sock);
		$this->log("Server is stopped");
	}

	public function getLastError() {
		return array($this->errorcode, $this->errormsg);
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

	private function selectProtocol(&$headers) {
		if (!empty($headers['Sec-WebSocket-Protocol'])) {
			$protocols = explode(',', $headers['Sec-WebSocket-Protocol']);
			foreach ($this->extensions as &$ext) {
				$protocol = $ext->selectProtocol($protocols);
				if ($protocol) {
					return $protocol;
				}
			}
		}
		return false;
	}

	private function buildHandshake(&$headers, $protocol = false) {
		$resp_headers = array();
		$resp_headers['Sec-WebSocket-Accept'] = base64_encode(sha1($headers['Sec-WebSocket-Key'].$this->ws_guid, true));
		$resp_headers['Upgrade'] = 'websocket';
		$resp_headers['Connection'] = 'Upgrade';
		if (!empty($headers['Sec-WebSocket-Protocol']) && $protocol) {
			$resp_headers['Sec-WebSocket-Protocol'] = $protocol;
		}

		$resp = "HTTP/1.1 101 Switching Protocols\r\n";
		foreach ($resp_headers as $header=>$value) {
			$resp .= $header.": ".$value."\r\n";
		}
		return $resp."\r\n";
	}
}
