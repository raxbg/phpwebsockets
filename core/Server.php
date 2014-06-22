<?php
class Server {
	public $ip = '';
	public $port = 0;
	public $log;
	private $sock;
	private $errorcode;
	private $errormsg;
	private $backlog = 10;
	private $connections = array();
	private $unauth_clients = array();
	private $ws_guid = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
	private $components = array();

	public function __construct($ip = '0.0.0.0', $port = '65000') {
		$this->log = new FileLog();
		$this->ip = $ip;
		$this->port = $port;
	}

	public function loadComponent($component) {
		$c = new $component($this);
		if ($c instanceof Component && !empty($c::PROTOCOL)) {
			if (method_exists($c, 'onLoad')) {
				$c->onLoad();
			}
			$this->components[$c::PROTOCOL] = $c;
		} else {
			$this->log->control("Failed to load component $component. It does not implement the iComponent interface.");
		}
		unset($c);
	}

	public function send($client_id, $data) {
		if (!empty($this->connections[$client_id])) {
			$conn = $this->connections[$client_id];
			if (!empty($data)) {
				$message = new SendFrame($data);
				$msgFrame = $message->getFrame();
				$this->sendFrame($conn->getResource(), $msgFrame);
				return true;
			}
			return false;
		}
		return false;
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

		foreach($this->components as $c) {
			if (method_exists($c, 'onStart')) {
				$c->onStart($this->ip, $this->port);
			}
		}

		for (;;) {
			$read = array_merge(array($this->sock), $this->getConnectionsArray(), $this->unauth_clients);

			$write = NULL;
			$except = NULL;
			if (socket_select($read, $write, $except, 0)) {

				if (in_array($this->sock, $read)) { //new client is connecting
					$this->unauth_clients[] = $new_client = socket_accept($this->sock);
					socket_getpeername($new_client, $client_ip);
					$this->log->control("Client is connecting from $client_ip");
					$key = array_search($this->sock, $read);
					unset($read[$key]);
				}

				foreach ($read as $read_resource) {
					$data = @socket_read($read_resource, 1024, PHP_BINARY_READ);

					if (empty($data)) {
						$this->releaseResource($read_resource);
					} else {
						if (in_array($read_resource, $this->unauth_clients)) {
							$this->authClient($read_resource, $data);
						} else {
							$this->processData($read_resource, $data);
						}
					}
				}
			}
		}
		$this->stop();
	}

	private function getConnectionsArray() {
		$result = array();
		foreach ($this->connections as $con) {
			$result[] = $con->getResource();
		}
		return $result;
	}

	private function &getConnectionByResource(&$resource) {
		$id = false;
		foreach($this->connections as $conn) {
			if ($conn->getResource() == $resource) {
				$id = $conn->id;
				break;
			}
		}
		if ($id && !empty($this->connections[$id])) return $this->connections[$id];
		return $id;
	}

	private function authClient(&$client_resource, &$data) {
			$headers = $this->parse_headers($data);
			if ($this->validateWsHeaders($headers)) {
				$protocol = $this->selectProtocol($headers);
				if ($protocol) {
					$response = $this->buildHandshake($headers, $protocol);
					socket_write($client_resource, $response);
					$conn = new Connection($client_resource);
					if(method_exists($this->components[$protocol], 'onConnect')) {
						$this->components[$protocol]->onConnect($conn->id);
					}
					$this->connections[$conn->id] = $conn;
				} else {
					$this->log->control("Unsupported protocol. Disconnecting client...");
					socket_close($client_resource);
				}
			} else {
				$this->log->control("Header validation failed.");
				socket_close($client_resource);
			}
			$key = array_search($client_resource, $this->unauth_clients);
			unset($this->unauth_clients[$key]);
	}

	private function releaseResource(&$res) {
		$key = array_search($res, $this->unauth_clients);
		if ($key !== false) {
			unset($this->unauth_clients[$key]);
		} else {
			$conn = $this->getConnectionByResource($res);
			if ($conn){
				foreach($this->components as &$component) {
					if(method_exists($component, 'onDisconnect')) {
						if ($component->onDisconnect($conn->id)) {
							break;
						}
					}
				}
				unset($this->connections[$conn->id]);
			}
		}
		$this->log->control("Client has disconnected");
	}

