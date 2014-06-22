<?php
class FileLog extends Log {
	private $logFile;

	public function __construct($file = NULL) {
		if (!is_string($file)) {
			$this->logFile = DIR_WS_LOG . "error.log";
		} else {
			if ($file[0] == '/') {//absolute path
				$this->logFile = $file;
			} else {
				$this->logFile = DIR_WS_LOG . $file;
			}
		}
	}

	public function control($message) {
		echo trim($message)."\n";
	}

	public function error($message) {
		file_put_contents($this->logFile, trim($message)."\n", FILE_APPEND);
	}

	public function history(HistoryLog $data) {
	}
}
