<?php
class PSO_HTTPClientConnection extends PSO_TCPClientConnection {
	public $requestMethod;
	public $requestPath;
	public $requestHTTPVersion;
	public $requestHeaders;
	public $requestBody;
	
	public $responseHeaders = array();

	public function readData() {
		$data = parent::readData();
		list($headers, $body) = explode("\r\n\r\n", $data, 2);
		
		$headers = explode("\r\n", $headers);
		$status = array_shift($headers);
		list($this->responseStatusCode, $this->responseStatus) = explode(' ', $status);

		foreach($headers as $header) {
			list($name, $value) = explode(':', $header, 2);
			
			$this->headers[$name] = trim($value);
		}
		
		$this->body = $body;
	}
}