<?php
class PSO_ProcessConnection extends PSO_Connection {
	public $stream;
	public $stdin;
	public $stdout;
	public $stderr;
	
	public function __construct($stream) {
		$this->stream = $stream;
	}
	
	public function readData() {
		$read = array($this->stdout, $this->stderr);
		$write = $except = array();
		stream_select($read, $write, $except, 0, 0);
		$data = fread(reset($read), static::$chunk_size);
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