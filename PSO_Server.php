<?php
class PSO_Server extends PSO_Connection {
	public function __construct($stream) {
		$this->stream = $stream;
	}

	public function readData() {
		$stream = stream_socket_accept($this->stream, 0, $clientIP);

		$poolClass = get_class($this->pool);
		$class = $poolClass::$connection_class;
		$conn = new $class($stream, $clientIP);

		$this->pool->addConnection($conn);
	}

}