<?hh
class FileLog extends Log {
    private string $logFile;

    public function __construct(?string $file = NULL) {
        if (!is_string($file)) {
            $this->logFile = DIR_LOG . "error.log";
        } else {
            if ($file[0] == '/') {//absolute path
                $this->logFile = $file;
            } else {
                $this->logFile = DIR_LOG . $file;
            }
        }
    }

    public function control(string $message): void {
        echo trim($message)."\n";
    }

    public function error(string $message): void {
        file_put_contents($this->logFile, trim($message)."\n", FILE_APPEND);
    }

    public function history(HistoryLog $data): void {
    }
}
