<?php
include '../PSO.php';

$server = new PSO_HTTPServer(8000);

$server->onRequest('/date', function() {
	$this->responseHeaders['Content-Type'] = 'text/plain';
	$this->send(date('r'));
	$this->disconnect();
});

$server->onRequest('/', function() {
	$this->responseHeaders['Content-Type'] = 'text/plain';
	$this->send("Hello, World!");
	$this->disconnect();
});

$server->onMissingRequest(function() {
	$this->sendStatus(404);
	$this->send("Page not found");
	$this->disconnect();
});

PSO::drain($server);
