<?php
abstract class PSO_Pool {
	use PSO_EventProvider;
	
	public static $connection_class = 'PSO_Connection';
	public $open = true;
	protected $connections = array();
	
	public function broadcast($data) {
		foreach($this->connections as $conn) {
			$conn->send($data);
		}
	}
	
	public function send($data) {
		return $this->broadcast($data);
	}
	
	public function findConnection($stream) {
		foreach($this->connections as $conn) {
			if($conn->stream == $stream) {
				return $conn;
			}
		}
	}
	
	public function getStreams() {
		$read = $write = $except = array();
		
		foreach($this->connections as $conn) {
			if(!$conn->stream) {
				$conn->disconnect();
				continue;
			}
			
			$read[] = $conn->stream;
			$except[] = $conn->stream;
			
			if($conn->hasOutput())
				$write[] = $conn->stream;
		}
		
		return array($read, $write, $except);
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
	
	public function close() {
		$this->raiseEvent('Close');
		
		foreach($this->connections as $key => $conn) {	
			$conn->raiseEvent('Close');
			unset($this->connections[$key]);
		}
		
		$this->open = false;
	}
}