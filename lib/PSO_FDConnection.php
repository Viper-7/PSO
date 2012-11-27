<?php
class PSO_FDConnection extends PSO_Connection {
	public function __construct($stream) {
		$this->stream = $stream;
	}

	public function hasOutput() {
		// Bypass stream_select()'s $write check
		if(parent::hasOutput()) {
			$this->sendBuffer();
		}
		
		return false;
	}
}