<?hh
class EchoComponent extends Component {
    public static string $PROTOCOL = "echo";

    public function onMessage(int $client_id, string $data, string $dataType = 'text'): bool {
        $this->server->send($client_id, $data, $dataType);
        return true;
    }
}
