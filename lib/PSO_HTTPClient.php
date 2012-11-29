<?php
class PSO_HTTPClient extends PSO_ClientPool {
	public static $connection_class = 'PSO_HTTPClientConnection';
	
	public $userAgent;
	public $captureRedirects = false;
	
	protected $concurrency = 10;
	protected $fetchBodies = true;
	protected $queue = array();
	
	public function getStreams() {
		$this->spawnConnections();
		
		foreach($this->connections as $conn) {
			if(!$conn->hasInit) {
				$this->initalizeConnection($conn);
			}
		}
		
		return parent::getStreams();
	}
	
	public function setConcurrency($level) {
		$this->concurrency = $level;
	}
	
	public function addTargets($targets) {
		$this->queue = array_merge($this->queue, $targets);
	}
	
	public function addTarget($target) {
		return $this->createConnection($target);
	}
	
	public function disconnect($conn) {
		parent::disconnect($conn);
		$this->spawnConnections();
	}
	
	protected function createConnection($target) {
		$class = static::$connection_class;
		$conn = new $class(NULL);
		
		$conn->requestURI = $target;
		
		$conn->contextOptions = array();
		$conn->contextOptions['http']['header'] = array();
		$conn->contextOptions['http']['ignore_errors'] = 1;
		$conn->contextOptions['http']['follow_location'] = intval(!$this->captureRedirects);
		
		if($this->userAgent)
			$conn->contextOptions['http']['user_agent'] = $this->userAgent;
		
		$this->raiseEvent('BeforeSpawn', array(), NULL, $conn);

		if(!isset($conn->contextOptions['http']['method']))
			$conn->contextOptions['http']['method'] = $conn->requestMethod;
		else
			$conn->requestMethod = $conn->contextOptions['http']['method'];
		
		if(is_string($conn->requestHeaders))
			$conn->requestHeaders = explode("\r\n", $conn->requestHeaders);
		
		foreach($conn->requestHeaders as $key => $value) {
			if(is_int($key)) {
				$conn->contextOptions['http']['header'][] = $value;
			} else {
				$conn->contextOptions['http']['header'][] = "$key: $value";
			}
		}
		
		$conn->requestHeaders = array();
		foreach($conn->contextOptions['http']['header'] as $header) {
			list($key, $value) = explode(':', $header, 2) + array('','');
			$conn->requestHeaders[$key] = $value;
		}
		
		if(!isset($conn->contextOptions['http']['content']))
			$conn->contextOptions['http']['content'] = $conn->requestBody;
		else
			$conn->requestBody = $conn->contextOptions['http']['content'];
		
		$this->connections[$conn->requestURI] = $conn;
		
		return $conn;
	}
	
	protected function initalizeConnection($conn) {
		$context = stream_context_create($conn->contextOptions);
		$stream = @fopen($conn->requestURI, 'r', false, $context);

		$conn->stream = $stream;
		$conn->pool = $this;
		$conn->hasInit = true;
		
		$this->raiseEvent('Connect', array(), NULL, $conn);
	}
	
	protected function spawnConnections() {
		$count = $this->concurrency - count($this->connections);
		
		while($this->queue && $count--) {
			$conn = $this->createConnection(array_shift($this->queue));
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
		$url = $conn->responseHeaders['Location'];
		
		$this->raiseEvent('Redirect', array($url), NULL, $conn);
		$conn->raiseEvent('Redirect', array($url));
		
		$this->addTargets(array($url));
		$this->disconnect($conn);
	}
	
	public function handleError($conn) {
		$this->raiseEvent('Error', array(), NULL, $conn);
		$conn->raiseEvent('Error');
		$this->disconnect($conn);
	}
}