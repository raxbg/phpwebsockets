<?hh
abstract class Component {

    public function __construct(
        protected Server $server
    ){}

    public function onLoad(string $ip, int $port, string $host) {}
    public function onDisconnect(int $connection_id) {}
    abstract public function onMessage(int $client_id, string $data, string $dataType): bool;
}
