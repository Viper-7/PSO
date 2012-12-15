<?php
class PSO_IRCClientUser {
	use PSO_EventProvider;
	
	public $nick;
	public $ident;
	public $hostname;
	public $channels = array();
	public $connection;
	
	public function __construct($nick, $ident='', $hostname='') {
		$this->nick = $nick;
		$this->ident = $ident;
		$this->hostname = $hostname;
	}
	
	public function send($message) {
		$this->connection->sendPrivate($this->nick, $message);
	}
	
	public function sendCTCP($message) {
		$this->send("\001{$message}\001");
	}
	
	public function sendFile($path, $filename = NULL) {
		if(is_null($filename))
			$filename = basename($path);
		
		$port = mt_rand(1024, 2048);
		$pool = new PSO_TCPServer($port);
		$pool->onConnect(function() use ($path) {
			$this->send(file_get_contents($path));
			$this->disconnect();
		});
		$pool->onDisconnect(function() {
			$this->close();
		});
		
		PSO::addPool($pool);
		
		$ip = ip2long(trim(file_get_contents('http://automation.whatismyip.com/n09230945.asp')));
		$filename = str_replace('"', '', $filename);
		$filesize = filesize($path);
		$this->sendCTCP("DCC SEND \"{$filename}\" {$ip} {$port} {$filesize}");
	}
}

class PSO_IRCClientChannel {
	use PSO_EventProvider;
	
	public $users = array();
	public $name;
	public $connection;
	
	public function __construct($name) {
		$this->name = $name;
	}
	
	public function send($message) {
		$this->connection->sendChannel($this->name, $message);
	}
	
	public function addUser($user) {
		$this->users[$user->nick] = $user;
		$user->channels[$this->name] = $this;
	}
	
	public function removeUser($user) {
		unset($this->users[$user->nick]);
		unset($user->channels[$this->name]);
	}
}

class PSO_IRCClientConnection extends PSO_ClientConnection {
	public $users;	// sendCTCP($msg)
	public $channels;
	public $nick;
	
	protected $lastPing;
	protected $authenticated;
	
	public function readData() {
		$data = fgets($this->stream);
		$this->processIncoming($data);
		return $data;
	}
	
	public function sendPrivate($nick, $message) {
		$message = trim($message);
		$this->send("PRIVMSG {$nick} :{$message}\n");
	}
	
	public function sendChannel($channel, $message) {
		$message = trim($message);
		$this->send("PRIVMSG {$channel} :{$message}\n");
	}
	
	public function getUserByNick($nick) {
		$nick = trim($nick, '@$+%');
		
		if(!isset($this->users[$nick])) {
			$user = new PSO_IRCClientUser($nick);
			$user->connection = $this;
			$this->users[$nick] = $user;
		}
		
		return $this->users[$nick];
	}
	
	public function getUserByHost($host) {
		list($nick, $mode, $ident, $hostname) = $this->decodeHostname($host);
		
		if(!isset($this->users[$nick])) {
			$user = new PSO_IRCClientUser($nick, $ident, $hostname);
			$user->connection = $this;
			$this->users[$nick] = $user;
		} elseif(!empty($hostname) && empty($this->users[$nick]->hostname)) {
			$this->users[$nick]->hostname = $hostname;
			$this->users[$nick]->ident = $ident;
		}
		
		return $this->users[$nick];
	}
	
	public function decodeHostname($host) {
		if(preg_match('/^(.+?)!(.+?)@(.+)$/', $host, $matches)) {
			$mode = '';
			
			$char = substr($matches[1],0,1);
			if(in_array($char, array('@', '+', '%')))
				$mode = $char;
			
			return array(trim($matches[1], '@$+%'), $mode, $matches[2], $matches[3]);
		}
		
		return array($host, '', '', '');
	}
	
	public function joinChannel($channel) {
		if(!$this->getChannel($channel)) {
			$obj = new PSO_IRCClientChannel($channel);
			$this->addChannel($obj);
		}
		
		$this->send("JOIN {$channel}\n");
		
		return $this->getChannel($channel);
	}
	
	public function addChannel($channel) {
		$this->channels[$channel->name] = $channel;
		$channel->connection = $this;
	}
	
	public function partChannel($channel) {
		$channel->removeUser($this->getUserByNick($this->nick));
		unset($this->channels[$channel->name]);
	}
	
	public function getChannel($channel) {
		if(isset($this->channels[$channel])) {
			return $this->channels[$channel];
		}
	}

