<?php
class PSO_Connection {
	use PSO_EventProvider;

	public static $chunk_size = 8192;
	
	public $pool;
	public $stream;
	
	protected $outputBuffer = '';
	
	public function readData() {
		$data = fread($this->stream, static::$chunk_size);
		return $data;
	}
	
	public function sendBuffer() {
		if(strlen($this->outputBuffer) > static::$chunk_size) {
			$chunk = substr($this->outputBuffer, 0, static::$chunk_size);
			$this->outputBuffer = substr($this->outputBuffer, static::$chunk_size);
		} else {
			$chunk = $this->outputBuffer;
			$this->outputBuffer = '';
		}

		$written = @fwrite($this->stream, $chunk);
		if($written === FALSE || $written < strlen($chunk)) {
			$this->disconnect();
		}
	}

	public function send($data) {
		$this->outputBuffer .= $data;
	}
	
	public function hasOutput() {
		return $this->outputBuffer != '';
	}
	
	public function disconnect() {
		// If the connection is still active, recurse to drain the buffer before disconnecting
		if(is_resource($this->stream) && $this->outputBuffer) {
			$client = $this;
			return $this->pool->onTick(function() use ($client) {
				$client->disconnect();
				return 'unregister';
			});
		}
		
		$this->pool->disconnect($this);
		@fclose($this->stream);
		$this->stream = NULL;
	}
}