	private function processData(&$res, $data) {
		//$this->log->control("Processing data...");
		$con = &$this->getConnectionByResource($res);
		if ($con->wasLastFrameFinal() && $con->isFrameComplete()) {
		    if (!empty($con->dataBuffer)) {
			    $this->log->control("Frame is complete");
			    $this->dispatchConnectionData($con);
		    }
			$this->processFrame($con, $data);
		} else {
			//$this->log->control("Frame is not complete");
			$bytesToCompleteFrame = $con->frameDataLength - $con->recvFrameDataLength();
			if ($bytesToCompleteFrame >= 1024) {
				//$this->log->control("We continue buffering data...");
				$con->dataBuffer .= RecvFrame::unmaskData($con->frameMask, $data);
				if ($con->wasLastFrameFinal() && $con->isFrameComplete()) {
				    $this->dispatchConnectionData($con);
				}
			} else {
				//$this->log->control("This should be the last buffer piece");
				$con->dataBuffer .= RecvFrame::unmaskData($con->frameMask, substr($data, 0, $bytesToCompleteFrame));
				//$this->log->control("The data is buffered, send it to the components");
				if ($con->wasLastFrameFinal()) {
				    $this->dispatchConnectionData($con);
				}
				$this->processFrame($con, substr($data, $bytesToCompleteFrame));
			}
		}
	}

	private function processFrame(&$con, $data) {
		$frame = new RecvFrame($data);
		if (!$frame->isValid()) return;
		
		if ($frame->RSV1 || $frame->RSV2 || $frame->RSV3) {
		    $this->closeConnection($con);
		    return;
		}

		if ($frame->opcode == 0) {
			$this->log->control("Continuation frame");
		} else if ($frame->opcode == 0x1) {
			$this->log->control('Text frame');
		} else if ($frame->opcode == 0x2) {
			$this->log->control('Binary frame');
		}
		
		if ($frame->opcode < 0x8) {
		    $con->multiFrameBuffer .= $con->dataBuffer;    
			$con->dataBuffer = $frame->getData();
			$con->frameDataLength = $frame->payload_len;
			$con->lastFrameOpcode = $frame->opcode;
			$con->is_last_frame = $frame->FIN;
			if ($frame->mask) {
			    $con->frameMask = $frame->mask_bytes;
			}
			
			switch ($frame->opcode) {
			    case 0x1:
                    $con->dataType = 'text';
                    break;
                case 0x2:
                    $con->dataType = 'binary';
                    break;
			}
		}
			
		if ($frame->opcode == 0x8) { //disconnect code
			$this->log->control('Client sent disconnect code');
			$this->releaseResource($con->getResource());
		} else if ($frame->FIN) {
			if ($con->isFrameComplete()) {
			    $this->dispatchConnectionData($con);
			}
		}
	}
	
	private function closeConnection(&$con) {
	    $closingFrame = new SendFrame();
	    $closingFrame->opcode = 0x08;
	    $this->sendFrame($con->getResource(), $closingFrame->getFrame());
	    $this->releaseResource($con->getResource());
	}
	
	private function sendFrame(&$res, $frame) {
	    socket_write($res, $frame);
	}

    private function dispatchConnectionData(&$con) {
        $con->multiFrameBuffer .= $con->dataBuffer;
		$this->componentsOnMessage($con->id, $con->multiFrameBuffer, $con->dataType);
		$this->resetConnectionData($con);
    }

	private function componentsOnMessage($conId, $msg, $dataType) {
		foreach($this->components as &$component) {
			if ($component->onMessage($conId, $msg, $dataType)) {
				break;
			}
		}
	}
	
	private function resetConnectionData(&$con) {
	    $con->multiFrameBuffer = '';
	    $con->dataBuffer = '';
		$con->frameDataLength = 0;
		$con->lastFrameOpcode = 0;
	}

	public function stop() {
		$this->log("Closing connections...");
		foreach($this->components as &$component) {
			if(method_exists($component, 'onStop')) {
				$component->onStop();
			}
		}
		foreach($this->connections as &$conn) {
			socket_close($conn->getResource());
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
			foreach ($this->components as &$component) {
				if (!empty($component::PROTOCOL) && in_array($component::PROTOCOL, $protocols)) {
					return $component::PROTOCOL;
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
