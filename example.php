<?php
include 'PSO.php';

$start = microtime(true);

$client = new PSO_HTTPClient();
$client->setConcurrency(1);

$client->addTargets(array(
	'http://www.overclockers.com.au/',
	'http://www.ausgamers.com.au/',
	'http://www.news.com.au/',
	'http://www.google.com/',
	'http://www.bing.com/',
	'http://www.microsoft.com/',
	'http://www.yahoo.com/',
	'http://www.amazon.com/',
	'http://www.rackspace.com/',
	'http://www.youtube.com/',
	'http://www.slashdot.org/',
	'http://www.mozilla.org/',
	'http://www.wikipedia.org/',
	'http://www.php.net/'
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
