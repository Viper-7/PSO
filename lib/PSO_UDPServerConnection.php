<?php
class PSO_UDPServerConnection extends PSO_ServerConnection {
	public function sendBuffer() {
		if(strlen($this->outputBuffer) > static::$chunk_size) {
			$chunk = substr($this->outputBuffer, 0, static::$chunk_size);
			$this->outputBuffer = substr($this->outputBuffer, static::$chunk_size);
		} else {
			$chunk = $this->outputBuffer;
			$this->outputBuffer = '';
		}

		$written = stream_socket_sendto($this->stream, $chunk, 0, $this->remoteHost);

		if($written === FALSE || $written < strlen($chunk)) {
			$this->disconnect();
		}
	}
}