<?php
class PSO_UDPServer extends PSO_ServerPool {
	public static $connection_class = 'PSO_UDPServerConnection';
	public $connections = array();
	
	public function __construct() {
		$args = func_get_args();
		
		if($args) 
			call_user_func_array(array($this, 'openPort'), $args);
	}

	public function openPort($port, $bindip = '0.0.0.0') {
		$serverID = "{$bindip}:{$port}";

		if(isset($this->servers[$serverID]))
			trigger_error("Server already open! {$bindip}:{$port}", E_USER_ERROR);
		
		$socket = stream_socket_server("udp://{$bindip}:{$port}", $errno, $errstr, STREAM_SERVER_BIND);
		
		if(!$socket) {
			trigger_error('Failed to create socket: ' . $errstr, E_USER_ERROR);
		}
		
		$conn = new PSO_Server($socket);
		$conn->pool = $this;
		
		$this->servers[$serverID] = $conn;
	}

	public function readData($conn) {
		$class = static::$connection_class;
		$data = stream_socket_recvfrom($conn->stream, $class::$chunk_size, 0, $peer);

		if(!isset($this->connections[$peer])) {
			$conn = new $class($conn->stream, null);
			$conn->pool = $this;
			$conn->remoteHost = $peer;
			
			$this->connections[$peer] = $conn;
		}

		$conn = $this->connections[$peer];

		if($data) {
			$this->raiseEvent('Data', $data, NULL, $conn);
			$conn->raiseEvent('Data', $data);
		}
		
		return $data;
	}

	public function findConnection($stream) {
		foreach($this->connections as $conn) {
			if($conn->hasOutput()) {
				return $conn;
			}
		}
		
		foreach($this->servers as $server) {
			if($server->stream == $stream) {
				return $server;
			}
		}
	}
	
	public function getStreams() {
		$read = $write = $except = array();

		foreach($this->servers as $conn) {
			$read[] = $conn->stream;
		}
		
		foreach($this->connections as $conn) {
			if($conn->hasOutput())
				$write[] = $conn->stream;
		}
		

		return array($read, $write, $except);
	}
	
}