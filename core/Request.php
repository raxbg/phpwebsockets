<?hh
class Request {
    public function __construct(
        public Connection $con,
        public string $data
    ){}

    public function getConnection() {
        return $this->con;
    }

    public function getData() {
        return $this->data;
    }
}
