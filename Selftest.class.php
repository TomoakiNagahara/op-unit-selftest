<?php
/**
 * unit-selftest:/Selftest.class.php
 *
 * @created   2018-01-05
 * @version   1.0
 * @package   unit-selftest
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright Tomoaki Nagahara All right reserved.
 */

/** namespace
 *
 * @creation  2018-03-19
 */
namespace OP\UNIT;

/** Used class
 *
 */
use OP\OP_CORE;
use OP\OP_UNIT;
use OP\IF_UNIT;
use function OP\Html;

/** Selftest
 *
 * @created   2018-01-05
 * @version   1.0
 * @package   unit-selftest
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright Tomoaki Nagahara All right reserved.
 */
class Selftest implements IF_UNIT
{
	/** trait
	 *
	 */
	use OP_CORE, OP_UNIT;

	/** Generate Configer instance.
	 *
	 * @return \OP\UNIT\SELFTEST\Configer
	 */
	static function Configer()
	{
		return new \OP\UNIT\SELFTEST\Configer();
	}

	/** Automatically do self test.
	 *
	 */
	function Auto($config)
	{
		//	...
		if(!$db = $this->Database() ){
			$form = $this->Form();
			$this->Unit('App')->Template(__DIR__.'/form.phtml',['form'=>$form]);
			$this->Unit('WebPack')->Auto([__DIR__.'/form.css',__DIR__.'/form.js']);
			return false;
		};

		//	...
		include(__DIR__.'/Inspector.class.php');
		$inspector = new \OP\UNIT\SELFTEST\Inspector();
		$inspector->Auto($config, $db);

		//	Internal notice.
		echo '<ol class="error">';
		//	Selftest
		foreach( self::Error() as $error ){
			Html($error, 'li');
		};
		//	Inspector
		while( $error = $inspector->Error() ){
			Html($error, 'li');
		}
		echo '</ol>';

		//	...
		$inspector->Result();

		//	...
		if( $io = $inspector->isResult() ){
			$message = 'Selftest was successful.';
			$classes = '.bold .success';
		}else{
			$message = 'Selftest was failure.';
			$classes = '.bold .failure';
		}

		//	...
		Html($message, $classes);

		//	...
		return $io;
	}

	function Config()
	{
		//	...
		static $_config;

		//	...
		if(!$_config ){
			$_config = new \OP\UNIT\SELFTEST\Configer();
		};

		//	...
		return $_config;
	}

	/** Get the unit of Database.
	 *
	 * @return \OP\UNIT\Database $database
	 */
	function Database()
	{
		/* @var $form \OP\Unit\Form */
		if(!$form = $this->Form() ){
			return null;
		};

		//	...
		if(!$form->isValidate() ){
			return null;
		};

		//	...
		if(!$config = $form->Values() ){
			return null;
		};

		//	...
		if(!$this->Form()->isValidate() ){
			return;
		};

		/* @var $db \OP\UNIT\Database */
		if(!$db = $this->Unit('Database') ){
			return;
		};

		//	...
		if(!$config['prod'] ){
			return false;
		};

		//	...
		if(!$db->Connect($config) ){
			return false;
		};

		//	...
		return $db;
	}

	/** Form
	 *
	 * @return Form
	 */
	function Form()
	{
		//	...
		static $_form;

		//	...
		if(!$_form ){
			$_form = $this->Unit('Form');
			$_form->Config(__DIR__.'/form.config.php');
		};

		//	...
		return $_form;
	}

	/** Inspector
	 *
	 * @param	 array				 $args
	 * @param	\OP\UNIT\Database	 $db
	 * @return	 boolean			 $io
	 */
	function Inspector($args, $db)
	{
		return \OP\UNIT\SELFTEST\Inspector::Inspection($args, $db);
	}

	/** Result
	 *
	 */
	function Result()
	{
		return \OP\UNIT\SELFTEST\Inspector::Result();
	}

	/** Error
	 *
	 */
	static function Error($error=null)
	{
		//	...
		static $_errors = [];

		//	...
		if( $error ){
			//	...
			$_errors[] = $error;
		}else{
			//	...
			return $_errors;
		}
	}

	function Help($config=null)
	{
		$readme = file_get_contents(__DIR__.'/README.md');
		echo '<dir class="border">';
		echo nl2br($readme);
		echo '</dir>';
	}

	/*
	function Debug($config=null)
	{
		\OP\UNIT\SELFTEST\Inspector::Debug();
	}
	*/
}
