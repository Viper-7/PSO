<?php
abstract class PSO_ClientPool extends PSO_Pool {
	public static $connection_class = 'PSO_ClientConnection';
	
	public function getStreams() {
		list($read, $write, $except) = parent::getStreams();
		if(!$read) $this->close();
		return array($read, $write, $except);
	}
}