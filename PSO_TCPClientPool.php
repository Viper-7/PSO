<?php
class PSO_TCPClientPool extends PSO_ClientPool {
	public static $connection_class = 'PSO_TCPClientConnection';

	public function addTarget($host, $port) {
		$class = static::$connection_class;
		$stream = stream_socket_client("tcp://{$host}:{$port}");
		$conn = new $class($stream);
		$this->addConnection($conn);
		return $conn;
	}

}