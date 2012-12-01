<?php
class PSO_HTTPClientConnection extends PSO_ClientConnection {
	public $requestURI;
	public $contextOptions = array();

	public $requestMethod = 'GET';
	public $requestHeaders = array();
	public $requestBody;
	
	public $responseHeaders = array();
	public $responseHTTPVersion;
	public $responseStatusCode;
	public $responseStatus;
	public $responseBody = '';
	
	public $hasInit = false;

	public function getDOM() { 
		if(isset($this->dom))
			return $this->dom;
		
		libxml_clear_errors();
		$olderr = libxml_use_internal_errors(true);
		$dom = new DOMDocument();
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
			$this->responseBody .= fread($this->stream, 4096);
			unset($this->dom);
			
			$this->pool->handlePartial($this);
			
			if($this->stream && feof($this->stream)) { 
				$this->pool->handleResponse($this);
				$meta = stream_get_meta_data($this->stream);
				
				return implode("\r\n", $meta['wrapper_data']) . "\r\n" . $this->responseBody;
			} else {
				return;
			}
		}
		
		$meta = stream_get_meta_data($this->stream);
		
		$headers = $meta['wrapper_data'];

		foreach($headers as $header) {
			if(substr($header, 0, 5) == 'HTTP/') {
				$status = $header;
				continue;
			}
			
			list($name, $value) = explode(':', $header, 2) + array('', '');
			
			$this->responseHeaders[$name] = trim($value);
		}
		
		list($this->responseHTTPVersion, $this->responseStatusCode, $this->responseStatus) = explode(' ', $status);
		
		if($this->responseStatusCode > 199 && $this->responseStatusCode < 300) {
			$this->pool->handleHead($this);
		} elseif($this->responseStatusCode > 299 && $this->responseStatusCode < 400) {
			$this->pool->handleRedirect($this);
		} else {
			$this->pool->handleError($this);
		}
	}

	public function joinURL($base, $added) {
		return $this->pool->joinURL($base, $added);
	}
}