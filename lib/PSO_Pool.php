<?php
abstract class PSO_Pool {
	use PSO_EventProvider;
	
	public static $connection_class = 'PSO_Connection';
	public static $next_poll = 0;
	public static $poll_interval = 0.1;
	
	public $open 		 = true;
	
	public $startTime;
	public $bytesRead    = 0;
	public $bytesWritten = 0;
	
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
	
	public function getReadSpeed() {
		return $this->divideSize($this->bytesRead / (microtime(true) - $this->startTime));
	}
	
	public function getWriteSpeed() {
		return $this->divideSize($this->bytesWritten / (microtime(true) - $this->startTime));
	}
	
	public function divideSize($bytes) {
		foreach(array('k','m','g','t','p') as $char) {
			$bytes /= 1024;
			if($bytes < 1024)
				return number_format($bytes, 0) . "{$char}";
		}
	}
	
	public function handleTick() {
		if(self::$next_poll < microtime(true)) {
			$this->raiseEvent('Tick');
			self::$next_poll = microtime(true) + self::$poll_interval;
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
		if(!$this->startTime)
			$this->startTime = microtime(true);

		$data = $conn->readData();

		$this->bytesRead += strlen($data);
		
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