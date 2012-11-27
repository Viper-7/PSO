<?php
class PSO_ProcessConnection extends PSO_Connection {
	public $stream;
	public $stdin;
	public $stdout;
	public $stderr;
	
	protected $stdoutLoc = 0;
	protected $stderrLoc = 0;
	
	public function __construct($stream) {
		$this->stream = $stream;
	}
	
	public function readData() {
		$read = $write = $except = array();
		$data = NULL;
		
		if($this->pool->hasData($this, 'stdout')) {

			if(PHP_OS == 'WINNT')
				fseek($this->stdout, $this->stdoutLoc, SEEK_SET);
			
			$data = fread($this->stdout, static::$chunk_size);
			
			if(PHP_OS == 'WINNT')
				$this->stdoutLoc = ftell($this->stdout);
			
		} elseif($this->pool->hasData($this, 'stderr')) {
			
			if(PHP_OS == 'WINNT')
				fseek($this->stderr, $this->stderrLoc, SEEK_SET);
			
			$data = fread($this->stderr, static::$chunk_size);
			
			if(PHP_OS == 'WINNT')
				$this->stderrLoc = ftell($this->stderr);
			
		}
		
		return $data;
	}
	
	public function sendBuffer() {
		if($this->outputBuffer === '')
			return;
		
		if(strlen($this->outputBuffer) > static::$chunk_size) {
			$chunk = substr($this->outputBuffer, 0, static::$chunk_size);
			$this->outputBuffer = substr($this->outputBuffer, static::$chunk_size);
		} else {
			$chunk = $this->outputBuffer;
			$this->outputBuffer = '';
		}

		$written = @fwrite($this->stdin, $chunk);
		
		if(!$written) {
			$this->disconnect();
		}
	}
}