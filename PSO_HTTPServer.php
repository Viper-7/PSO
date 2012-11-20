<?php
class PSO_HTTPServer extends PSO_ServerPool {
	public static $connection_class = 'PSO_HTTPServerConnection';
	
	public function readData($conn) {
		$data = parent::readData($conn);
		$this->raiseEvent('Request', $conn->requestPath, $conn);
		return $data;
	}
		
	public function openPort($port, $bindip = '0.0.0.0') {
		$serverID = "{$bindip}:{$port}";

		if(isset($this->servers[$serverID]))
			trigger_error("Server already open! {$bindip}:{$port}", E_USER_ERROR);
		
		$socket = stream_socket_server("tcp://{$bindip}:{$port}", $errno, $errstr);
		
		if(!$socket) {
			trigger_error('Failed to create socket: ' . $errstr, E_USER_ERROR);
		}
		
		$conn = new PSO_Server($socket);
		$conn->pool = $this;
		
		$this->servers[$serverID] = $conn;
	}
}