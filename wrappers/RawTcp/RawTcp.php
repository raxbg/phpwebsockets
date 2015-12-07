<?hh
class RawTcp extends Wrapper {
    public function init() {}

    public function onConnect(Connection $con) {
        $con->send("Hello\n");
    }

    public function onDisconnect(Connection $con) {
        $this->log->debug("Client disconnected " . $con->ip);
    }

    public function onData(Connection $con, string $data) {
        $con->send($data);
    }

    public function onStop() {}
}
