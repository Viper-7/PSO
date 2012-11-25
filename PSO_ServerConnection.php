<?php
class PSO_ServerConnection extends PSO_Connection {
	public $clientIP;
	
	public function __construct($stream, $clientIP) {
		$this->stream = $stream;
		$this->clientIP = $clientIP;
	}
	
	public function close() {
		$this->pool->close();
	}
}