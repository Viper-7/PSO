<?php
include '../PSO.php';

$stdio = new PSO_STDIO();

$stdio->onData(function($data) {
	$this->send($data);
});

PSO::drain($stdio);
