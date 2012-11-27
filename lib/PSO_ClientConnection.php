<?php
class PSO_ClientConnection extends PSO_Connection {
	public $remoteHost;
	public $remotePort;

	public function __construct($stream) {
		$this->stream = $stream;
	}
}