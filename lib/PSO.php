<?php
abstract class PSO {
	public static $ip;
	protected static $pools;
	
	protected static function find_connection($fp, $pools) {
		foreach($pools as $pool) {
			if($conn = $pool->findConnection($fp)) {
				return array($pool, $conn);
			}
		}
	}
	
	public static function divideSize($bytes, $precision = 0) {
		foreach(array('k','m','g','t','p') as $char) {
			$bytes /= 1024;
			if($bytes < 1024)
				return number_format($bytes, $precision) . "{$char}";
		}
	}
	
	public static function addPool($pool) {
		self::$pools[] = $pool;
	}
	
	public function connect($pool1, $pool2) {
		$pool = new PSO_ConnectedPool($pool1, $pool2);
		return $pool;
	}
	
	public static function drain() {
		self::$ip = ip2long(trim(file_get_contents('http://icanhazip.com/')));
		
		self::$pools = func_get_args();
		$start = microtime(true);
		
		while(true) {
			$read = $write = $except = array();
			
			$open = false;
			foreach(self::$pools as $pool) {
				list($poolRead, $poolWrite, $poolExcept) = $pool->getStreams();
				$read = array_merge($read, $poolRead);
				$write = array_merge($write, $poolWrite);
				if($pool->open) $open = true;
			}
			
			if(!$open) break;
			
			// Hackish fix to catch process closure, leave the process handle in the read array until now
			// @todo use $pool->open instead
			$read = array_filter($read, function($stream) { return get_resource_type($stream) != 'process'; });
			
			$wait = PSO_Pool::$next_poll - microtime(true);
			if($wait < 0) $wait = 0;
			$wait_s = floor($wait);
			$wait_us = floor(($wait - $wait_s) * 1000000);
			
			if($read || $write || $except) {
				if(stream_select($read, $write, $except, $wait_s, $wait_us)) {
					foreach($write as $fp) {
						list($pool, $conn) = self::find_connection($fp, self::$pools);
						
						$pool->sendBuffer($conn);
					}

					foreach($read as $fp) {
						list($pool, $conn) = self::find_connection($fp, self::$pools);
						
						if($conn) {
							if($conn->stream)
								$pool->readData($conn);
							else
								$conn->disconnect();
						}
					}
				}
			}
			
			foreach(self::$pools as $pool) {
				$pool->handleTick();
			}
		}
		
		$end = microtime(true);
		return number_format($end - $start, 4);
	}
}