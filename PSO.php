<?php
spl_autoload_register(function($class) {
	$dir = dirname(realpath(__FILE__));
	
	if(file_exists($file = "{$dir}/lib/{$class}.php")) {
		include $file;
	}
});
