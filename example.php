<?php
include 'PSO.php';

$server = new PSO_TCPServer(8005);

$client = new PSO_TCPClient();
$conn1 = $client->addTarget('localhost', 8005);
$conn2 = $client->addTarget('localhost', 8005);

$server->onData(function($data) {
	echo "Server received: {$data}";
	$this->send("Hi there {$this->clientIP}!\r\n");
});

$client->onData(function($data) {
	echo "Client received: {$data}";
});

$client->broadcast("Hello, World!\r\n");

PSO::drain($server, $client);
