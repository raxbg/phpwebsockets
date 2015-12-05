<?hh
class Connection {
    public static int $ai_count = 0;
    public int $id;
    public string $ip;

    protected $resource;

    public function __construct(&$res, string $ip) {
        $this->resource = $res;
        $this->id = ++self::$ai_count;
        $this->ip = $ip;
    }

    public function getResource() {
        return $this->resource;
    }

    public function send(string $data) {
        fwrite($this->resource, $data);
    }

    public function close() {
        fclose($this->resource);
    }
}
