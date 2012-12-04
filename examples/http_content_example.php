<?php
// Target URLs to scrape
$urls = array(
	/*'http://codepad.viper-7.com/',
	'http://www.youtube.com/',
	'http://www.news.com.au/',
	'http://www.google.com/',
	'http://www.bing.com/',
	'http://www.wikipedia.org/',*/
	'http://www.php.net/'
);

include_once '../PSO.php';

$content = array();
$pool = new PSO_HTTPClient();

// Set the user agent so remote sites don't think we're a bot
$pool->userAgent = 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:16.0) Gecko/20100101 Firefox/16.0';

// Add the main page to the queue
$pool->addTargets($urls, function() use (&$content) {
	// Store the response in the result array
	$content[$this->requestURI]['document'][0] = $this->responseBody;
	$content[$this->requestURI]['requests'] = 1;
	
	$dom = $this->getDOM();
	$base = $this;
	
	$fetch = array('img' => 'src', 'script' => 'src', 'link' => 'href');
	
	foreach($fetch as $tagname => $attribute) { 
		foreach($dom->getElementsByTagName($tagname) as $link) {
			if($href = $link->getAttribute($attribute)) {
				$target = $this->getMediaURL($href);
				
				$content[$this->requestURI]['requests'] += 1;
				
				$this->pool->addTarget($target, function() use (&$content, $base, $link) {
					
					if($link->getAttribute('type') == 'text/css') {
						preg_match_all('/url\s*\(?\s*["\']([^"\']+?)["\']/', $this->responseBody, $matches, PREG_SET_ORDER);
						
						foreach($matches as $match) {
							$target = $base->getMediaURL($match[1]);
							
							$content[$base->requestURI]['requests'] += 1;
							
							$this->pool->addTarget($target, function() use (&$content, $base) {
								$content[$base->requestURI]['import'][$this->requestURI] = $this->responseBody;
							});
						}
					}
					
					$content[$base->requestURI][$link->tagName][$this->requestURI] = $this->responseBody;
				});
			}
		}
	}
});

$time = PSO::drain($pool);
$total = 0;

?>
<table width="600">
	<thead>
		<tr>
			<th>URL</th>
			<th>Page</th>
			<th>JS</th>
			<th>CSS</th>
			<th>CSS URL</th>
			<th>Images</th>
			<th>Requests</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach($content as $baseurl => $links) {
				$links += ['document'=>[],'script'=>[],'link'=>[],'import'=>[],'img'=>[]];
				$vars = ['document', 'script', 'link', 'import', 'img'];
				foreach($vars as $name) {
					$$name = array_sum(array_map('strlen', $links[$name]));
				}
				$requests = $links['requests'];
				$total += $document + $script + $link + $import + $img;
		?>
			<tr>
				<td><?= htmlentities(substr($baseurl,0,50)); ?></td>
				<td><?= PSO::divideSize($document) ?>b</td>
				<td><?= PSO::divideSize($script) ?>b</td>
				<td><?= PSO::divideSize($link) ?>b</td>
				<td><?= PSO::divideSize($import) ?>b</td>
				<td><?= PSO::divideSize($img) ?>b</td>
				<td><?= $requests ?></td>
			</tr>
		<?php } ?>
	</tbody>
	<tfoot>
		<tr>
			<td><?= PSO::divideSize($total); ?>b total</td>
			<td colspan="6"><?= $pool->requestCount ?> requests took <?= $time ?> seconds</td>
		</tr>
	</tfoot>
</table>
