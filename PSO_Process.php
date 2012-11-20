<?php
class PSO_Process extends PSO_Pool {
	public static $connection_class = 'PSO_ProcessConnection';
	protected $doWrites = false;	// ASYNC - Hold off on writes until the process has started
	
	public function findConnection($stream) {
		foreach($this->connections as $conn) {
			if($conn->stdin == $stream || $conn->stdout == $stream || $conn->stderr == $stream) {
				return $conn;
			}
		}
	}
	
	public function hasData($conn, $prop) {
		$stream = $conn->$prop;
		
		$loc = ftell($stream);
		$data = fread($stream, 1);
		fseek($stream, $loc, SEEK_SET);

		return $data !== '' && $data !== FALSE;
	}
	
	public function getStreams() {
		$read = $write = array();
		
		if(PHP_OS == 'WINNT') {
			foreach($this->connections as $conn) {
				if($this->hasData($conn, 'stdout'))
					$read[] = $conn->stdout;
				
				$read[] = $conn->stream;
				
				if($conn->hasOutput() && $this->doWrites) {
					$write[] = $conn->stdin;
				}
			}
		} else {
			foreach($this->connections as $conn) {
				$read[] = $conn->stdout;
				$read[] = $conn->stderr;
				
				if($conn->hasOutput())		// need doWrites check on *nix ?
					$write[] = $conn->stdin;
			}
		}
		
		return array($read, $write);
	}
	
	public function open($command, $path=NULL, $env=array()) {
		if(PHP_OS == 'WINNT') {
			$stdoutFile = tempnam(sys_get_temp_dir(), 'out');
			$stderrFile = tempnam(sys_get_temp_dir(), 'err');

			$stdout = fopen($stdoutFile, 'w+');
			$stderr = fopen($stderrFile, 'w+');
			
			$spec = array(
				array('pipe', 'r'),
				$stdout,
				$stderr
			);
		} else {
			$spec = array(
				array('pipe', 'r'),
				array('pipe', 'w'),
				array('pipe', 'w')
			);
		}
		
		$stream = proc_open($command, $spec, $pipes, $path, $env);
		$class = static::$connection_class;
		$conn = new $class($stream);
		
		if(PHP_OS == 'WINNT') {
			$conn->stdin = $pipes[0];
			$conn->stdout = $spec[1];
			$conn->stderr = $spec[2];
			$conn->stream = $stream;
		} else {
			list($conn->stdin, $conn->stdout, $conn->stderr) = $pipes;
		}
		
		$this->addConnection($conn);
		
		$start = microtime(true);
		$this->onTick(function() use ($start) { if(microtime(true) - $start > 0.1) { $this->enableWrites(); return 'unregister'; }});

		return $conn;
	}
	
	protected function enableWrites() {
		$this->doWrites = true;
	}
}