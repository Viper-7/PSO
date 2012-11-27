<?php
class PSO_UDPClientConnection extends PSO_ClientConnection {
	public function readData() {
		$data = fread($this->stream, static::$chunk_size);
		return $data;
	}
}