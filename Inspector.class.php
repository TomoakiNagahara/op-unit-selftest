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
		self::$_result['user'][$type][$host][$user]['result'] = $io;

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
			//	...
			$host = $config['user']['host'];
			$type = $config['user']['driver'];
			$port = $config['user']['port'];
			$user = $config['user']['user'];

			//	...
			self::$_dbh = self::User($config['user']);

			//	...
			self::Databases($config['databases'], self::$_result['structure'][$host][$type][$port]);
		}
	}

	/** Check databases
	 *
	 * @param array $config
	 */
	static function Databases($databases, &$_result)
	{
		//	...
		$sql  = \SQL\Show::Database(self::$_dbh);
		$list = self::$_dbh->Query($sql);

		//	...
		foreach( $databases as $name => $config ){
			$io = array_search($name, $list) === false ? false: true;
			$_result['databases'][$name]['result'] = $io;

			//	...
			self::tables($name, $config['tables'], $_result);
		}
	}

	/** Check databases
	 *
	 * @param array $config
	 */
	static function Tables($database, $tables, &$_result)
	{
		//	...
		$sql  = \SQL\Show::Table(self::$_dbh, $database);
		$list = self::$_dbh->Query($sql);

		//	...
		foreach( $tables as $name => $config ){
			$io = array_search($name, $list) === false ? false: true;
			$_result['tables'][$database][$name]['result'] = $io;

			//	...
			self::Columns($database, $name, ifset($config['columns'], []), $_result);
			self::Indexes($database, $name, ifset($config['indexes'], []), $_result);
			self::Autoinc($database, $name, ifset($config['autoinc'], []), $_result);
		}

		D($tables);
	}

	static function Columns($database, $table, $columns, &$_result)
	{
		//	...
		$sql  = \SQL\Show::Column(self::$_dbh, $database, $table);
		$list = self::$_dbh->Query($sql);

		//	...
		foreach( $columns as $column => $structs ){
			if(!$io = isset($list[$column]) ? true: false ){
				$_result['columns'][$database][$table][$column]['result'] = $io;
				continue;
			}

			//	...
			foreach( $structs as $key => $val ){
				$io = $list[$column][$key] === $val ? true: false;
				$_result['columns'][$database][$table][$column][$key]['result'] = $io;
				if(!$io ){
					$_result['columns'][$database][$table][$column][$key]['detail']['current'] = $list[$column][$key];
					$_result['columns'][$database][$table][$column][$key]['detail']['update']  = $val;
				}
			}
		}
	}

	static function Indexes($database, $table, $indexes, &$_result)
	{
		//	...
		$sql  = \SQL\Show::Index(self::$_dbh, $database, $table);
		$list = self::$_dbh->Query($sql);
	}

	static function Autoinc($database, $table, $autoinc, &$_result)
	{

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