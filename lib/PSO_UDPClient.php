<?php
class PSO_UDPClient extends PSO_ClientPool {
	public static $connection_class = 'PSO_UDPClientConnection';
	
	public function __construct() {
		$args = func_get_args();
		if($args) 
			call_user_func_array(array($this, 'addTarget'), $args);
	}

	public function addTarget($host, $port, $bindip='0.0.0.0') {
		$class = static::$connection_class;
		$options['socket']['bindto'] = $bindip;
		$context = stream_context_create($options);
		$stream = stream_socket_client("udp://{$host}:{$port}", $errno, $errstr, ini_get("default_socket_timeout"), STREAM_CLIENT_CONNECT, $context);
		$conn = new $class($stream);
		$conn->remoteHost = $host;
		$conn->remotePort = $port;
		$this->addConnection($conn);
		return $conn;
	}

}