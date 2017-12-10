<?php
/**
 * unit-selftest:/autoloader.php
 *
 * @creation  2017-12-09
 * @version   1.0
 * @package   unit-selftest
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright Tomoaki Nagahara All right reserved.
 */
//	...
spl_autoload_register( function($path){
	//	...
	$namespace = 'OP\UNIT\SELFTEST\\';

	//	...
	if( strpos($path, $namespace) !== 0 ){
		return;
	}

	//	...
	$name = substr($path, strlen($namespace));

	//	...
	$path = __DIR__."/{$name}.class.php";

	//	...
	if( file_exists($path) ){
		include($path);
	}else{
		Notice::Set("Does not exists this file. ($path)");
	}
});