<?php
abstract class PSO_ServerPool extends PSO_Pool {
	public static $connection_class = 'PSO_ServerConnection';
	protected $servers = array();
	
	public function findConnection($stream) {
		foreach($this->servers as $server) {
			if($server->stream == $stream) {
				return $server;
			}
		}
		
		return parent::findConnection($stream);
	}
	
	public function getStreams() {
		list($read, $write, $except) = parent::getStreams();

		foreach($this->servers as $key => $conn) {
			if($conn->timeToLive && $conn->ttlExpiry < time()) {
				$conn->close();
				unset($this->servers[$key]);
			} else {
				$read[] = $conn->stream;
			}
		}
		
		return array($read, $write, $except);
	}

	public function close() {
		parent::close();
		
		foreach($this->servers as $key => $server) {
			$server->close();
			unset($this->servers[$key]);
		}
	}
}