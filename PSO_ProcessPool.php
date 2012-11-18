<?php
class PSO_ProcessPool extends PSO_Pool {
	public static $connection_class = 'PSO_ProcessConnection';
	
	public function findConnection($stream) {
		foreach($this->connections as $conn) {
			if($conn->stdin == $stream || $conn->stdout == $stream || $conn->stderr == $stream) {
				return $conn;
			}
		}
	}
	
	public function getStreams() {
		$read = $write = array();
		
		foreach($this->connections as $conn) {
			$read[] = $conn->stdout;
			$read[] = $conn->stderr;
			
			if($conn->hasOutput())
				$write[] = $conn->stdin;
		}
		
		return array($read, $write);
	}
	
	public function open($command, $path=NULL, $env=array()) {
		$spec = array(
			array('pipe', 'r'),
			array('pipe', 'w'),
			array('pipe', 'w')
		);
		
		$stream = proc_open($command, $spec, $pipes, $path, $env);
		$class = static::$connection_class;
		$conn = new $class($stream);
		list($conn->stdin, $conn->stdout, $conn->stderr) = $pipes;
		stream_set_blocking($conn->stdout, 0);
		stream_set_blocking($conn->stderr, 0);
		$this->addConnection($conn);
		return $conn;
	}
}