<?php
class PSO_HTTPClient extends PSO_ClientPool {
	public static $connection_class = 'PSO_HTTPClientConnection';
	
	protected $concurrency = 10;
	protected $fetchBodies = true;
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
		
		$options['http']['ignore_errors'] = 1;
		$context = stream_context_create($options);
		
		while($count && $this->queue) {
			$target = array_shift($this->queue);
			
			// Complex Targets?
			$stream = fopen($target, 'r', false, $context);
			
			$conn = new $class($stream);
			$conn->requestURI = $target;
			$this->addConnection($conn);
			$count--;
		}
		
		if(empty($this->queue) && empty($this->connections)) {
			$this->close();
		}
	}
	
	public function getQueueSize() {
		return count($this->queue);
	}
	
	public function handleHead($conn) {
		$this->raiseEvent('Headers', array(), NULL, $conn);
		$conn->raiseEvent('Headers');
		
		if(!$this->fetchBodies) {
			$this->disconnect($conn);
		}
	}
	
	public function handleResponse($conn) {
		$this->raiseEvent('Response', array(), NULL, $conn);
		$conn->raiseEvent('Response');
		$this->disconnect($conn);
	}
	
	public function handlePartial($conn) {
		$this->raiseEvent('Partial', array(), NULL, $conn);
		$conn->raiseEvent('Partial');
	}

	public function handleRedirect($conn) {
		var_dump("Should not see me");
	}
	
	public function handleError($conn) {
		$this->raiseEvent('Error', array(), NULL, $conn);
		$conn->raiseEvent('Error');
		$this->disconnect($conn);
	}
}