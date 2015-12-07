<?hh
class WebSocket extends Wrapper {
    private string $ws_guid = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
    private Map<string, Component> $components = Map {};
    private Map<string, ?Component> $hosts = Map {};
    private Map<int, WebSockConnection> $clients = Map {};

    public function init(): void {
        $hosts = $this->config->get('hosts');

        if ($hosts !== null) {
            foreach($hosts as $host => $components) {
                foreach ($components as $component) {
                    $this->hosts[$host] = $this->loadComponent($component, $host);
                }
            }
        }
    }

    public function loadComponent(string $component, string $host): ?Component {
        $c = new $component($this->server);
        if ($c instanceof Component && !empty($c::$PROTOCOL)) {
            if ($this->server !== null) {
                $c->onLoad($this->server->ip, $this->server->port, $host);
            }
            $this->components[$c::$PROTOCOL] = $c;
            return $c;
        } else {
            $this->log->error("Failed to load component $component. It does not implement the Component interface.");
        }
        return null;
    }

    public function onConnect(Connection $con) {
        $this->clients[$con->id] = new WebSockConnection($con);
    }

    public function onData(Connection $con, string $data) {
        $websock_con = $this->clients->get($con->id);

        if ($websock_con !== null) {
            if (!$websock_con->isAuthorized()) {
                $this->authClient($websock_con, $data);
            } else {
                $this->processData($websock_con, $data);
            }
        }
    }

    public function onDisconnect(Connection $con) {
        if ($this->clients->contains($con->id)) {
            $websock_con = $this->clients->get($con->id);

            if ($websock_con !== null && $websock_con->isAuthorized()) {
                $protocol = $websock_con->protocol;
                $component = $this->components->get($protocol);
                if ($component !== null) {
                    $component->onDisconnect($websock_con);
                }
            }
            $this->clients->remove($con->id);
        }
    }

    public function onStop() {
        foreach($this->components as $component) {
            $component->onStop();
        }
    }

    private function componentsOnMessage($con, $msg, $dataType) {
        $component = $this->components->get($con->protocol);
        if ($component !== null) {
            $component->onMessage($con, $msg, $dataType);
        }
    }

    private function resetConnectionData(WebSockConnection $con): void {
        $con->multiFrameBuffer = '';
        $con->dataBuffer = '';
        $con->frameDataLength = 0;
        $con->lastFrameOpcode = 0;
    }

    private function dispatchConnectionData(WebSockConnection $con) {
        $con->multiFrameBuffer .= $con->dataBuffer;
        $this->componentsOnMessage($con, $con->multiFrameBuffer, $con->dataType);
        $this->resetConnectionData($con);
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

    private function validateWsHeaders(&$headers) {
        return (!empty($headers['Upgrade']) && !empty($headers['Connection']) && !empty($headers['Sec-WebSocket-Key']) && !empty($headers['Sec-WebSocket-Version']));
    }

    private function selectProtocol(&$headers) {
        if (!empty($headers['Sec-WebSocket-Protocol'])) {
            $protocols = explode(',', $headers['Sec-WebSocket-Protocol']);
            foreach ($this->components as $component) {
                if (in_array($component::$PROTOCOL, $protocols)) {
                    return $component::$PROTOCOL;
                }
            }
        }
        return false;
    }

    private function authClient(WebSockConnection $con, $data) {
        $headers = $this->parse_headers($data);
        if ($this->validateWsHeaders($headers)) {
            $protocol = $this->selectProtocol($headers);
            if ($protocol) {
                $con->protocol = $protocol;

                $response = $this->buildHandshake($headers, $protocol);
                $con->sendRaw($response);

                $con->setAuthorized(true);
                $this->components[$protocol]->onConnect($con);
            } else {
                $this->log->debug("Unsupported protocol. Disconnecting client...");
                $con->close();
            }
        } else {
            $this->log->debug("Header validation failed.");
            $con->close();
        }
    }

    private function processData(WebSockConnection $con, $data) {
        if ($con->wasLastFrameFinal() && $con->isFrameComplete()) {
            if (!empty($con->dataBuffer)) {
                //$this->log->debug("Frame is complete");
                $this->dispatchConnectionData($con);
            }
            $this->processFrame($con, $data);
        } else {
            $bytesToCompleteFrame = $con->frameDataLength - $con->recvFrameDataLength();
            if ($bytesToCompleteFrame >= 1024) {
                $con->dataBuffer .= RecvFrame::unmaskData($con->frameMask, $data);
                if ($con->wasLastFrameFinal() && $con->isFrameComplete()) {
                    $this->dispatchConnectionData($con);
                }
            } else {
                $con->dataBuffer .= RecvFrame::unmaskData($con->frameMask, substr($data, 0, $bytesToCompleteFrame));
                if ($con->wasLastFrameFinal()) {
                    $this->dispatchConnectionData($con);
                }
                $this->processFrame($con, substr($data, $bytesToCompleteFrame));
            }
        }
    }

    private function processFrame(WebSockConnection $con, $data) {
        $frame = new RecvFrame($data);
        if (!$frame->isValid()) return;

        if ($frame->RSV1 || $frame->RSV2 || $frame->RSV3) {
            $con->close();
            return;
        }

        if ($frame->opcode == 0) {
            //$this->log->debug("Continuation frame");
        } else if ($frame->opcode == 0x1) {
            //$this->log->debug('Text frame');
        } else if ($frame->opcode == 0x2) {
            //$this->log->debug('Binary frame');
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
            //$this->log->debug('Client sent disconnect code');
        } else if ($frame->FIN) {
            if ($con->isFrameComplete()) {
                $this->dispatchConnectionData($con);
            }
        }
    }
}
