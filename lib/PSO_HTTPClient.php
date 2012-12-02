<?php
class PSO_HTTPClient extends PSO_ClientPool {
	public static $connection_class = 'PSO_HTTPClientConnection';
	
	public $userAgent;
	public $captureRedirects = false;
	public $active = array();
	
	public $requestCount = 0;
	public $statusCount  = array();
	
	protected $concurrency = 100;
	protected $spawnRate   = 10;
	protected $connectionsPerIP = 5;
	protected $fetchBodies = true;
	protected $connectionCache = array();
	
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
		$count = min($this->concurrency, count($this->connections)) - array_sum(array_map('count', $this->active));
		$this->spawnCount = 0;
		$active = $this->active;
		
		usort($this->connections, function($a, $b) use ($active) {
			$ac = isset($active[$a->remoteIP]) ? count($active[$a->remoteIP]) : 0;
			$bc = isset($active[$b->remoteIP]) ? count($active[$b->remoteIP]) : 0;
			if($ac == $bc) return 0;
			return $ac < $bc ? -1 : 1;
		});
		
		foreach($this->connections as $conn) {
			if($this->spawnCount >= $this->spawnRate) break;
			if(!$count) break;
			
			$ipcount = 0;
			if(isset($this->active[$conn->remoteIP])) {
				$ipcount = count($this->active[$conn->remoteIP]);
			}
			
			if(!$conn->hasInit && $ipcount < $this->connectionsPerIP) {
				if($this->initalizeConnection($conn)) {
					$count--;
					$this->spawnCount += 1;
				}
			}
		}
		
		parent::handleTick();
	}
	
	public function setConcurrency($level) {
		$this->concurrency = $level;
	}
	
	public function setSpawnRate($rate) {
		$this->spawnRate = $rate;
	}
	
	public function addTargets($targets, $onResponse=null) {
		foreach($targets as $target) {
			if(isset($this->connectionCache[$target])) {
				$conn = $this->connectionCache[$target];
				$conn->onResponse($onResponse);
				if($conn->requestComplete) 
					$conn->raiseEvent('Response');
				$conns[$target] = $conn;
			} else {
				$conn = $this->createConnection($target);
				$conns[$target] = $conn;
				$conn->onResponse($onResponse);
			}
		}
		
		return $conns;
	}
	
	public function addTarget($target) {
		return $this->createConnection($target);
	}
	
	public function disconnect($conn) {
		$key = array_search($conn, $this->active[$conn->remoteIP]);

		if($key !== FALSE)
			unset($this->active[$conn->remoteIP][$key]);
			
		if(empty($this->active[$conn->remoteIP]))
			unset($this->active[$conn->remoteIP]);
		
		parent::disconnect($conn);
	}
	
	protected function createConnection($target, $conn=null) {
		if(!$conn) {
			$class = static::$connection_class;
			$conn = new $class(NULL);
		}
		
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
		
		$parts = parse_url($conn->requestURI);
		$host = isset($parts['host']) ? $parts['host'] : '';
		
		$ip = gethostbyname($host);
		$conn->remoteIP = $ip;
		
		$conn->requestHeaders['Host'] = $host;
		$conn->requestHeaders['User-Agent'] = $this->userAgent;

		$this->connections[] = $conn;
		
		return $conn;
	}
	
	protected function initalizeConnection($conn) {
		$parts = parse_url($conn->requestURI);
		$host = $parts['host'];

		$url = "tcp://{$conn->remoteIP}:80";
		$context = stream_context_create($conn->contextOptions);
		$stream = stream_socket_client($url, $errno, $errstr, ini_get('default_socket_timeout'), STREAM_CLIENT_CONNECT, $context);
		
		if(!$stream) {
			echo "Failed to connect to {$url} for {$conn->requestURI}\r\n";
			$this->handleError($conn, 'unknown');
			return $conn;
		}
		stream_set_read_buffer($stream, 8192);
		$conn->stream = $stream;

		unset($parts['scheme'], $parts['host']);
		$url = $this->packURL($parts);
		$url = str_replace(' ', '%20', $url);
		
		$conn->send("{$conn->requestMethod} {$url} HTTP/1.0\r\n");

		$conn->requestHeaders['Content-Length'] = strlen($conn->requestBody);
		
		foreach($conn->requestHeaders as $header => $line) {
			if(!is_array($line))
				$line = array($line);
			
			foreach($line as $value) {
				$value = trim($value);
				$conn->send("{$header}: {$value}\r\n");
			}
		}
		
		$conn->send("\r\n");
		
		$conn->send($conn->requestBody . "\r\n");
		
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
		$url = $this->joinURL($conn->requestURI, $conn->responseHeaders['Location']);
		
		$this->raiseEvent('Redirect', array($url), NULL, $conn);
		$conn->raiseEvent('Redirect', array($url));
		
		$this->disconnect($conn);
		$conn->hasInit = false;
		$this->createConnection($url, $conn);
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
	
		return $this->packURL($parsed_url);
	}

	function packURL($parsed_url) {
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
