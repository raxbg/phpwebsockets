<?hh
class Connection {
    public static int $ai_count = 0;
    public int $id;
    public string $ip;

    protected $resource;

    public function __construct($res, string $ip) {
        $this->resource = $res;
        $this->id = ++self::$ai_count;//TODO: make sure this does not overlap with other connection ids
        $this->ip = $ip;
    }

    public function getResource() {
        return $this->resource;
    }

    public function send(string $data) {
        fwrite($this->resource, $data);
        //TODO: Split these into small chunks that can be sent fast
        //Maybe even implement a job queue, also make this function async
    }

    public function close() {
        fclose($this->resource);
    }
}
