<?php
class PSO_HTTPClient extends PSO_ClientPool {
	public static $connection_class = 'PSO_HTTPClientConnection';
	
	protected $concurrency = 1;
	protected $queue = array();
	
	public function setConcurrency($level) {
		$this->concurrency = $level;
	}
	
	public function addTargets($targets) {
		$this->queue = array_merge($this->queue, $targets);
		$this->spawnConnections();
	}
	
	public function disconnect($conn) {
		parent::disconnect($conn);
		$this->spawnConnections();
	}
	
	protected function spawnConnections() {
		$count = $this->concurrency - count($this->connections);
		$class = static::$connection_class;
		
		while($count && $this->queue) {
			$target = array_unshift($this->queue);
			
			// Complex Targets?
			$stream = fopen($target, 'r');
			$conn = new $class($stream);
			$conn->setRequestURL($target);
			
			$this->connections[] = $conn;
			$count--;
		}
	}
	
	public function getQueueSize() {
		return count($this->queue);
	}
}