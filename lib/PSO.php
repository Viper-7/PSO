<?php
abstract class PSO {
	protected static $next_poll = 0;
	protected static $poll_interval = 1;
	
	protected static function find_connection($fp, $pools) {
		foreach($pools as $pool) {
			if($conn = $pool->findConnection($fp)) {
				return array($pool, $conn);
			}
		}
	}

	public function connect($pool1, $pool2) {
		$pool = new PSO_ConnectedPool($pool1, $pool2);
		return $pool
	}
	
	public static function drain() {
		$pools = func_get_args();
		
		while(true) {
			$read = $write = $except = array();
			
			foreach($pools as $pool) {
				list($poolRead, $poolWrite, $poolExcept) = $pool->getStreams();
				$read = array_merge($read, $poolRead);
				$write = array_merge($write, $poolWrite);
			}

			if(!$read) return;
			
			// Hackish fix to catch process closure, leave the process handle in the read array until now
			$read = array_filter($read, function($stream) { return get_resource_type($stream) != 'process'; });
			
			$wait = self::$next_poll - microtime(true);
			if($wait < 0) $wait = 0;
			$wait_s = floor($wait);
			$wait_us = floor(($wait - $wait_s) * 1000000);
			
			if($read || $write || $except) {
				if(stream_select($read, $write, $except, $wait_s, $wait_us)) {
					foreach($write as $fp) {
						list($pool, $conn) = self::find_connection($fp, $pools);
						
						$pool->sendBuffer($conn);
					}

					foreach($read as $fp) {
						list($pool, $conn) = self::find_connection($fp, $pools);
						
						if($conn) {
							if($conn->stream)
								$pool->readData($conn);
							else
								$conn->disconnect();
						}
					}
				}
			}
			
			
			if(self::$next_poll < microtime(true)) {
				foreach($pools as $pool) {
					$pool->raiseEvent('Tick');
				}
				
				self::$next_poll = microtime(true) + self::$poll_interval;
			}
		}
	}
}