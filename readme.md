PHP Stream Objects v0.3.1
=========================

An event driven, concurrent, object oriented library to encapsulate PHP stream functionality.
Currently supports TCP & HTTP connections (both client & server), and Processes.

Provides a simple callback system to intercept connections/disconnections, incoming data, and many protocol specific conditions

Uses SELECT to provide concurrency handling across multiple streams of any type (You can mix TCP Servers with HTTP Clients without worry)


PSO_TCPClient
-------------
For interacting with services on a TCP port without blocking

PSO_TCPServer
-------------
Easy to use TCP server with automatic connection handling

PSO_Process
-----------
Runs a command in a new process (asynchronously)

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

* gzip Compression
* http proxy support
* PSO_UDPServer
* PSO_UDPClient
* PSO_IRCServer
* PSO_IRCClient
* PSO_WebSocketServer
* PSO_WebSocketClient
* PSO_FD


Possible Modules
================

* PSO_SSLClient
* PSO_HTTPSClient
* PSO_SSHClient
* PSO_SSHProcess
* PSO_SSHTunnel
* PSO_ExpectProcess
