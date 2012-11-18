<?php
class PSO_Connection {
	use EventProvider;

	public static $chunk_size = 4096;
	
	public $pool;
	public $stream;
	
	protected $outputBuffer = '';
	
	public function readData() {
		$data = fread($this->stream, self::$chunk_size);
		return $data;
	}
	
	public function send($data) {
		$this->outputBuffer .= $data;
	}
	
	public function hasOutput() {
		return $this->outputBuffer != '';
	}
	
	public function sendBuffer() {
		if(!$this->outputBuffer)
			return;
		
		if(strlen($this->outputBuffer) > self::$chunk_size) {
			$chunk = substr($this->outputBuffer, 0, self::$chunk_size);
			$this->outputBuffer = substr($this->outputBuffer, self::$chunk_size);
		} else {
			$chunk = $this->outputBuffer;
			$this->outputBuffer = '';
		}
		
		$written = @fwrite($this->stream, $chunk);
		if(!$written) {
			$this->disconnect();
		}
	}
	
	public function disconnect() {
		$this->pool->disconnect($this);
		@fclose($this->stream);
	}
}