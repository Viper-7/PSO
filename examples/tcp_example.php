<?php
include '../PSO.php';

$server = new PSO_TCPServer(8005);

$server->onConnect(function() {
	$this->send("Hello {$this->clientIP}, How are you today?\r\n");
});

$server->onData(function($data) {
	echo $data;
	
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
			$this->close();
			break;
	}
});



$client = new PSO_TCPClient('127.0.0.1', 8005);

$client->onData(function($data) {
	echo $data;
	
	if(strpos($data, 'How are you today?') !== FALSE) {
		$this->send("good\r\n");
		
	} elseif(strpos($data, 'How about yesterday then?') !== FALSE) {
		$this->send("good\r\n");
		
	} elseif(strpos($data, 'How about the day before that?') !== FALSE) {
		$this->send("bad\r\n");
		
	} else {
		$this->send("fine.\r\n");
		$this->disconnect();
	}
});


PSO::drain($server, $client);
