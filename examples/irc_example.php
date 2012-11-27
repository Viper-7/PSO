<?php
include '../PSO.php';

$pool = new PSO_IRCClient('v7PSObot');
$server = $pool->addServer('chat.freenode.org');

$server->onConnected(function() {
	$this->joinChannel("#v7test");
});


$server->onMessage(function($data, $user) {
	$this->send("Hi, {$user->nick}! You said \"{$data}\" in {$this->name}!");
});

$server->onPrivateMessage(function($data) {
	$this->send("Hi, {$this->nick}! You said \"{$data}\" in private!");
});


$server->onJoined(function() {
	$this->send("Hi Everybody!");
});

$server->onKicked(function($channel, $reason, $user) {
	$this->joinChannel($channel->name);
});


PSO::drain($pool);