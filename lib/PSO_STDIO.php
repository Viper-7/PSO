<?php
class PSO_STDIO extends PSO_Pool {
	public static $connection_class = 'PSO_STDIOConnection';
	
	public function __construct() {
		$class = static::$connection_class;
		$targets = array('stdin', 'stdout', 'stderr');
		
		foreach($targets as $key => $value) {
			$stream = fopen("php://fd/{$key}", 'r+');
			$conn = new $class($stream);
			$this->addConnection($conn);
			$this->$value = $conn;
		}
		
		return $conn;
	}
	
	public function send($data) {
		return $this->stdout->send($data);
	}
	
	public function sendError($data) {
		return $this->stderr->send($data);
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
		
		$read[] = $this->stdin->stream;
		
		if($this->stdout->hasOutput())
			$write[] = $this->stdout->stream;
		
		if($this->stderr->hasOutput())
			$write[] = $this->stderr->stream;
		
		return array($read, $write, $except);
	}
	
	public function addConnection($conn) {
		$this->connections[] = $conn;
		$conn->pool = $this;
	}

	public function readData($conn) {
		echo "_\r";
		$data = $conn->readData();
		
		if($data) {
			$this->raiseEvent('Data', $data);
			$conn->raiseEvent('Data', $data, NULL, $this);
		}
		
		return $data;
	}
}