<?php
class PSO_Connection {
	use PSO_EventProvider;

	public static $chunk_size = 8192;
	public $timeToLive;
	public $ttlExpiry;
	
	public $pool;
	public $stream;
	public $sent = '';
	
	protected $outputBuffer = '';
	
	public function readData() {
		$this->ttlExpiry = time() + $this->timeToLive;
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

		$this->ttlExpiry = time() + $this->timeToLive;
		$written = @fwrite($this->stream, $chunk);
		$this->sent .= substr($chunk,0,$written);
		$this->pool->bytesWritten += $written;
		
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
	
	public function disconnect($force = false) {
		if(!$force) {
			// If the connection is still active, recurse to drain the buffer before disconnecting
			if(is_resource($this->stream) && $this->outputBuffer) {
				$client = $this;
				return $this->pool->onTick(function() use ($client) {
					$client->disconnect();
					return 'unregister';
				});
			}
		}
		
		$this->pool->disconnect($this);
		@fclose($this->stream);
		$this->stream = NULL;
	}
}