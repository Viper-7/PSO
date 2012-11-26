<?php
include 'PSO.php';

$start = microtime(true);

$client = new PSO_HTTPClient();
$client->setConcurrency(21);

$client->addTargets(array(
	'http://www.overclockers.com.au/',
	'http://www.ausgamers.com.au/',
	'http://www.news.com.au/',
	'http://www.overclockers.com.au/',
	'http://www.ausgamers.com.au/',
	'http://www.news.com.au/',
	'http://www.overclockers.com.au/',
	'http://www.ausgamers.com.au/',
	'http://www.news.com.au/',
	'http://www.overclockers.com.au/',
	'http://www.ausgamers.com.au/',
	'http://www.news.com.au/',
	'http://www.overclockers.com.au/',
	'http://www.ausgamers.com.au/',
	'http://www.news.com.au/',
	'http://www.overclockers.com.au/',
	'http://www.ausgamers.com.au/',
	'http://www.news.com.au/',
	'http://www.overclockers.com.au/',
	'http://www.ausgamers.com.au/',
	'http://www.news.com.au/',
));

$client->onPartial(function() {
	$titles = $this->getDOM()->getElementsByTagName('title');

	if($titles->length) {
		$responseTitle = $titles->item(0)->textContent;

		echo "Response: {$this->requestURI} {$responseTitle}\r\n";
		$this->disconnect();
	}
});

$client->onError(function() {
	echo "Error: {$this->requestURI} {$this->responseStatusCode} {$this->responseStatus}\r\n";
});


PSO::drain($client);

$end = microtime(true);
echo "Operation took: " . number_format($end - $start, 6) . " seconds\r\n";
