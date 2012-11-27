<?php
class PSO_IRCClient extends PSO_ClientPool {
	public static $connection_class = 'PSO_IRCClientConnection';
	
	public $nick;
	
	public function __construct($nick, $host=NULL, $port=6667, $bindip='0.0.0.0') {
		$this->nick = $nick;
		
		if($host) {
			$this->addServer($host, $port, $bindip);
		}
	}
	
	public function addServer($host, $port=6667, $bindip='0.0.0.0') {
		$class = static::$connection_class;
		$options['socket']['bindto'] = $bindip;
		$context = stream_context_create($options);
		$stream = stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, ini_get("default_socket_timeout"), STREAM_CLIENT_CONNECT, $context);
		$conn = new $class($stream);
		$conn->nick = $this->nick;
		$conn->remoteHost = $host;
		$conn->remotePort = $port;
		$this->addConnection($conn);
		return $conn;
	}

}