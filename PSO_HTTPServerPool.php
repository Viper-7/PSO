<?php
class PSO_HTTPServerPool extends PSO_TCPServerPool {
	public static $connection_class = 'PSO_HTTPServerConnection';
	
	public function readData($conn) {
		$data = parent::readData($conn);
		$this->raiseEvent('Request', $conn->requestPath, $conn);
		return $data;
	}
}