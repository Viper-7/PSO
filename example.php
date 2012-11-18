<?php
include 'PSO.php';

$pool = new PSO_TCPServerPool();
$pool->openPort(8004);
$pool->onData(function($data) {
	$this->send($data);
});


$clientPool = new PSO_TCPClientPool();
$clientPool->addTarget('localhost', '8004');
$clientPool->onData(function($data) {
	var_dump($data);
});
$clientPool->onTick(function() { 
	$this->broadcast(microtime(true));
});



PSO::drain($pool, $clientPool);
