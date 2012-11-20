<?php
abstract class PSO_Pool {
	use EventProvider;
	
	public static $connection_class = 'PSO_Connection';
	protected $connections = array();
	
	public function broadcast($data) {
		foreach($this->connections as $conn) {
			$conn->send($data);
		}
	}
	
	public function findConnection($stream) {
		foreach($this->connections as $conn) {
			if($conn->stream == $stream) {
				return $conn;
			}
		}
	}
	
	public function getStreams() {
		$read = $write = array();
		
		foreach($this->connections as $conn) {
			$read[] = $conn->stream;
			
			if($conn->hasOutput())
				$write[] = $conn->stream;
		}
		
		return array($read, $write);
	}
	
	public function addConnection($conn) {
		$this->connections[] = $conn;
		$conn->pool = $this;
		$this->raiseEvent('Connect', array(), NULL, $conn);
	}
	
	public function readData($conn) {
		$data = $conn->readData();
		
		if($data) {
			$this->raiseEvent('Data', $data, NULL, $conn);
			$conn->raiseEvent('Data', $data);
		}
		
		return $data;
	}
	
	public function sendBuffer($conn) {
		$conn->sendBuffer();
	}
	
	public function disconnect($conn) {
		$conn->raiseEvent('Disconnect');
		$this->raiseEvent('Disconnect', array(), NULL, $conn);
		
		$key = array_search($conn, $this->connections);
		unset($this->connections[$key]);
	}
}