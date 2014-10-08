<?php
/*
* ----------------------------------------------------------------------------
* "THE BEER-WARE LICENSE" (Revision 42):
* <viper7@viper-7.com> wrote this file. As long as you retain this notice you
* can do whatever you want with this stuff. If we meet some day, and you think
* this stuff is worth it, you can buy me a beer in return.   Dale Horton
* ----------------------------------------------------------------------------
*/
spl_autoload_register(function($class) {
	$dir = dirname(realpath(__FILE__));
	
	if(file_exists($file = "{$dir}/lib/{$class}.php")) {
		include $file;
	}
});
