<?php
function __autoload($class) {
	if(file_exists($file = "{$class}.php")) {
		include $file;
	}
}

abstract class PSO {
	protected static $select_timeout_s = 1;
	protected static $select_timeout_us = 0;
	
	public static function drain() {
		$pools = func_get_args();
		
		while(true) {
			$read = $write = $except = array();
			
			foreach($pools as $pool) {
				list($poolRead, $poolWrite) = $pool->getStreams();
				$read = array_merge($read, $poolRead);
				$write = array_merge($write, $poolWrite);
			}

			if(!$read) return;
			
			if(stream_select($read, $write, $except, self::$select_timeout_s, self::$select_timeout_us)) {
				foreach($read as $fp) {
					list($pool, $conn) = self::find_connection($fp, $pools);
					
					$pool->readData($conn);
				}
				
				foreach($write as $fp) {
					list($pool, $conn) = self::find_connection($fp, $pools);
					$pool->sendBuffer($conn);
				}
			}
		}
	}
	
	protected static function find_connection($fp, $pools) {
		foreach($pools as $pool) {
			if($conn = $pool->findConnection($fp)) {
				return array($pool, $conn);
			}
		}
	}
}