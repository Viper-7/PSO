<?php
class PSO_ServerConnection extends PSO_Connection {
	public $clientIP;
	
	public function __construct($stream, $clientIP) {
		$this->stream = $stream;
		$this->clientIP = $clientIP;
	}
	
	public function addConnection($conn) {
		stream_set_read_buffer($conn->stream, 4096);
		parent::addConnection($conn);
	}
}