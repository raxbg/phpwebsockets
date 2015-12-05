<?hh
abstract class Component {
    public static string $PROTOCOL = '';

    public function __construct(
        protected Server $server
    ){}

    public function onLoad(string $ip, int $port, string $host) {}
    public function onConnect(WebSockConnection $con) {}
    public function onDisconnect(WebSockConnection $con) {}
    public function onStop() {}

    abstract public function onMessage(WebSockConnection $con, string $data, string $dataType): void;
}
