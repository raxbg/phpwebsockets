<?hh
class WebChat extends Component {
    public static string $PROTOCOL = "webchat";

    private $clients = Vector{};

    public function onLoad(string $ip, int $port, string $host) {
        $this->server->log->control("WebChat component loaded on $ip:$port for host $host");
    }

    public function onConnect(int $client_id): void {
        $this->clients[] = $client_id;
    }

    public function onMessage(int $client_id, string $data, string $dataType = 'text'): bool {
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
