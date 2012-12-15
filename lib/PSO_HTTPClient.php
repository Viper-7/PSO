<?php
class PSO_HTTPClient extends PSO_ClientPool {
	public static $connection_class = 'PSO_HTTPClientConnection';
	
	public $userAgent;
	public $captureRedirects = true;
	public $active = array();
	
	public $requestCount = 0;
	public $retryCount = 0;
	public $statusCount  = array();
	
	protected $concurrency = 1;
	protected $spawnRate   = 12;
	protected $resolveRate = 5;
	protected $connectionsPerIP = 20;
	protected $retryLimit = 0;
	protected $redirectLimit = 1;
	
	protected $fetchBodies = true;
	protected $connectionCache = array();
	protected $dnsCache = array();
	
	public function getStreams() {
		if(!$this->connections && !$this->active) {
			$this->close();
		}
		
		foreach($this->active as $set) {
			foreach($set as $conn) {
				if(!$conn->stream || !is_resource($conn->stream) || feof($conn->stream)) {
					$conn->disconnect();
				}
			}
		}

		$read = $write = $except = array();
		foreach($this->active as $set) {
			foreach($set as $conn) {
				$read[] = $conn->stream;
				$except[] = $conn->stream;
				
				if($conn->hasOutput())
					$write[] = $conn->stream;
			}
		}
		
		return array($read, $write, $except);
	}
	
	public function handleTick() {
		parent::handleTick();

		if(!$this->connections)
			return;
		
		if(array_sum(array_map('count', $this->active)) >= $this->concurrency)
			return;
		
		$resolveCount = 0;
		$counts = array();
		
		foreach($this->connections as $key => $conn) {
			if($resolveCount >= $this->resolveRate)
				return;
			
			if(!$conn->remoteIP) {
				if(isset($this->dnsCache[$conn->remoteHost])) {
					$ip = $this->dnsCache[$conn->remoteHost];
				} else {
					$ip = @gethostbyname($conn->remoteHost);
					$this->dnsCache[$conn->remoteHost] = $ip;
					$resolveCount++;
				}
				
				if($ip == $conn->remoteHost) {
					$this->handleError($conn, 'DNS');
					continue;
				}
				
				$conn->remoteIP = $ip;
			}
			
			if(isset($this->active[$conn->remoteIP]))
				$counts[$key] = count($this->active[$conn->remoteIP]);
			else
				$counts[$key] = 0;
		}

		arsort($counts);
		$spawnCount = 0;
		
		foreach($counts as $key => $count) {
			if($count >= $this->connectionsPerIP)
				continue;
			
			$conn = $this->connections[$key];

			if(!$conn->hasInit) {
				if($this->initalizeConnection($conn)) {
					$spawnCount++;
					if($spawnCount >= $this->spawnRate)
						return;
				}
			}
		}
	}
	
	public function setConcurrency($level) {
		$this->concurrency = $level;
	}
	
	public function setSpawnRate($rate) {
		$this->spawnRate = $rate;
	}
	
	public function addTargets($targets, $onResponse = null) {
		foreach($targets as $target) {
			if(isset($this->connectionCache[$target])) {
				$conn = $this->connectionCache[$target];

				if($onResponse) {
					if($conn->requestComplete) {
						$callback = $onResponse->bindTo($conn, $conn);
						$result = $callback();
					
						if($result != 'unregister')
							$conn->onResponse($onResponse);
					}
				}
				
				$conns[$target] = $conn;
			} else {
				$conn = $this->createConnection($target);
				
				$conns[$target] = $conn;
				
				if($onResponse)
					$conn->onResponse($onResponse);
			}
		}
		
		return $conns;
	}
	
	public function addTarget($target, $onResponse = null) {
		$conns = $this->addTargets(array($target), $onResponse);
		foreach($conns as $conn) { return $conn; }
	}
	
	public function disconnect($conn) {
		if(isset($this->active[$conn->remoteIP])) {
			$key = array_search($conn, $this->active[$conn->remoteIP]);

			if($key !== FALSE)
				unset($this->active[$conn->remoteIP][$key]);
				
			if(empty($this->active[$conn->remoteIP]))
				unset($this->active[$conn->remoteIP]);
		}
		
		parent::disconnect($conn);
	}
	
