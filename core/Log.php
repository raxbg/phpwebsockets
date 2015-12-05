<?hh
abstract class Log {
	abstract public function control(string $message): void;
	abstract public function error(string $message): void;
	abstract public function history(HistoryLog $data): void;
}
