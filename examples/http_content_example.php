<?php
include '../PSO.php';

// Target URLs to scrape
$urls = array(
	'http://www.codepad.viper-7.com/',
	'http://www.microsoft.com/',
	'http://www.amazon.com/',
	'http://www.rackspace.com/',
	'http://www.youtube.com/',
	'http://www.news.com.au/',
	'http://www.google.com/',
	'http://www.bing.com/',
	'http://www.slashdot.org/',
	'http://www.mozilla.org/',
	'http://www.wikipedia.org/',
	'http://www.php.net/'
);

$content = array();
$pool = new PSO_HTTPClient();

// Set the user agent so remote sites don't think we're a bot
$pool->userAgent = 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:16.0) Gecko/20100101 Firefox/16.0';

// Add the main page to the queue
$pool->addTargets($urls, function() use (&$content) {
	// Store the response in the result array
	$content[$this->requestURI]['document'][0] = $this->responseBody;
	
	$dom = $this->getDOM();
	$base = $this->requestURI;
	
	// Parse out any <base> tag to use for links
	foreach($dom->getElementsByTagName('base') as $basetag) {
		$base = $basetag->getAttribute('href');
	}
	
	// Fetch each <img> tag that has an src attribute
	foreach($dom->getElementsByTagName('img') as $img) {
		$src = $img->getAttribute('src');
		
		if($src) {
			// Build the URL for the external content
			$target = $this->joinURL($base, $src);
			
			// Add it to the scraping queue
			$conn = $this->pool->addTarget($target);
			
			// Put the response in the result array
			$conn->onResponse(function() use (&$content, $base) {
				$content[$base]['img'][$this->requestURI] = $this->responseBody;
			});
		}
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
			$conn->onResponse(function() use (&$content, $base) {
				$content[$base]['js'][$this->requestURI] = $this->responseBody;
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
			$conn->onResponse(function() use (&$content, $base) {
				$content[$base]['css'][$this->requestURI] = $this->responseBody;
			});
		}
	}
});

$pool->onError(function($error) {
	if($this->responseStatusCode)
		var_dump($this->sent, $this->rawResponse);
});

$char = '/';
$chars = array('/' => '-', '-' => '\\', '\\' => '|','|'=>'/');

$pool->onTick(function() use (&$char, $chars) {
	$char = $chars[$char];
	$active = array_sum(array_map('count', $this->active));
	$inactive = count($this->connections) - $active;
	$ipcount = count($this->active);
	$speed = $this->getReadSpeed();
	
	echo "  {$char}   {$active} Connections to {$ipcount} Domains, {$inactive} Queued - {$speed}/s        \r";
});

$start = microtime(true);

// Run the task
PSO::drain($pool);

$end = microtime(true);

$total = 0;

// Show the array we've built
foreach($content as $baseurl => $links) {
	echo str_pad(substr($baseurl,0,30), 30, ' ', STR_PAD_RIGHT) . " - ";
	$links += array('img' => array(), 'js' => array(), 'css' => array());
	foreach($links as $type => $data) {
		$type = str_pad($type, 5, ' ', STR_PAD_LEFT);
		$count = str_pad(count($data), 4, ' ', STR_PAD_RIGHT);
		$len = array_sum(array_map('strlen', $data));
		$total += $len;
		$len = str_pad(number_format($len / 1024, 2) . 'kb', 11, ' ', STR_PAD_RIGHT);
		echo "{$type}: {$count} {$len} \t";
	}
	echo "\r\n";
}

$total /= 1024;
$time = number_format($end - $start, 3); 
$speed = number_format($total / $time, 1);
$total = number_format($total / 1024, 2);
echo "{$total} Mb total, {$pool->requestCount} requests took {$time}s ({$speed} K/s)\r\n";
echo "Errors: ";
var_dump($pool->statusCount);