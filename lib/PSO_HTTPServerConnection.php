<?php
class PSO_HTTPServerConnection extends PSO_TCPServerConnection {
	public $requestMethod;
	public $requestPath;
	public $requestHTTPVersion;
	public $requestHeaders;
	public $requestBody;
	
	public $responseHeaders = array();
	public $responseStatusCode = 200;
	public $responseStatus = 'OK';
	
	protected $sentHeaders = false;
	protected $sentEnding = false;
	
	public function readData() {
		return parent::readData();
	}
	
	public function sendStatus($code) {
		$this->responseStatusCode = $code;
		$this->responseStatus = '??';
	}
	
	public function decodeRequest($data) {
		list($headers, $body) = explode("\r\n\r\n", $data, 2) + array('', '');
		
		if(!$headers) return;
		
		$headers = explode("\r\n", $headers);

		foreach($headers as $key => $header) {
			if(strpos($header, 'HTTP/1.') !== FALSE) {
				list($this->requestMethod, $this->requestPath, $this->requestHTTPVersion) = explode(' ', $header);
				unset($headers[$key]);
			} else {
				list($name, $value) = explode(':', $header, 2);
				$this->requestHeaders[$name] = trim($value);
			}
		}
		
		$this->requestBody = $body;
	}
	
	protected function sendHeaders() {
		if(!$this->responseHeaders && !$this->responseStatusCode) {
			trigger_error('Cannot deliver a HTTP response with no headers!');
		}
		
		$headers = '';
		foreach($this->responseHeaders as $name => $value) {
			$headers .= "{$name}: {$value}\r\n";
		}
		
		$this->sentHeaders = true;
		if(!$this->responseStatus)
			$this->responseStatus = $this->getStatusMessage($this->responseStatusCode);
		
		$this->send("HTTP/1.1 {$this->responseStatusCode} {$this->responseStatus}\r\n");
		$this->send("{$headers}\r\n");
	}
	
	public function getStatusMessage($code) {
		switch ($code) {
			case 100: $text = 'Continue'; break;
			case 101: $text = 'Switching Protocols'; break;
			case 200: $text = 'OK'; break;
			case 201: $text = 'Created'; break;
			case 202: $text = 'Accepted'; break;
			case 203: $text = 'Non-Authoritative Information'; break;
			case 204: $text = 'No Content'; break;
			case 205: $text = 'Reset Content'; break;
			case 206: $text = 'Partial Content'; break;
			case 300: $text = 'Multiple Choices'; break;
			case 301: $text = 'Moved Permanently'; break;
			case 302: $text = 'Moved Temporarily'; break;
			case 303: $text = 'See Other'; break;
			case 304: $text = 'Not Modified'; break;
			case 305: $text = 'Use Proxy'; break;
			case 400: $text = 'Bad Request'; break;
			case 401: $text = 'Unauthorized'; break;
			case 402: $text = 'Payment Required'; break;
			case 403: $text = 'Forbidden'; break;
			case 404: $text = 'Not Found'; break;
			case 405: $text = 'Method Not Allowed'; break;
			case 406: $text = 'Not Acceptable'; break;
			case 407: $text = 'Proxy Authentication Required'; break;
			case 408: $text = 'Request Time-out'; break;
			case 409: $text = 'Conflict'; break;
			case 410: $text = 'Gone'; break;
			case 411: $text = 'Length Required'; break;
			case 412: $text = 'Precondition Failed'; break;
			case 413: $text = 'Request Entity Too Large'; break;
			case 414: $text = 'Request-URI Too Large'; break;
			case 415: $text = 'Unsupported Media Type'; break;
			case 500: $text = 'Internal Server Error'; break;
			case 501: $text = 'Not Implemented'; break;
			case 502: $text = 'Bad Gateway'; break;
			case 503: $text = 'Service Unavailable'; break;
			case 504: $text = 'Gateway Time-out'; break;
			case 505: $text = 'HTTP Version not supported'; break;
			default:
				throw new Exception("Unknown Status Code: {$code}");
		}
		
		return $text;
	}
	
	public function addHeader($name, $value) {
		$this->requestHeaders[$name] = $value;
	}
	
	public function disconnect() {
		if(!$this->sentEnding) {
			$this->send("\r\n\r\n");
			$this->sentEnding = true;
		}
		
		parent::disconnect();
	}
	
	public function send($data) {
		if(!$this->sentHeaders)
			$this->sendHeaders();
		
		parent::send($data);
	}
}