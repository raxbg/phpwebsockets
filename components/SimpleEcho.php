<?hh
class SimpleEcho extends Component {
    public static string $PROTOCOL = "echo";

    public function onLoad(string $ip, int $port, string $host) {
        $this->server->log->control("SimpleEcho component loaded on $ip:$port for host $host");
    }

    public function onMessage(int $client_id, string $data, string $dataType = 'text'): bool {
        $this->server->send($client_id, $data, $dataType);
        return true;
    }
}
