<?php
class PSO_STDIOConnection extends PSO_Connection {
	public function __construct($stream) {
		$this->stream = $stream;
	}

	public function hasOutput() {
		if(parent::hasOutput()) {
			$this->sendBuffer();
		}
		
		return false;
	}
}