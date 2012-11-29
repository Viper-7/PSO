<?php
include '../PSO.php';

// Target URL to scrape
$url = 'http://codepad.viper-7.com/';

$content = array();
$pool = new PSO_HTTPClient();

// Use up to 5 HTTP requests in parallel
$pool->setConcurrency(5);

// Add the main page to the queue
$root = $pool->addTarget($url);

// Handle the response for $url
$root->onResponse(function() use (&$content) {
	// Store the response in the result array
	$content['document'][$this->requestURI] = $this->responseBody;
	
	$dom = $this->getDOM();
	$base = $this->requestURI;
	
	// Parse out any <base> tag to use for links
	foreach($dom->getElementsByTagName('base') as $basetag) {
		$base = $basetag->getAttribute('href');
	}
	
	// Fetch each <script> tag that has an src attribute
	foreach($dom->getElementsByTagName('script') as $script) {
		$src = $script->getAttribute('src');
		
		if($src) {
			// Build the URL for the external content
			$target = $this->joinURL($base, $src);
			
			// Add it to the scraping queue
			$conn = $this->pool->addTarget($target);
			
			// Put the response in the result array
			$conn->onResponse(function() use (&$content) {
				$content['js'][$this->requestURI] = $this->responseBody;
			});
		}
	}
	
	// Fetch each <link> tag, that has rel="stylesheet" and href attributes
	foreach($dom->getElementsByTagName('link') as $link) {
		$rel = strtolower($link->getAttribute('rel'));
		$href = $link->getAttribute('href');
		
		if($rel == 'stylesheet' && $href) {
			// Build the URL for the external content
			$target = $this->joinURL($base, $href);

			// Add it to the scraping queue
			$conn = $this->pool->addTarget($target);

			// Put the response in the result array
			$conn->onResponse(function() use (&$content) {
				$content['css'][$this->requestURI] = $this->responseBody;
			});
		}
	}
});


// Report some status while running
$pool->onTick(function() {
	$active = count($this->active);
	$inactive = count($this->connections) - $active;
	
	echo "{$active} Active, {$inactive} Waiting\r";		
});

// Run the task
PSO::drain($pool);

// Show the array we've built
foreach($content as $type => $elem) {
	foreach($elem as $url => $body) {
		$len = strlen($body);
		echo "{$type}: {$url} - {$len} Bytes <br/>\r\n";
	}
}