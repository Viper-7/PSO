PHP Stream Objects v0.3.9
=========================

An event driven, concurrent, object oriented library to encapsulate PHP stream functionality.
Currently supports TCP & HTTP connections (both client & server), Processes and a simple IRC Client.

Allows you to connect to or run multiple services in PHP using a single process/thread.
PSO abstracts away all the complexities of dealing with non-blocking streams, file descriptors and SELECT.

Provides an easy to use callback system to intercept connections/disconnections, incoming data, and many protocol specific conditions.

Uses SELECT to provide concurrency handling across multiple streams of any type, You can pass any mix of PSO objects to PSO::drain() to handle them all concurrently.

<br/>
<br/>

Examples
========

http_example.php
----------------

Demonstrates a page title scraping engine for HTML/HTTP, Connects to a large number of services in parallel, Dropping the connection as soon as the page title is known.

irc_example.php
---------------

Demonstrates a simple connection to an IRC server. Automatically joining a channel, rejoining it when kicked, and responding to both public & private messages.

http_server_example.php
-----------------------

Demonstrates a basic HTTP server, which delivers response for two URLs (/ and /date), and a 404 result for any other requested URL.

tcp_example.php
---------------

Demonstrates a basic TCP Server and Client, sending messages back & forth between them.
Highlights some of the finer points of PSO such as the I/O buffer handling on disconnection (Server still receives "fine.")

<br/>
<br/>

Drivers
=======
This is a list of the currently implemented PSO drivers, and what events they provide.

PSO_TCPClient
-------------
For interacting with services on a TCP port without blocking

PSO_TCPServer
-------------
Easy to use TCP server with automatic connection handling

PSO_UDPClient
-------------
For interacting with services on a UDP port

PSO_UDPServer
-------------
Easy to use UDP server with virtual connection handling for multiple client support

PSO_Process
-----------
Runs a command in a new process (asynchronously)

PSO_FD
------
For interacting with a file descriptor attached to the current process

PSO_STDIO
---------
For interacting with the console attached to the current process (STDIN, STDOUT & STDERR)

PSO_HTTPClient
--------------
Native support for DOMDocument
Supports parsing of partial requests

`onHeaders`
* Called once HTTP headers have been received from the remote server
	
`onResponse`
* Called once the complete HTTP response has been received from the remote server
	
`onPartial`
* Like onResponse, but will be called as each packet is received, until a complete document is loaded

`onError`
* Called on an error status code (not 2xx or 3xx)

`onRedirect`
* Called to handle a redirection status code (3xx)
* Only used when $httpClient->captureRedirects is set to true
	
PSO_HTTPServer
--------------
For testing servers & simple REST services
	
`onRequest`
* Called when a complete request has been received from the client
	
`onMissingRequest`
* Called when an onRequest handler could not be found for the requested URI

PSO_IRCClient
-------------
A simple IRC client, can join/part channels, send/receive messages, and tracks users in each channel
	
`onMessage`
* Called when a message is received in a channel

`onPrivateMessage`
* Called when a message is received in private

`onKick`
* Called when a user is kicked from the channel

`onKicked`
* Called when the bot is kicked from the channel

`onJoin`
* Called when a user joins the channel

`onJoined`
* Called when the bot joins the channel

`onPart`
* Called when a user leaves the channel

`onQuit`
* Called when a user disconnects from IRC

`onNotice`
* Called when a notice is received from a channel

`onPrivateNotice`
* Called when a notice is received in private

`onMode`
* Called when channel modes are changed

`onConnected`
* Called after receiving the MOTD from the server (fully connected)

`onJoined`
* Called after joining a new channel
	
Shared Events
----------------------------------------------
Available for all stream types

`onConnect`
* Called when connected to a remote host
	
`onData`
* Called for each packet of data received
		
`onDisconnect`
* Called when the remote host disconnects
	
`onClose`
* Called when the server begins to shut down


Planned Features
================

* PSO_WebSocketServer
* PSO_WebSocketClient


Possible Modules
================

* gzip Compression
* http proxy support
* PSO_SSLClient
* PSO_HTTPSClient
* PSO_SSHClient
* PSO_SSHProcess
* PSO_SSHTunnel
* PSO_ExpectProcess
