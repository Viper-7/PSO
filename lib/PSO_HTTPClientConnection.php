<?php
class PSO_HTTPClientConnection extends PSO_ClientConnection {
	public $requestURI;
	public $contextOptions = array();
	
	public $remoteHost;
	public $remoteIP;

	public $requestMethod = 'GET';
	public $requestHeaders = array(
		'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
		'Connection' => 'close',
		'Accept-Language' => 'en-US,en;q=0.6',
		'Accept-Encoding' => 'plain',
		'Pragma' => 'no-cache',
		'Cache-Control' => 'no-cache',
	);
	
	public $requestBody;
	
	public $responseHeaders = array();
	public $responseHTTPVersion;
	public $responseStatusCode;
	public $responseStatus;
	public $responseBody = NULL;
	
	public $errorCount = 0;
	public $redirectCount = 0;
	
	public $hasInit = false;
	public $headersSent = false;
	
	public $requestComplete = false;
	public $rawResponse = '';

	public function getDOM() { 
		if(isset($this->dom))
			return $this->dom;
		
		libxml_clear_errors();
		$olderr = libxml_use_internal_errors(true);
		$dom = new DOMDocument();

		if(!trim($this->responseBody))
			return $dom;
		
		$dom->loadHTML($this->responseBody);
		$errors = libxml_get_errors();
		libxml_use_internal_errors($olderr);
		
		$this->dom = $dom;
		$this->htmlErrors = $errors;
		
		return $dom;
	}
	
	public function setPostVars($data) {
		if($this->requestBody) {
			trigger_error('Cannot send post vars in a request that already has a body!');
		}
		
		$this->requestMethod = 'POST';
		$this->requestBody = http_build_query($data);
	}
	
	public function setCookie($name, $value, $expires=null, $path=null, $domain=null, $secure=null) {
		$parts = array();
		if(!is_null($expires) && is_int($expires)) {
			$expires = date('r', $expires);
		}
		
		$vars = array('expires', 'path', 'domain', 'secure');
		foreach($vars as $var) {
			if(!is_null($$var))
				$parts[] = $var . '=' . $$var;
		}
		
		//Set-Cookie: value[; expires=date][; domain=domain][; path=path][; secure]
		$this->requestHeaders['Set-Cookie'][] = "{$name}={$value}";
	}

	public function readData() {
		if(!empty($this->responseHeaders)) {
			$content = fread($this->stream, 4096);
			$this->rawResponse .= $content;
			$this->responseBody .= $content;
			unset($this->dom);
			
			$this->pool->handlePartial($this);
			
			if($this->stream && feof($this->stream)) { 
				$this->requestComplete = true;
				$this->pool->handleResponse($this);

				return $this->rawResponse;
			} else {
				return;
			}
		}
		
		$this->rawResponse .= fread($this->stream, 1024);
		$content = explode("\r\n\r\n", $this->rawResponse, 2);
		
		if(!isset($content[1]))
			return;
		
		$headers = explode("\r\n", $content[0]);
		$this->responseBody = $content[1];
		
		list($this->responseHTTPVersion, $this->responseStatusCode, $this->responseStatus) = explode(' ', array_shift($headers), 3);

		foreach($headers as $header) {
			list($name, $value) = explode(':', $header, 2) + array('', '');
			
			$this->responseHeaders[$name] = trim($value);
		}
		
		if($this->responseStatusCode > 199 && $this->responseStatusCode < 300) {
			$this->pool->handleHead($this);
		} elseif($this->responseStatusCode > 299 && $this->responseStatusCode < 400) {
			$this->pool->handleRedirect($this);
		} else {
			$this->pool->handleError($this);
		}
	}
	
	public function getMediaURL($added, $base=null) {
		if(is_null($base)) {
			$base = $this->requestURI;
			
			$dom = $this->getDOM();
			
			if($dom) {
				foreach($dom->getElementsByTagName('base') as $basetag) {
					if($newbase = $basetag->getAttribute('href')) {
						$base = $newbase;
					}
				}
			}
		}
		
		$base = parse_url($base);
		$added = parse_url($added);

		if(isset($base['fragment']))
			unset($base['fragment']);
		
		if(isset($base['query']) && isset($added['path']) && !isset($added['query']))
			unset($base['query']);
		
		if(isset($added['path']) && $added['path'][0] == '/')
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