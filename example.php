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


$conn1 = $clientPool->addTarget('localhost', '8004');
$conn1->send('test1');


PSO::drain($pool, $clientPool);
