<?hh
class WebChat extends Component {
    public static string $PROTOCOL = "webchat";

    private Vector<WebSockConnection> $clients = Vector {};

    public function onLoad(string $ip, int $port, string $host) {
        $this->server->log->debug("WebChat component loaded on $ip:$port for host $host");
    }

    public function onConnect(WebSockConnection $con): void {
        $this->clients->add($con);
    }

    public function onDisconnect(WebSockConnection $con): void {
        $this->server->log->debug("Client disconnected");
    }

    public function onMessage(WebSockConnection $con, string $data, string $dataType = 'text'): void {
        $this->server->log->debug("Data received: " . $data);
        foreach ($this->clients as $client) {
            if ($client == $con) continue;
            $client->send($data, ($dataType == 'binary' ? true : false));
        }
    }
}
