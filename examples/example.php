<?php
include '../PSO.php';

$server = new PSO_TCPServer(8005);

$server->onData(function($data) {
	echo "S: $data";
	$this->send($data);
});



$client = new PSO_TCPClient('127.0.0.1', 8005);

$client->onData(function($data) {
	echo "C: $data";
	$this->send($data);
});

$client->send("test\r\n");

PSO::drain($server, $client);
