<?php
class PSO_ClientConnection extends PSO_Connection {
	public function __construct($stream) {
		$this->stream = $stream;
	}
	
	public function addConnection($conn) {
		stream_set_read_buffer($conn->stream, 4096);
		parent::addConnection($conn);
	}
}