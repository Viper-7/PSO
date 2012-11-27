<?php
class PSO_FD extends PSO_Pool {
	public static $connection_class = 'PSO_FDConnection';
	
	public function __construct() {
		$args = func_get_args();
		
		foreach($args as $arg) {
			$this->open($arg);
		}
	}
	
	public function open($fd) {
		$class = static::$connection_class;
		$stream = fopen("php://fd/{$fd}", 'r+');
		$conn = new $class($stream);
		$this->addConnection($conn);
		return $conn;
	}
}