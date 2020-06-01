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

/** Used class
 *
 */
use OP\OP_CORE;
use OP\OP_SESSION;
use OP\Notice;
use OP\UNIT\Database;
use OP\Unit;
use function OP\ifset;
use function OP\Hasha1;
use function OP\Json;

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
	use OP_CORE, OP_SESSION;

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

	/** Get SQL Object.
	 *
	 * @created  2019-04-09
	 * @return  \OP\UNIT\SQL
	 */
	static private function _SQL()
	{
		//	...
		static $_SQL;

		//	...
		if(!$_SQL ){
			$_SQL = Unit::Instantiate('SQL');
		};

		//	...
		return $_SQL;
	}

	/** Get configuration array.
	 *
	 */
	static private function _Config($args)
	{
		//	Include configuration file.
		if( is_string($args) ){
			//	...
			if(!file_exists($args) ){
				throw new \Exception("Does not exists this file name. ($args)");
			};

			//	...
			$config = call_user_func(function($path){
				return include($path);
			}, $args);

			//	...
			if(!is_array($config) ){
				throw new \Exception("Empty return value. ($args)");
			};
		}else{
			$config = $args;
		}

		//	Adjust structure configuration.
		foreach( $config ?? [] as $dsn => &$structure ){
			//	...
			if(!preg_match('|([a-z]+)://([-_a-z0-9\.]+):([0-9]+)??(.+)?|', $dsn)){
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
						switch( $type = $define['type'] ?? null ){
							case null:
								D("type is null. ($database_name, $table_name, $column_name, $type)");
								break;

							case 'text':
								unset($define['length']);
								break;

							default:
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
					if( $column_name = $table['ai'] ?? null ){
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

	/** Get DSN
	 *
	 */
	static function _DSN($config)
	{
		return "{$config['prod']}://{$config['host']}:{$config['port']}";
	}

	/** Automatically do inspection and building.
	 *
	 * @param	 array		 $args
	 * @param	Database $DB
	 */
	static function Auto($args, $DB=null)
	{
		//	...
		self::$_failure = false;

		//	...
		if(!$config = self::_Config($args) ){
			return;
		}

		//	...
		if( $DB === null ){
			//	...
			if(!$DB = self::DB() ){
				self::$_failure = true;
				return !self::Failed();
			};

			//	...
			if(!empty($_POST) ){
				$conf = [];
				foreach( ['prod','host','port','user','password','charset',] as $key ){
					$conf[$key] = $_POST[$key] ?? null;
				};

				//	...
				$DB->Connect($conf);
			};

			//	...
			if(!$DB->isConnect() ){
				self::Form();
				self::$_failure = true;
				return !self::Failed();
			};
		};

		//	...
		self::_SQL()->DB($DB);

		//	...
		self::Inspection($config, $DB);

		//	Check build value.
		if( $build = $_POST['build'] ?? false ){
			$build = $build === self::Session('build') ? true: false;
		}

		//	Change build value.
		self::Session('build', Hasha1(microtime()));

		//	...
		if( self::$_failure and $build ){
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
		if( self::$_failure ){
			self::Form();
		};

		//	...
		return !self::Failed();
	}

	/** Inspection.
	 *
	 * @param   array      $args
	 * @param  Database $DB
	 */
	static function Inspection($config, $DB)
	{
		//	...
		$dsn = self::_DSN( $DB->Config() );

		//	...
		if(!isset($config[$dsn]) ){
			//	...
			$dsns = [];

			//	...
			foreach( $config as $tmp => $temp ){
				$dsns[] = $tmp;
			}

			//	...
			$dsns = join(', ', $dsns);

			//	...
			self::Error("DSN not match. ($dsn --> $dsns)");
			self::$_failure = true;
			return false; D($temp);
		}

		//	...
		self::Users($config[$dsn], $DB);

		//	...
		self::Structures($config[$dsn], $DB);

		//	...
		return !self::$_failure;
	}

	/** Check connection of users.
	 *
	 * @param   array      $config
	 * @param  Database $DB
	 */
	static function Users($configs, $DB)
	{
		//	...
		$host = $DB->Config()['host'];
		$dsn  = self::_DSN($DB->Config());
		$lists = [];

		//	...
		if(!$sql  = self::_SQL()->DDL()->Show()->User([], $DB) ){
			return;
		}

		//	...
		foreach( $DB->Query($sql, 'select') as $record ){
			$host = $record['host'];
			$user = $record['user'];
			$lists["{$user}@{$host}"] = $record;
		}

		//	...
		foreach( $configs['users'] as $user_name => $user ){
			//	...
			$name = $user['name'] ?? null;
			$host = $user['host'] ?? null;

			//	...
			if( !$host or !$name ){
				throw new \Exception("Has not been set user name or host name. ({$name}@{$host})");
				return;
			};

			//	...
			$key = $name.'@'.$host;

			//	...
			$result = &self::$_result[$dsn]['users'][$user_name];

			//	...
			$result['result']    = false;
			$result['exist']     = null;
			$result['password']  = null;
			$result['privilege'] = null;

			//	Check user exist.
			if(!$result['exist'] = isset($lists[$key]) ){
				self::$_failure = true;
				continue;
			};

			//	Generate mysql hashed password.
			$sql = self::_SQL()->DDL()->Show()->Password(['password'=>$user['password']]);
			$password = $DB->Query($sql, 'password');

			//	Check password match.
			if(!$result['password'] = ($password === ($lists[$key]['password'] ?? null)) ){
				$result['modify']   = $user['password'];
				self::$_failure = true;
				continue;
			};

			//	Privilege
			if(!$result['privilege'] = self::Privilege($DB, $host, $user_name, $configs, self::$_result[$dsn]) ){
				self::$_failure = true;
				continue;
			}

			//	...
			$result['result'] = true;

			//	for Eclipse.
			if( false ){
				D($result);
			};
		};
	}

	/** Check privilege.
	 *
	 * @param	Database $DB
	 * @param	 string		 $host
	 * @param	 string		 $user
	 * @param	 array		 $configs
	 * @param	 array		 $result
	 */
	static function Privilege($DB, $host, $user, $configs, &$results)
	{
		//	Set default value is true.
		$success = true;

		//	Get current setting.
		$sql  = self::_SQL()->DDL()->Show()->Grants(['host'=>$host, 'user'=>$user]);
		$real = $DB->Query($sql, 'show');

		//	USAGE only.
		if( (count($real) === 1) and ($real['*']['*'][0] === 'USAGE') ){
			$results['privileges'][$user][$host] = false;
			return false;
		};

		//	Loop to each databases.
		foreach( $configs['users'][$user]['privilege'] as $database => $databases ){

			//	Check if all privileges
			if( $real[$user] ?? null ){
				//	Check all tables.
				if( $real[$user]['*'] ?? null ){
					//	ALL PRIVILEGES
					if( array_search('ALL PRIVILEGES', $real[$user]['*']) !== false ){
						return $success;
					}
				}
			}

			//	Loop to each tables.
			foreach( $databases as $tables => $privileges ){

				//	Separate to each tables.
				foreach( explode(',',$tables) as $table ){
					//	Reference for eash to readable.
					$result = &$results['privileges'][$user][$host][$database][$table];

					//	Set default is false.
					$result['result'] = false;

					//	Does not exists table privileges.
					if(!$result['exist'] = isset($real[$database][$table]) ){
						$success = false;
						continue;
					};

					//	Loop to each columns.
					foreach( $privileges as $privilege => $columns ){
						//	...
						$base = explode(',',strtoupper($privilege));
						$comm = array_intersect( $base, $real[$database][$table] );
						$diff = array_diff($base, $comm);

						//	Not match count.
						if( count($diff) !== 0 ){
							$success = false;
							continue;
						};

						//	Match all.
						$result['result']  = true;
						$result['columns'] = $columns;

						//	For Eclipse Notice.
						if( false ){
							D($result);
						};
					};
				};
			};
		};

		//	...
		return $success;
	}

	/** Inspect structures.
	 *
	 * @param  array      $config
	 * @param Database $DB
	 */
	static function Structures($config, $DB)
	{
		//	...
		$dsn  = self::_DSN($DB->Config());

		//	...
		self::Databases($DB, $config['databases'], self::$_result[$dsn]);
	}

	/** Inspect databases.
	 *
	 * @param	Database $DB
	 * @param	 array		 $databases
	 * @param	&array		 $_result
	 */
	static function Databases($DB, $databases, &$_result)
	{
		//	...
		$sql = self::_SQL()->DDL()->Show()->Database();

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
				continue;
			}

			//	...
			if(!self::tables($DB, $database_name, $database['tables'], $_result) ){
				self::$_failure = true;
			};
		}
	}

	/** Inspect each table.
	 *
	 * @param	Database $DB
	 * @param	 string		 $database
	 * @param	 string		 $table
	 * @param	&array		 $_result
	 * @return	 boolean	 $result
	 */
	static function Tables($DB, $database, $tables, &$_result)
	{
		//	...
		$sql  = self::_SQL()->DDL()->Show()->Table(['database'=>$database]);
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
				continue;
			}

			//	...
			$_result['tables'][$database][$table_name]['result'] = (array_search($table_name, $list) === false) ? false: true;
			if(!$_result['tables'][$database][$table_name]['result'] ){
				continue;
			}

			//	...
			if(!self::Fields( $DB, $database, $table_name, ($table['columns'] ?? []), $_result) ){
				self::$_failure = true;
			};

			//	...
			if(!self::Indexes($DB, $database, $table_name, ($table['indexes'] ?? []), $_result) ){
				self::$_failure = true;
			};
		}

		//	...
		if(!$_result['tables'][$database][$table_name]['result'] ){
			self::$_failure = true;
		};

		//	...
		return $_result['tables'][$database][$table_name]['result'];
	}

	/** Inspect each fields.
	 *
	 * @param  Database $DB
	 * @param   string     $database
	 * @param   string     $table
	 * @param   array      $columns
	 * @param  &array      $_result
	 * @return  boolean    $result
	 */
	static function Fields($DB, $database, $table, $columns, &$_result)
	{
		//	Get fields list.
		$sql  = self::_SQL()->DDL()->Show()->Column(['database'=>$database, 'table'=>$table]);
		$list = $DB->Query($sql, 'show');

		//	Each fields.
		foreach( $columns as $name => $details ){

			//	If field exist.
			$io = isset($list[$name]) ? true: false;
			$_result['fields'][$database][$table][$name]['result'] = $io;

			//	If not exist.
			if(!$io ){
				self::$_failure = true;
				continue;
			}

			//	Field is exist.
			self::Columns($DB, $database, $table, $name, $details, $list[$name], $_result);
		}

		//	Check leftover field name.
		foreach( array_keys(array_diff_key($list, $columns)) as $field ){
			$_result['fields'][$database][$table][$field]['result'] = null;
		}

		//	...
		return $_result['fields'][$database][$table][$name]['result'];
	}

	/** Inspect each columns.
	 *
	 * @param  Database $DB
	 * @param   string     $database
	 * @param   string     $table
	 * @param   string     $field
	 * @param   array      $column
	 * @param   array      $fact
	 * @param  &array      $_result
	 * @return  boolean    $result
	 */
	static function Columns($DB, $database, $table, $field, $column, $fact, &$_result)
	{
		//	...
		$success = true;

		//	...
		foreach( ['type','length','unsigned','null','default','extra',/*'key','privileges',*/'comment','collation'] as $key ){
			//	...
			$io = true;

			//	...
			switch( $key ){
				case 'unsigned':
					if( isset($fact[$key]) or isset($column[$key]) ){
						$io = (ifset($column[$key]) ? true: false) === (ifset($fact[$key])   ? true: false) ? true: false;
					}else{
						continue 2;
					}
					break;

				//	...
				case 'null':
					//	...
					if( isset($column[$key]) ){
						$io = ($column[$key] === $fact[$key]) ? true: false;
					}else{
						$io = $fact[$key] ? true: false;
					};
					break;

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
					//	If type of integer.
					if( strpos($column['type'], 'int') !== false ){
						//	Convert to string from integer, If not null.
						if( $column['default'] !== null ){
							$column['default'] = (string)$column['default'];
						}
					};

					//	...
					$io = ($column[$key] ?? '') === ($fact[$key] ?? '') ? true: false;

					//	...
					if(!$io ){
					//	D($field, $key, $column, $fact);
					}

					break;

				//	...
				case 'length':
					switch( $column['type'] ){
						case 'float':
							continue 3;

						case 'set':
						case 'enum':
							$join = [];
							foreach( explode(',', $fact['length']) as $temp ){
								$join[] = trim($temp, "'");
							}
							$fact['length'] = join(',', $join);

							//	...
							$join = [];
							foreach( explode(',', $column[$key]) as $temp ){
								$join[] = trim($temp);
							};
							$column[$key] = join(',', $join);
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
				$_result['columns'][$database][$table][$field][$key]['current'] = ifset($fact[$key]);
				$_result['columns'][$database][$table][$field][$key]['modify']  = ifset($column[$key]);
			}
		}

		//	...
		return $success;
	}

	/** Inspect index each table.
	 *
	 * @param	Database $DB
	 * @param	 string		 $database
	 * @param	 string		 $table
	 * @param	 array		 $indexes
	 * @param	&array		 $_result
	 * @return	 boolean	 $result
	 */
	static function Indexes($DB, $database, $table, $_configs, &$_result)
	{
		//	...
		$sql  = self::_SQL()->DDL()->Show()->Index(['database'=>$database, 'table'=>$table]);
		$real = $DB->Query($sql);

		//	...
		$success = true;

		//	Left over.
		foreach(array_diff(array_keys($_configs), array_keys($real)) as $index_name){
			$_result['indexes'][$database][$table][$index_name]['result'] = false;
		}

		//	...
		foreach( $_configs as $index_name => $index ){
			//	...
			$io = null;
			$_result['indexes'][$database][$table][$index_name]['result'] = false;

			//	Check if exists index at current table.
			if( empty($real[$index_name]) ){
				$_result['indexes'][$database][$table][$index_name]['exists'] = false;
				$success = false;
				continue;
			}

			//	...
			switch( $type = strtolower($index['type']) ){
				//	PRIMARY KEY
				case 'ai':
				case 'pkey':
				case 'primary':
					if( isset($real['PRIMARY']) ){
						$io = (join(',',$real['PRIMARY']['columns'])) === $index['column'];
					};
					break;

				case 'index':
					$io = (($real[$index_name]['primary'] === false) and ($real[$index_name]['unique'] === false));
					break;

				case 'unique':
					if( $io = isset($real[$index_name]['unique']) ){
						$io = $real[$index_name]['unique'];
					};
					break;

				default:
					Notice::Set("Has not been support this type. ($type)");
			};

			//	...
			if(!$io ){
				$current = $real[$index_name]['unique'] ? 'unique': 'multiple';
				$_result['indexes'][$database][$table][$index_name]['type']    = false;
				$_result['indexes'][$database][$table][$index_name]['modify']  = $type;
				$_result['indexes'][$database][$table][$index_name]['current'] = $current;
				$success = false;
				continue;
			}

			//	...
			if( is_string($index['column']) ){
				$index['column'] = explode(',', $index['column']);
			}

			//	...
			foreach( $index['column'] as $field_name ){
				//	...
				$field_name = trim($field_name);

				//	...
				$io = (array_search($field_name, $real[$index_name]['columns']) !== false) ? true: false;

				//	...
				$_result['indexes'][$database][$table][$index_name]['field'][$field_name] = $io;

				//	...
				if(!$io ){
					//	...
					$success = false;
				}
			}

			//	...
			$_result['indexes'][$database][$table][$index_name]['result'] = $success;

			//	...
			continue;

			//	...
			if( is_array($index['column']) ){
				$index['column'] = join(',', $index['column']);
			}else if( is_string($index['column']) ){
				$index['column'] = str_replace(' ', '', $index['column']);
			};

			//	...
			if( ($_result['indexes'][$database][$table][$index_name]['result'] = $io) ){
				continue;
			};

			//	...
			$_result['indexes'][$database][$table][$index_name]['column'] = $index['column'];
			$_result['indexes'][$database][$table][$index_name]['type']   = $index['type'];

			//	...
			$success = false;
		};

		//	...
	//	$_result['tables'][$database][$table]['result'] = $success;
		$_result['tables'][$database][$table]['index']  = $success;

		//	...
		return $success;
	}

	/** Display default form.
	 *
	 */
	static function Form()
	{
		//	...
		$build = self::Session('build');

		//	...
		Unit::Instantiate('WebPack')->Js( __DIR__.'/form');
		Unit::Instantiate('WebPack')->Css(__DIR__.'/form');

		//	...
		Unit::Instantiate('App')->Template(__DIR__.'/form.phtml', ['build'=>$build]);
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
	 * @param	 string		 $message
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

	/** Is inspection result.
	 *
	 * @return boolean
	 */
	static function isResult()
	{
		return !self::$_failure;
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
		//	...
		if( self::$_result ){
			Json(self::$_result, '#OP_SELFTEST');
		}else{
			self::Error('Inspection has not been execute.');
		};

		//	...
		if( $webpack = \OP\Unit::Instantiate('WebPack') ){
			$webpack->Js( __DIR__.'/result');
			$webpack->Css(__DIR__.'/result');
		};
	}

	static function Help()
	{

	}

	/** For developers
	 *
	 */
	/*
	static function Debug()
	{
		//	...
		$debug = null;

		//	...
		while( $error = self::Error() ){
			$debug['Error'][] = $error;
		};

		//	...
		$debug['result'] = self::$_result;

		//	...
		D( $debug );
	}
	*/
}
