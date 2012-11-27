<?php
include '../PSO.php';

$stdio = new PSO_STDIO();
$irc = new PSO_IRCClient('V7_PSO');
$server = $irc->addServer('chat.freenode.org');

$server->onConnected(function() {
	$this->joinChannel("#phpc");
});

$server->onMessage(function($data, $user) use ($stdio) {
	$stdio->send("{$user->nick}: {$data}\n");
});

$stdio->onData(function($data) use ($server) {
	$server->sendChannel("#phpc", $data);
});

PSO::drain($stdio, $irc);