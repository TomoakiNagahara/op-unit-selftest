<?php
/**
 * unit-selftest:/Inspector.class.php
 *
 * @created   2017-12-09
 * @version   1.0
 * @package   unit-selftest
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright Tomoaki Nagahara All right reserved.
 */

/** namespace
 *
 */
namespace OP\UNIT\SELFTEST;

/** Inspector
 *
 * @created   2017-12-09
 * @version   1.0
 * @package   unit-selftest
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright Tomoaki Nagahara All right reserved.
 */
class Inspector
{
	/** trait
	 *
	 */
	use \OP_CORE;

	/** Store database handlers.
	 *
	 * @var array
	 */
	static $_dbhs;

	/** Database handler object.
	 *
	 * @var DB
	 */
	static $_dbh;

	/** Failure
	 *
	 * @var boolean
	 */
	static $_failure;

	/** Result
	 *
	 * @var array
	 */
	static $_result;

	/** Automatically self testing.
	 *
	 * @param  array $configs
	 */
	static function Auto($configs)
	{
		//	...
		self::Users($configs);

		//	...
		self::Structures($configs);

		//	...
		if( self::$_failure ){
			\Template::Run('form.phtml');
		}
	}

	/** Check each user connection.
	 *
	 * @param array $config
	 */
	static function User($config)
	{
		//	...
		$type = $config['driver'];
		$host = $config['host'];
		$user = $config['user'];

		//	...
		if( isset( self::$_dbhs[$type][$host][$user]) ){
			return self::$_dbhs[$type][$host][$user];
		}

		/* @var $dbh DB */
		if(!$dbh = \Unit::Factory('db') ){
			return;
		}

		//	...
		$io = $dbh->Connect($config);

		//	...
		self::$_result[$type][$host][$user] = $io;

		//	...
		self::$_dbhs[$type][$host][$user] = $dbh;

		//	...
		if(!$io ){
			self::$_failure = true;
		}

		//	...
		return $dbh;
	}

	/** Check connection of users.
	 *
	 * @param  array $config
	 */
	static function Users($configs)
	{
		//	...
		foreach( $configs['users'] as $config ){
			self::User($config);
		}
	}

	/** Check structures.
	 *
	 * @param  array $config
	 */
	static function Structures($configs)
	{
		//	...
		if(!\Unit::Load('SQL') ){
			return;
		}

		//	...
		foreach($configs['structures'] as $config){
			self::$_dbh = self::User($config['user']);
			self::Databases($config['databases']);
		}
	}

	/** Check databases
	 *
	 * @param array $config
	 */
	static function Databases($config)
	{
		//	...
		$sql = \SQL\Show::Database(self::$_dbh);
		$databases = self::$_dbh->Query($sql);
D($databases);
		//	...
		foreach( $config as $name => $conf ){
			D($name, $conf);
		}
	}

	/** Get inspection result.
	 *
	 * @return array
	 */
	static function Result()
	{
		return self::$_result;
	}
}