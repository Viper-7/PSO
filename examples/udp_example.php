<?php
include '../PSO.php';

$server = new PSO_UDPServer(8008);

$server->onData(function($data) {
	echo "S: $data\n";
	$this->send($data);
});

$client = new PSO_UDPClient('127.0.0.1', 8008);

$client->onData(function($data) {
	echo "C: $data\n";
	$this->send($data);
});

$client->send("test");

PSO::drain($server, $client);
