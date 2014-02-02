<?php
interface iLog {
	public function control($message);
	public function error($message);
	public function history(HistoryLog $data);
}
