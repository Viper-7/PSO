<?php
include '../PSO.php';

$url = 'http://www.overclockers.com.au';
$content = array();

$pool = new PSO_HTTPClient();
$pool->setConcurrency(1000);	// must be higher than the number of external sources in the page
$root = $pool->addTarget($url);

$root->onResponse(function() use (&$content) {
	$content['document'] = $this->responseBody;
	$dom = $this->getDOM();

	foreach($dom->getElementsByTagName('script') as $script) {
		if($script->hasAttribute('src')) {
			$url = $script->getAttribute('src');
			$content['script'][$url] = $this->pool->addTarget($url);
		}
	}

	foreach($dom->getElementsByTagName('link') as $script) {
		if($script->hasAttribute('rel')) {
			$url = $script->getAttribute('rel');
			$content['css'][$url] = $this->pool->addTarget($url);
		}
	}
});

PSO::drain($pool);

var_dump($content);
