<?php
class PSO_HTTPServerConnection extends PSO_TCPServerConnection {
	public $requestMethod;
	public $requestPath;
	public $requestHTTPVersion;
	public $requestHeaders;
	public $requestBody;
	
	public $responseHeaders = array();
	
	protected $sentHeaders = false;
	
	public function readData() {
		$data = parent::readData();
		$this->decodeRequest($data);
	}
	
	protected function decodeRequest($data) {
		list($headers, $body) = explode("\r\n\r\n", $data, 2) + array('', '');
		
		$headers = explode("\r\n", $headers);
		$status = array_shift($headers);
		list($this->requestMethod, $this->requestPath, $this->requestHTTPVersion) = explode(' ', $status);

		foreach($headers as $header) {
			list($name, $value) = explode(':', $header, 2);
			
			$this->requestHeaders[$name] = trim($value);
		}
		
		$this->requestBody = $body;
	}
	
	protected function sendHeaders() {
		if(!$this->responseHeaders) {
			trigger_error('Cannot deliver a HTTP response with no headers!');
		}
		
		$headers = '';
		foreach($this->responseHeaders as $name => $value) {
			$headers .= "{$name}: {$value}\r\n";
		}
		
		$this->send("{$headers}\r\n");
		$this->sentHeaders = true;
	}
	
	public function addHeader($name, $value) {
		$this->requestHeaders[$name] = $value;
	}
	
	public function send($data) {
		if(!$this->sentHeaders)
			$this->sendHeaders();
		
		parent::send($data);
	}
}