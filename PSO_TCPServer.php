<?php
class PSO_TCPServer extends PSO_ServerPool {
	public static $connection_class = 'PSO_TCPServerConnection';
	
	public function __construct() {
		$args = func_get_args();
		if($args) 
			call_user_func_array(array($this, 'openPort'), $args);
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