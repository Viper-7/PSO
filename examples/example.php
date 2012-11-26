<?php
include '../PSO.php';

$server = new PSO_TCPServer(8005);

$server->onData(function($data) {
	$this->send($data);
});

PSO::drain($server);
