<?php
include '../PSO.php';

$pool = new PSO_Process();

$pool->onData(function($data) {
	var_dump($data);
});

$pool->onTick(function() {
	$this->send("\r\n");
});

$proc = $pool->open("time");

PSO::drain($pool);
