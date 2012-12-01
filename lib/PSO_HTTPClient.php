<?php
class PSO_HTTPClient extends PSO_ClientPool {
	public static $connection_class = 'PSO_HTTPClientConnection';
	
	public $userAgent;
	public $captureRedirects = false;
	public $active = array();
	
	public $requestCount = 0;
	public $statusCount  = array();
	
	protected $concurrency = 20;
	protected $spawnRate   = 1;
	protected $fetchBodies = true;
	
	public function getStreams() {
		if(!$this->connections && !$this->active) {
			$this->close();
		}
		
		$count = min($this->spawnRate, $this->concurrency, count($this->connections)) - count($this->active);
		
		foreach($this->connections as $conn) {
			if(!$count) break;

			if(!$conn->hasInit) {
				if($this->initalizeConnection($conn)) {
					$count--;
				}
			}
		}

		foreach($this->active as $conn) {
			if(!$conn->stream || !is_resource($conn->stream)) {
				$conn->disconnect();
			}
		}

		$read = $write = $except = array();
		foreach($this->active as $conn) {
			$read[] = $conn->stream;
			$except[] = $conn->stream;
			
			if($conn->hasOutput())
				$write[] = $conn->stream;
		}
		
		return array($read, $write, $except);
	}
	
	public function setConcurrency($level) {
		$this->concurrency = $level;
	}
	
	public function setSpawnRate($rate) {
		$this->spawnRate = $rate;
	}
	
	public function addTargets($targets, $onResponse=null) {
		foreach($targets as $target) {
			$conn = $this->createConnection($target);
			$conns[$target] = $conn;
			if(is_callable($onResponse)) {
				$conn->onResponse($onResponse);
			}
		}
		
		return $conns;
	}
	
	public function addTarget($target) {
		return $this->createConnection($target);
	}
	
	public function disconnect($conn) {
		$key = array_search($conn, $this->active);
		
		if($key !== FALSE)
			unset($this->active[$key]);
		
		parent::disconnect($conn);
	}
	
	protected function createConnection($target) {
		$class = static::$connection_class;
		$conn = new $class(NULL);
		$conn->pool = $this;
		
		$conn->requestURI = $target;
		
		$conn->contextOptions = array();
		$conn->contextOptions['http']['header'] = array();
		$conn->contextOptions['http']['ignore_errors'] = 1;
		$conn->contextOptions['http']['follow_location'] = intval(!$this->captureRedirects);
		
		if($this->userAgent)
			$conn->contextOptions['http']['user_agent'] = $this->userAgent;

		$this->raiseEvent('Queue', array(), NULL, $conn);
		$conn->raiseEvent('Queue');

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
		
		$this->connections[] = $conn;
		
		return $conn;
	}
	
	protected function initalizeConnection($conn) {
		$context = stream_context_create($conn->contextOptions);
		$url = 'tcp' . substr($conn->requestURI, 3);
		$stream = @fopen($url, 'r', false, $context);
		
		$this->requestCount += 1;
		
		if(!$stream) {
			return $this->handleError($conn, 'unknown');
		}

		$conn->stream = $stream;
		$conn->hasInit = true;
		
		$this->active[] = $conn;
		
		$this->raiseEvent('Connect', array(), NULL, $conn);
		$conn->raiseEvent('Connect');
		
		return $stream;
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
	
	public function handleError($conn, $status=null) {
		if(!$status)
			$status = $conn->responseStatusCode;
		
		if(!isset($this->statusCount[$status]))
			$this->statusCount[$status] = 0;
		
		$this->statusCount[$status] += 1;
		
		$this->raiseEvent('Error', array(), NULL, $conn);
		$conn->raiseEvent('Error');
		$this->disconnect($conn);
	}
	
	public function joinURL($base, $added) {
		$base = parse_url($base);
		$added = parse_url($added);

		if(isset($base['fragment']))
			unset($base['fragment']);
		
		if(isset($base['query']) && isset($added['path']) && !isset($added['query']))
			unset($base['query']);
		
		if($added['path'][0] == '/')
			unset($base['path']);
		
		$parsed_url = $added + $base;
		
		if(isset($base['path']) && isset($added['path'])) {
			$parsed_url['path'] = rtrim($base['path'], '/') . '/' . ltrim($added['path'], '/');
		}

		$scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
		$host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
		$port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
		$user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
		$pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
		$pass     = ($user || $pass) ? "$pass@" : '';
		$path     = isset($parsed_url['path']) ? '/' . ltrim($parsed_url['path'], '/') : '';
		$query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
		$fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
		return "$scheme$user$pass$host$port$path$query$fragment";
	} 
}