	protected function createConnection($target, $conn=null) {
		if(!$conn) {
			$class = static::$connection_class;
			$conn = new $class(NULL);
		}
		$conn->pool = $this;
		$conn->requestURI = $target;
		$this->connectionCache[$target] = $conn;

		$conn->contextOptions = array();
		$conn->contextOptions['http']['header'] = array();
		$conn->contextOptions['http']['ignore_errors'] = 1;
		$conn->contextOptions['http']['follow_location'] = intval(!$this->captureRedirects);
		$conn->contextOptions['http']['protocol_version'] = 1.0;
		
		if($this->userAgent)
			$conn->contextOptions['http']['user_agent'] = $this->userAgent;

		$parts = parse_url($conn->requestURI);
		$host = isset($parts['host']) ? $parts['host'] : '';
		$conn->remoteHost = $host;
		$conn->requestHeaders['Host'] = $host;
 
		$this->raiseEvent('Queue', array(), NULL, $conn);
		$conn->raiseEvent('Queue');

		$this->connections[] = $conn;
		
		return $conn;
	}
	
	protected function initalizeConnection($conn) {
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

		$conn->requestHeaders['Content-Length'] = strlen($conn->requestBody);

		$parts = parse_url($conn->requestURI);
		$parts['host'] = $conn->remoteIP;
		$url = $conn->packURL($parts);

		$context = stream_context_create($conn->contextOptions);

		$stream = fopen($url, 'r', false, $context);
		
		if(!$stream) {
			$this->handleError($conn, 'Socket');
			return $conn;
		}
		
		$conn->stream = $stream;
		
		$this->requestCount += 1;
		$conn->hasInit = true;
		$this->active[$conn->remoteIP][] = $conn;
		
		$this->raiseEvent('Connect', array(), NULL, $conn);
		$conn->raiseEvent('Connect');

		return $conn;
	}
		
	public function handleHead($conn) {
		$this->raiseEvent('Headers', array(), NULL, $conn);
		$conn->raiseEvent('Headers');
		
		if(!$this->fetchBodies) {
			$conn->disconnect();
		}
	}
	
	public function handleResponse($conn) {
		$this->raiseEvent('Response', array(), NULL, $conn);
		$conn->raiseEvent('Response');
		$conn->disconnect();
	}
	
	public function handlePartial($conn) {
		$this->raiseEvent('Partial', array(), NULL, $conn);
		$conn->raiseEvent('Partial');
	}

	public function handleRedirect($conn) {
		if($conn->redirectCount < $this->redirectLimit) {
			$conn->requestURI = $conn->getMediaURL($conn->responseHeaders['Location']);

			$this->raiseEvent('Redirect', array($conn->requestURI), NULL, $conn);
			$conn->raiseEvent('Redirect', array($conn->requestURI));
			
			$conn->redirectCount += 1;
			$this->restartConnection($conn);
		} else {
			$this->raiseEvent('Error', array('Redirect'), NULL, $conn);
			$conn->raiseEvent('Error', array('Redirect'));
			$conn->disconnect();
		}
	}
	
	public function restartConnection($conn) {
		$url = $conn->requestURI;
		$conn->disconnect(true);
		$conn->responseHeaders = array();
		$conn->rawResponse = '';
		$conn->requestComplete = false;
		$conn->hasInit = false;
		$this->createConnection($url, $conn);
	}
	
	public function handleError($conn, $status=null) {
		if(!$status)
			$status = $conn->responseStatusCode;
		
		if(!isset($this->statusCount[$status]))
			$this->statusCount[$status] = 0;
		
		$this->statusCount[$status] += 1;
		
		if($conn->errorCount >= $this->retryLimit) {
			$this->raiseEvent('Error', array($status), NULL, $conn);
			$conn->raiseEvent('Error', array($status));
			$conn->disconnect();
		} else {
			$url = $conn->requestURI;
			$conn->errorCount += 1;
			$this->retryCount += 1;
			
			$this->raiseEvent('Retry', array($status), NULL, $conn);
			$conn->raiseEvent('Retry', array($status));

			$this->restartConnection($conn);
		}
	}
}
