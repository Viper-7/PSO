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
		list($read, $write) = parent::getStreams();
		foreach($this->servers as $conn) {
			$read[] = $conn->stream;
		}
		return array($read, $write);
	}
}