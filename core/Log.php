<?php
abstract class Log {
	abstract public function control($message);
	abstract public function error($message);
	abstract public function history(HistoryLog $data);
}
