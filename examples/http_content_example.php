<?php
include '../PSO.php';

$url = 'http://codepad.viper-7.com/';

$content = array();

$pool = new PSO_HTTPClient();
$pool->setConcurrency(1000);	// must be higher than the number of external sources in the page
$root = $pool->addTarget($url);

$root->onResponse(function() use (&$content) {
	$content['document'][$this->requestURI] = $this->responseBody;
	$dom = $this->getDOM();
	
	foreach($dom->getElementsByTagName('script') as $script) {
		if($script->hasAttribute('src')) {
			$conn = $this->pool->addTarget($script->getAttribute('src'));
			$conn->onResponse(function() use (&$content) {
				$content['script'][$this->requestURI] = $this->responseBody;
			});
		}
	}
	
	foreach($dom->getElementsByTagName('link') as $link) {
		if($link->hasAttribute('rel') && strtolower($link->getAttribute('rel')) == 'stylesheet' && $link->hasAttribute('href')) {
			$conn = $this->pool->addTarget($link->getAttribute('href'));
			$conn->onResponse(function() use (&$content) {
				$content['link'][$this->requestURI] = $this->responseBody;
			});
		}
	}
});

PSO::drain($pool);

foreach($content as $type => $elem) {
	foreach($elem as $url => $body) {
		$len = strlen($body);
		echo "{$type}: {$url} - {$len} Bytes<br>";
	}
}