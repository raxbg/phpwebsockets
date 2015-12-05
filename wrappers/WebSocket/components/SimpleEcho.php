<?hh
class SimpleEcho extends Component {
    public static string $PROTOCOL = "echo";

    public function onLoad(string $ip, int $port, string $host) {
        $this->server->log->control("SimpleEcho component loaded on $ip:$port for host $host");
    }

    public function onMessage(WebSockConnection $con, string $data, string $dataType = 'text'): void {
        $con->send($data, ($dataType == 'binary' ? true : false));
    }
}
