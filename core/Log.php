<?hh
abstract class Log {
    abstract public function debug(string $message): void;
    abstract public function error(string $message): void;
}
