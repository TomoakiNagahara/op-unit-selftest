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
 * @created   2017-12-09
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

	/** Build
	 *
	 * @var boolean
	 */
	static private $_build;

	/** Config
	 *
	 * @var boolean
	 */
	static private $_config;

	/** Store debug information.
	 *
	 * @var array
	 */
	static private $_debug = [];

	/** Store error messages.
	 *
	 * @var array
	 */
	static private $_errors = [];

	/** Failure
	 *
	 * @var boolean
	 */
	static private $_failure;

	/** Result
	 *
	 * @var array
	 */
	static private $_result;

	/** Get configuration array.
	 *
	 */
	static private function _Config($args)
	{
		//	Include configuration file.
		if( is_string($args) ){
			if( file_exists($args) ){
				$config = include($args);
			}else{
				self::Error("Does not exists this file name. ($args)");
				return;
			}
		}else{
			$config = $args;
		}

		//	Adjust structure configuration.
		foreach( $config as $dsn => &$structure ){
			//	...
			if(!preg_match('|([a-z]+)://([-_a-z0-9\.]+):([0-9]+)|', $dsn)){
				self::Error("The DSN format is wrong. ($dsn)");
				return false;
			}

			//	...
			if( empty($structure['databases']) ){
				self::Error('The "databases" key was empty in configuration array.');
				return false;
			}

			//	Adjust structure configuration.
			foreach( $structure['databases']   as $database_name => &$database ){

				//	...
				if( empty($database['tables']) ){
					self::Error('The "tables" key was empty in configuration array.');
					return false;
				}

				//	Adjust tables configuration.
				foreach( $database['tables']   as $table_name    => &$table    ){

					//	...
					if( empty($table['columns']) ){
						self::Error('The "columns" key was empty in configuration array.');
						return false;
					}

					//	Adjust columns configuration.
					foreach( $table['columns'] as $column_name   => &$define   ){
						//	...
						if( isset($define['name']) ){
							$column_name = $define['name'];
							unset($define['name']);
						}

						//	...
						$define['field'] = $column_name;

						//	...
						switch( $type = $define['type'] ){
							case 'text':
								unset($define['length']);
								break;
						}

						//	$define['index'] -- copy --> $define['key']
						if( $key = isset($define['index']) ? $define['index']: null ){
							unset($define['index']);
							$define['key'] = $key;
						}

						//	...
						if( isset( $define['key']) ){
							switch( $define['key'] ){
								case 'pri':
								case 'pkey':
								case 'primary':
									$define['key'] = 'pri';
									break;

								case 'unique':
									$define['key'] = 'uni';
									break;

								default:
									$define['key'] = 'mul';
							}
						}
					}

					//	...
					if( $column_name = ifset($table['ai']) ){
						$table['columns'][$column_name]['extra'] = 'auto_increment';
						$table['columns'][$column_name]['key']   = 'pri';
					}
				}
			}
		}

		//	...
		self::$_config = $config;

		//	...
		return $config;
	}

	/** Generate DB object from the Form.
	 *
	 * @return \OP\UNIT\DB $DB
	 */
	static function _DB()
	{
		//	...
		if( $_SERVER['REQUEST_METHOD'] === 'GET' ){
			return;
		}

		//	...
		if(!$request = \Http::Request() ){
			self::Error("Has not been submitted the Form.");
			return;
		}

		//	...
		foreach(['driver','host','port','user'] as $key){
			if(!isset($request[$key])){
				self::Error("Configuration arguments has not been set $key.");
				return;
			}
		}

		/* @var $DB \OP\UNIT\DB\DB */
		if(!$DB = self::Connect($request) ){
			self::Error("Database connection was failed.");
			return;
		}

		//	...
		return $DB;
	}

	/** Get DSN
	 *
	 */
	static function _DSN()
	{
		//	...
		$DB = self::_DB();

		//	...
		$prod = $DB->Driver();
		$host = $DB->Host();
		$port = $DB->Port();
		$dsn  = "{$prod}://{$host}:{$port}";

		//	...
		return $dsn;
	}

	/** Automatically do inspection and building.
	 *
	 * @param array $args
	 */
	static function Auto($args)
	{
		//	...
		if(!$DB = self::_DB() ){
			return;
		}

		//	...
		if(!$config = self::_Config($args) ){
			return;
		}

		//	...
		self::Inspection($config, $DB);

		//	...
		if( self::$_failure and \Http::Request(null, 'build') ){
			//	...
			Builder::Auto($config, self::$_result, $DB);

			//	...
			self::$_build   = true;
			self::$_failure = false;
			self::$_result  = [];

			//	...
			self::Inspection($config, $DB);
		}

		//	...
		if( isset($DB) ){
			self::$_debug['queries'] = $DB->GetQueries();
		}

		//	...
		if( self::$_failure === null ){
			self::$_failure  =  false;
		}
	}

	/** Inspection.
	 *
	 * @param   array      $args
	 * @param  \OP\UNIT\DB $DB
	 */
	static function Inspection($config, $DB)
	{
		//	...
		if(!\Unit::Load('SQL') ){
			return false;
		}

		//	...
		$dsn = self::_DSN();

		//	...
		if(!isset($config[$dsn]) ){
			self::Error("Unmatch DSN. ($dsn)");
			return false;
		}

		//	...
		self::Users($config[$dsn], $DB);

		//	...
		self::Structures($config[$dsn], $DB);
	}

	/** Check connection of users.
	 *
	 * @param   array      $config
	 * @param  \OP\UNIT\DB $DB
	 */
	static function Users($configs, $DB)
	{
		//	...
		$host = $DB->Host();
		$dsn  = self::_DSN();

		//	...
		if(!$sql  = \OP\UNIT\SQL\Show::User($DB) ){
			return;
		}

		//	...
		foreach( $DB->Query($sql, 'select') as $record ){
			$lists["{$record['user']}@{$record['host']}"] = $record;
		}

		//	...
		foreach( $configs['users'] as $user_name => $user ){
			//	...
			$key = $user_name.'@'.$host;

			//	...
			$result = &self::$_result[$dsn]['user'][$user_name];

			//	Check user exist.
			if( $result['exist'] = isset($lists[$key]) ){
				//	Generate mysql hashed password.
				$sql = \OP\UNIT\SQL\Select::Password($user['password'], $DB);
				$password = $DB->Query($sql, 'password');

				//	Check password match.
				$result['password'] = ($lists[$key]['password'] === $password);
			}

			//	Result
			if(!$result['result'] = ( $result['exist'] and $result['password'] ) ){
				self::$_failure = true;
			}
		}
	}

	/** Check each user connection.
	 *
	 * @param  array          $config
	 * @return \OP\UNIT\DB\DB $DB
	 */
	static function Connect($config)
	{
		//	...
		$type = $config['driver'];
		$host = $config['host'];
		$user = $config['user'];

		/* @var $DB \OP\UNIT\DB\DB */
		if(!$DB = \Unit::Factory('DB') ){
			return false;
		}

		//	...
		if(!$DB->Connect($config)){
			return false;
		}

		//	...
		return $DB;
	}

	/** Inspect structures.
	 *
	 * @param  array      $config
	 * @param \OP\UNIT\DB $DB
	 */
	static function Structures($config, $DB)
	{
		//	...
		$dsn  = self::_DSN();

		//	...
		self::Databases($DB, $config['databases'], self::$_result[$dsn]);
	}

	/** Inspect databases.
	 *
	 * @param \OP\UNIT\DB $DB
	 * @param  array      $databases
	 * @param &array      $_result
	 */
	static function Databases($DB, $databases, &$_result)
	{
		//	...
		$sql  = \OP\UNIT\SQL\Show::Database($DB);

		//	...
		if(!$list = $DB->Query($sql) ){
			return;
		}

		//	...
		foreach( $databases as $database_name => $database ){
			//	...
			$result = (array_search($database_name, $list) === false ? false: true);

			//	...
			$_result['databases'][$database_name]['result'] = $result;

			//	...
			if(!$result ){
				self::$_failure = true;
				continue;
			}

			//	...
			if( isset($database['name']) ){
				$database_name = $database['name'];
			}

			//	...
			if( empty($database_name) ){
				self::Error("Has not been set database name in the configuration.");
				return;
			}

			//	...
			self::tables($DB, $database_name, $database['tables'], $_result);
		}
	}

	/** Inspect each table.
	 *
	 * @param  \OP\UNIT\DB $DB
	 * @param   string     $database
	 * @param   string     $table
	 * @param  &array      $_result
	 * @return  boolean    $result
	 */
	static function Tables($DB, $database, $tables, &$_result)
	{
		//	...
		$sql  = \OP\UNIT\SQL\Show::Table($DB, $database);
		$list = $DB->Query($sql);

		//	...
		foreach( $tables as $table_name => $table ){
			//	...
			if( isset($table['name']) ){
				$table_name = $table['name'];
			}

			//	...
			if( empty($table_name) ){
				self::Error("Has not been set table name in the configuration.");
				return;
			}

			//	...
			$io = array_search($table_name, $list) === false ? false: true;
			$_result['tables'][$database][$table_name]['result'] = $io;
			if(!$io ){
				self::$_failure = true;
				continue;
			}

			//	...
			self::Columns($DB, $database, $table_name, ifset($table['columns'], []), $_result);
			self::Indexes($DB, $database, $table_name, ifset($table['indexes'], []), $_result);
		}

		//	...
		return $_result['tables'][$database][$table_name]['result'];
	}

	/** Inspect each column.
	 *
	 * @param  \OP\UNIT\DB $DB
	 * @param   string     $database
	 * @param   string     $table
	 * @param   array      $columns
	 * @param  &array      $_result
	 * @return  boolean    $result
	 */
	static function Columns($DB, $database, $table, $columns, &$_result)
	{
		//	...
		$sql  = \OP\UNIT\SQL\Show::Column($DB, $database, $table);
		$list = $DB->Query($sql, 'show');

		//	...
		foreach( $columns as $name => $details ){

			//	...
			$io = isset($list[$name]) ? true: false;
			$_result['columns'][$database][$table][$name]['result'] = $io;

			//	...
			if(!$io ){
				self::$_failure = true;
				continue;
			}

			//	...
			self::Details($DB, $database, $table, $name, $details, $list[$name], $_result);
		}

		//	...
		return $_result['columns'][$database][$table][$name]['result'];
	}

	/** Inspect each field define.
	 *
	 * @param  \OP\UNIT\DB $DB
	 * @param   string     $database
	 * @param   string     $table
	 * @param   string     $field
	 * @param   array      $column
	 * @param   array      $fact
	 * @param  &array      $_result
	 * @return  boolean    $result
	 */
	static function Details($DB, $database, $table, $field, $column, $fact, &$_result)
	{
		//	...
		$success = true;

		//	...
		foreach( ['type','length','null','default','extra',/*'key','privileges',*/'comment','collation'] as $key ){
			//	...
			switch( $key ){
				//	...
				case 'null':
				case 'extra':
					$io = ifset($column[$key]) == ifset($fact[$key]) ? true: false;
					break;

				//	...
				case 'collation':
					if( ifset($column[$key]) ){
						$io = ifset($column[$key]) === ifset($fact[$key]) ? true: false;
					}else{
						$io = true;
					}
					break;

				//	...
				case 'default':
					switch( $column['type'] ){
						case 'int':
							if( $column['default'] !== null ){
								$column['default'] = (string)$column['default'];
							}
						break;
					}
					$io = ifset($column[$key]) === ifset($fact[$key]) ? true: false;
					break;

				//	...
				case 'length':
					switch( $column['type'] ){
						case 'set':
						case 'enum':
							$join = [];
							foreach( explode(',', $fact['length']) as $temp ){
								$join[] = trim($temp, "'");
							}
							$fact['length'] = join(',', $join);
						break;
					}
					$io = ifset($column[$key]) === ifset($fact[$key]) ? true: false;
					break;

				default:
					$io = ifset($column[$key]) === ifset($fact[$key]) ? true: false;
			}

			//	...
			$_result['columns'][$database][$table][$field][$key]['result'] = $io;

			//	...
			if(!$io ){
				$success = false;
				self::$_failure = true;
				$_result['columns'][$database][$table][$field][$key]['detail']['current'] = ifset($fact[$key]);
				$_result['columns'][$database][$table][$field][$key]['detail']['modify']  = ifset($column[$key]);
			}
		}

		//	...
		return $success;
	}

	/** Inspect index each table.
	 *
	 * @param  \OP\UNIT\DB $DB
	 * @param   string     $database
	 * @param   string     $table
	 * @param   array      $indexes
	 * @param  &array      $_result
	 * @return  boolean    $result
	 */
	static function Indexes($DB, $database, $table, $indexes, &$_result)
	{
		//	...
		$sql  = \OP\UNIT\SQL\Show::Index($DB, $database, $table);
		$list = $DB->Query($sql);

		//	`ALTER TABLE ``t_test`` DROP PRIMARY KEY;
	}

	/** Print default html form.
	 *
	 */
	static function Form()
	{
		\Template::Run(__DIR__.'/form.phtml');
	}

	/** Return one stacked error.
	 *
	 * <pre>
	 * # How to use
	 * ```
	 * while( $error = \OP\UNIT\SELFTEST\Inspector::Error() ){
	 *   D($error);
	 * }
	 * ```
	 *
	 * </pre>
	 *
	 * @param unknown $message
	 */
	static function Error($message=null)
	{
		if( $message ){
			self::$_errors[] = $message;
		}else{
			return array_shift(self::$_errors);
		}
	}

	/** Get is build.
	 *
	 * @return boolean
	 */
	static function Build()
	{
		return self::$_build;
	}

	/** Get Configuration.
	 *
	 * @return array
	 */
	static function Config()
	{
		return self::$_config;
	}

	/** Get is failed.
	 *
	 * @return boolean
	 */
	static function Failed()
	{
		return self::$_failure;
	}

	/** Get inspection result.
	 *
	 * @return array
	 */
	static function Result()
	{
		echo '<div id="selftest-result">';
		echo json_encode(self::$_result);
		echo '</div>';
		echo '<script>';
		echo \Template::Get(__DIR__.'/result.js');
		echo '</script>';
		echo '<style>';
		echo \Template::Get(__DIR__.'/result.css');
		echo '</style>';
	}

	static function Debug()
	{
		D(__METHOD__, self::$_debug, self::$_result);
	}
}
