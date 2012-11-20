<?php
include 'PSO.php';

$server = new PSO_TCPServer(8005);

$server->onConnect(function() {
	$this->send("Hello {$this->clientIP}, How are you today?\r\n");
});

$server->onData(function($data) {
	switch(trim(strtolower($data))) {
		case 'good':
			if(empty($this->askedAboutYesterday)) {
				$this->send("Oh thats good! How about yesterday then?\r\n");
				$this->askedAboutYesterday = true;
			} else {
				$this->send("Great! How about the day before that?\r\n");
			}
			break;
		case 'bad':
			$this->send("Sounds depressing, go away!\r\n");
			$this->disconnect();
			break;
	}
});

PSO::drain($server);