	public function processIncoming($message) {
		$this->lastping = time();
		$parts = explode(' ', trim($message,": \r\n")) + array('', '');
		$ctcp = false;
		
		switch($parts[0]) {
			case 'NOTICE':
				if(!$this->authenticated && $parts[1] == 'AUTH') {
					$this->send("NICK {$this->nick}\n");
					$this->send("USER {$this->nick} localhost {$this->remoteHost} :{$this->nick}\n");
					$this->authenticated = TRUE;
				}

				break;
			case 'PING':
				$this->send("PONG {$parts[1]}\n");
		}
		
		switch($parts[1]) {
			case 'PRIVMSG':
				$content = trim(implode(' ', array_slice($parts, 3)), ": ");
				if(substr($content,0,1) == "\001") {
					$ctcp = true;
					$content = trim($content, "\001");
				}
				
				if($content == 'VERSION' && $ctcp) {
					if(isset($this->users[$parts[0]]))
						$this->getUserByHost($parts[0])->sendCTCP('VERSION PSO_IRCClient');
				} else {
					if($parts[2] != $this->nick) {
						// Process channel text
						$user = $this->getUserByHost($parts[0]);
						$chan = $this->getChannel($parts[2]);

						$this->pool->raiseEvent('Message', array($content, $user, $ctcp), NULL, $chan);
						$this->raiseEvent('Message', array($content, $user, $ctcp), NULL, $chan);
						$chan->raiseEvent('Message', array($content, $user, $ctcp));
					} else {
						$user = $this->getUserByHost($parts[0]);
						$this->pool->raiseEvent('PrivateMessage', array($content, $ctcp), NULL, $user);
						$this->raiseEvent('PrivateMessage', array($content, $ctcp), NULL, $user);
					}
				}
				break;
			case 'KICK':
				$content = trim(implode(' ', array_slice($parts, 4)), ": ");
				if($parts[3] == $this->nick) {
					$user = $this->getUserByHost($parts[0]);
					$chan = $this->getChannel($parts[2]);
					
					$this->partChannel($chan);

					$chan->raiseEvent('Kicked', array($chan, $content, $user), NULL, $this);
					$this->pool->raiseEvent('Kicked', array($chan, $content, $user), NULL, $this);
					$this->raiseEvent('Kicked', array($chan, $content, $user));
				} else {
					$user = $this->getUserByHost($parts[0]);
					$victim = $this->getUserByHost($parts[3]);
					$chan = $this->getChannel($parts[2]);

					$chan->removeUser($victim);
					
					$this->pool->raiseEvent('Kick', array($victim, $content, $user), NULL, $chan);
					$this->raiseEvent('Kick', array($victim, $content, $user), NULL, $chan);
					$chan->raiseEvent('Kick', array($victim, $content, $user));
				}
				break;
			case 'NICK':
				$user = $this->getUserByHost($parts[0]);
				$user->setNick(trim($parts[2], ':'));
				break;
			case 'JOIN':
				$user = $this->getUserByHost($parts[0]);
				$chan = $this->getChannel(trim($parts[2], ':'));

				$chan->addUser($user);
				
				$this->pool->raiseEvent('Join', array($user), NULL, $chan);
				$this->raiseEvent('Join', array($user), NULL, $chan);
				$chan->raiseEvent('Join', array($user));
				break;
			case 'PART':
				$user = $this->getUserByHost($parts[0]);
				$chan = $this->getChannel(trim($parts[2], ':'));

				$chan->removeUser($user);
				
				$this->pool->raiseEvent('Part', array($user), NULL, $chan);
				$this->raiseEvent('Part', array($user), NULL, $chan);
				$chan->raiseEvent('Part', array($user));
				break;
			case 'QUIT':
				$content = trim(implode(' ', array_slice($parts, 3)), ": ");
				$user = $this->getUserByHost($parts[0]);

				foreach($this->channels as $chan) {
					foreach($chan->users as $chanuser) {
						if($chanuser == $user) {
							$chan->removeUser($user);
							$chan->raiseEvent('Quit', array($user));
						}
					}
				}
				$this->pool->raiseEvent('Quit', array($user));
				$this->raiseEvent('Quit', array($user));
				break;
			case 'NOTICE':
				if(in_array($parts[2], array('AUTH', '*', '***'))) {
					if( !$this->authenticated ) {
						$this->send("NICK {$this->nick}\n");
						$this->send("USER {$this->nick} localhost {$this->remoteHost} :{$this->nick}\n");
						$this->authenticated = TRUE;
					}
					break;
				}

				$content = trim(implode(' ', array_slice($parts, 3)), ": \001");
				$user = $this->getUserByHost($parts[0]);
				if($parts[2] != $this->nick)
				{
					$chan = $this->getChannel(trim($parts[2], ':'));

					$this->pool->raiseEvent('Notice', array($content, $user), NULL, $chan);
					$this->raiseEvent('Notice', array($content, $user), NULL, $chan);
					$chan->raiseEvent('Notice', array($content, $user));
				} else {
					$this->pool->raiseEvent('PrivateNotice', array($content), NULL, $user);
					$this->raiseEvent('PrivateNotice', array($content), NULL, $user);
				}
				break;
			case 'MODE':
				$content = trim(implode(' ', array_slice($parts, 3)), ": \001");
				$user = $this->getUserByHost($parts[0]);
				if($parts[2] != $this->nick) {
					$chan = $this->getChannel(trim($parts[2], ':'));

					$chan->addModes($content);
					$this->pool->raiseEvent('Mode', array($content, $user), NULL, $chan);
					$this->raiseEvent('Mode', array($content, $user), NULL, $chan);
					$chan->raiseEvent('Mode', array($content, $user));
				}
				break;
				
			case '005':
			case '376':
				$this->pool->raiseEvent('Connected', array(), NULL, $this);
				$this->raiseEvent('Connected');
				break;
			
			case '332':
				$content = trim(implode(' ', array_slice($parts, 4)), ": ");
				$chan = $this->getChannel($parts[3]);
				$chan->setTopic($content);
				
				break;
				
			case '353':
				$chan = $this->getChannel($parts[4]);
				$names = explode(' ', trim(implode(' ', array_slice($parts, 5)), ": "));
				foreach($names as $name) {
					$user = $this->getUserByNick($name);
					$chan->addUser($user);
				}
				break;
				
			case '366':
				$chan = $this->getChannel($parts[3]);
				
				$this->pool->raiseEvent('Joined', array(), NULL, $chan);
				$this->raiseEvent('Joined', array(), NULL, $chan);
				$chan->raiseEvent('Joined');
				break;
		}
	}
}