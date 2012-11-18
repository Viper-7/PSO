<?php
include 'PSO.php';

$pool = new PSO_TCPServerPool();
$pool->openPort(8004);
$pool->onData(function($data) {
	$this->pool->broadcast($data);
});


$clientPool = new PSO_TCPClientPool();

$clientPool->onData(function($data) {
	var_dump($data);
});

$clientPool->onTick(function() { 
	$this->broadcast(microtime(true));
});

$conn1 = $clientPool->addTarget('localhost', '8004');


PSO::drain($pool, $clientPool);
