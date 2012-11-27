<?php
include '../PSO.php';

$server = new PSO_FD();

$stdin = $server->open(0);
$stdout = $server->open(1);
$stderr = $server->open(2);

$stdin->onData(function($data) use ($stdout) {
	$stdout->send($data);
});

PSO::drain($server);
