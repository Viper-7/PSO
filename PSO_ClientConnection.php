<?php
class PSO_ClientConnection extends PSO_Connection {
	public function __construct($stream) {
		$this->stream = $stream;
	}
}