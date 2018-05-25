<?php
/**
 * unit-selftest:/Configer.class.php
 *
 * @created   2018-03-24
 * @version   1.0
 * @package   unit-selftest
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright Tomoaki Nagahara All right reserved.
 */

/** namespace
 *
 * @created   2018-03-24
 */
namespace OP\UNIT\SELFTEST;

/** Configer
 *
 * @created   2018-03-24
 * @version   1.0
 * @package   unit-selftest
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright Tomoaki Nagahara All right reserved.
 */
class Configer
{
	/** trait
	 *
	 */
	use \OP_CORE;

	static private $_config;

	static function Get()
	{
		return self::$_config;
	}

	static function DSN($host=null, $product=null, $port=null)
	{
		static $_host = 'localhost', $_product = 'mysql', $_port = '3306';

		//	...
		if( $host ){
			$_host = $host;
		}
		if( $product ){
			$_product = $product;
		}
		if( $port ){
			$_port = $port;
		}

		//	...
		if(!$host ){
			$_host;
		}
		if(!$product ){
			$_product;
		}
		if(!$port ){
			$_port;
		}

		//	...
		return sprintf('%s://%s:%s', $_product, $_host, $_port);
	}

	static function User($user=null, $password=null, $charset=null)
	{
		//	...
		static $_user, $_charset='utf8';

		//	...
		if( $user ){
			$_user = $user;
			$dsn   = self::Dsn();
			self::$_config[$dsn]['users'][$_user]['name'] = $_user;
		}

		//	...
		if( $password ){
			self::Password($password);
		}

		if( $charset ){
			$_charset = $charset;
			self::$_config[$dsn]['users'][$_user]['charset'] = $_charset;
		}

		//	...
		return $_user;
	}

	static function Privilege($user, $database, $table='*', $privilege='INSERT, SELECT, UPDATE, DELETE', $column='*')
	{
		//	...
		$dsn = self::Dsn();

		//	...
		self::$_config[$dsn]['users'][$user]['privilege'][$database][$table][$privilege] = $column;
	}

	static function Password($password=null)
	{
		static $_password;
		if( $password ){
			$_password = $password;

			//	...
			$dsn  = self::Dsn();
			$user = self::User();
			self::$_config[$dsn]['users'][$user]['password'] = $_password;
		}
		return $_password;
	}

	static function Database($database=null, $charset='utf8')
	{
		static $_database;
		if( $database ){
			$_database = $database;
			$dsn       = self::Dsn();
			$collation = self::_Collate($charset);
			self::$_config[$dsn]['databases'][$database]['name']      = $_database;
			self::$_config[$dsn]['databases'][$database]['collation'] = $collation;
		}
		return $_database;
	}

	static function Table($table=null, $comment=null, $charset='utf8')
	{
		static $_table;
		if( $table ){
			$_table    = $table;
			$dsn       = self::Dsn();
			$database  = self::Database();
			$collation = self::_Collate($charset);
			self::$_config[$dsn]['databases'][$database]['tables'][$table]['name']      = $_table;
			self::$_config[$dsn]['databases'][$database]['tables'][$table]['collation'] = $collation;
			self::$_config[$dsn]['databases'][$database]['tables'][$table]['comment']   = $comment;
		}
		return $_table;
	}

	static function Column($name, $type, $length, $null, $default, $comment)
	{
		//	...
		$dsn      = self::Dsn();
		$database = self::Database();
		$table    = self::Table();

		//	...
		$column['name']    = $name;
		$column['type']    = $type;
		$column['length']  = $length;
		$column['null']    = $null ? true: false;
		$column['default'] = $default;
		$column['comment'] = $comment;

		//	...
		switch( $type ){
			case 'timestamp':
				$column['extra']   = 'on update CURRENT_TIMESTAMP';
				$column['default'] = 'CURRENT_TIMESTAMP';
				break;

			default:
		}

		//	...
		self::$_config[$dsn]['databases'][$database]['tables'][$table]['columns'][$name] = $column;
	}

	static function Index($name, $type, $column, $comment)
	{
		//	...
		$dsn      = self::Dsn();
		$database = self::Database();
		$table    = self::Table();

		//	...
		foreach( explode(',', $column) as $field ){
			if( empty(self::$_config[$dsn]['databases'][$database]['tables'][$table]['columns'][trim($field)]) ){
				\Notice::Set("Set index was failed. Has not been set this column. ($database, $table, $column)");
				return;
			}
		}

		//	...
		$index['name']    = $name;
		$index['type']    = $type;
		$index['column']  = $column;
		$index['comment'] = $comment;

		//	...
		switch( $type ){
			case 'ai':
			case 'pri':
			case 'pkey':
				self::$_config[$dsn]['databases'][$database]['tables'][$table]['columns'][$column]['key']   = 'pri';
				self::$_config[$dsn]['databases'][$database]['tables'][$table]['columns'][$column]['extra'] = 'auto_increment';
				break;
		}

		//	...
		self::$_config[$dsn]['databases'][$database]['tables'][$table]['indexes'][$name] = $index;
	}

	static function Charset($field, $charset)
	{
		self::Collate($field, $charset);
	}

	static function Collate($field, $collate)
	{
		//	...
		$dsn      = self::Dsn();
		$database = self::Database();
		$table    = self::Table();
		$collate  = self::_Collate($collate);
		list($charset) = explode('_', $collate);

		//	...
		if( empty(self::$_config[$dsn]['databases'][$database]['tables'][$table]['columns'][$field]) ){
			\Notice::Set("Set collate is failed. Has not been set this column. ($database, $table, $field)");
			return;
		}

		//	...
		self::$_config[$dsn]['databases'][$database]['tables'][$table]['columns'][$field]['charset']   = $charset;
		self::$_config[$dsn]['databases'][$database]['tables'][$table]['columns'][$field]['collate']   = $collate;
		self::$_config[$dsn]['databases'][$database]['tables'][$table]['columns'][$field]['collation'] = $collate;
	}

	static private function _Collate($collate)
	{
		switch( $collate ){
			case 'ascii':
				$collate = 'ascii_general_ci';
				break;

			case 'utf8':
			case 'utf-8':
				$collate = 'utf8mb4_general_ci';
				break;
		}
		return $collate;
	}
}
