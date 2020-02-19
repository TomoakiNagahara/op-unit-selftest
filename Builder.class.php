<?php
/**
 * unit-selftest:/Builder.class.php
 *
 * @created   2017-12-11
 * @version   1.0
 * @package   unit-selftest
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright Tomoaki Nagahara All right reserved.
 */

/** namespace
 *
 * @created   2017-12-11
 */
namespace OP\UNIT\SELFTEST;

/** Used class
 *
 */
use Exception;
use OP\OP_CORE;
use OP\Notice;
use OP\Unit;
use OP\UNIT\Database;
use function OP\ifset;
use function OP\Hasha1;
use function OP\Json;

/** Builder
 *
 * @created   2017-12-11
 * @version   1.0
 * @package   unit-selftest
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright Tomoaki Nagahara All right reserved.
 */
class Builder
{
	/** trait
	 *
	 */
	use OP_CORE;

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

	/** Automatically.
	 *
	 * @param array $config
	 * @param array $result
	 * @param \OP\UNIT\Database $DB
	 */
	static function Auto($configs, $results, $DB)
	{
		//	...
		self::_SQL()->DB($DB);

		//	...
		$config = $DB->Config();
		$dsn    = "{$config['prod']}://{$config['host']}:{$config['port']}";

		//	...
		if(!$config = ifset($configs[$dsn]) ){
			D("empty", $dsn, $configs);
			return;
		}

		//	...
		if(!$result = ifset($results[$dsn]) ){
			D("This DNS has not been set. ($dsn)");
			return;
		}

		//	...
		try {
			//	...
			self::Database($config, $result, $DB);
			self::Table   ($config, $result, $DB);
			self::Field   ($config, $result, $DB);
			self::Column  ($config, $result, $DB);
			self::Index   ($config, $result, $DB);

			//	...
			self::User( $configs[$dsn]['users'], $result['users'], $DB);
			self::Grant($configs[$dsn]['users'], $result['users'], $DB);
		} catch ( \Throwable $e ){
			Notice::Set($e);
		}
	}

	/** Build database.
	 *
	 * @param   array      $config
	 * @param   array      $result
	 * @param   Database   $DB
	 * @throws  Exception
	 * @return  null
	 */
	static function Database($config, $result, $DB)
	{
		foreach( $result['databases'] as $database => $temp ){
			if( $temp['result'] ){
				continue;
			}

			//	...
			if( $collation = ifset($config['databases'][$database]['collation']) ){
				//	...
				if( empty($config['databases'][$database]['collate']) ){
					$config['databases'][$database]['collate'] = $collation;
				}

				//	...
				if( empty($config['databases'][$database]['charset']) ){
					list( $config['databases'][$database]['charset']) = explode('_', $collation);
				}
			}

			//	...
			$charset = ifset($config['databases'][$database]['charset']) ?? null;
			$collate = ifset($config['databases'][$database]['collate']) ?? null;

			//	...
			$conf = [];
			$conf['database'] = $database;
			$conf['charset']  = $charset;
			$conf['collate']  = $collate;
		//	$sql = \OP\UNIT\SQL\Database::Create($conf, $DB);
			$sql = self::_SQL()->Create()->Database($conf, $DB);
			if(!$DB->Query($sql) ){

				D($database, $config['databases'][$database]);

				throw new Exception(Notice::Get()['message']);
			}

			//	Overwrite result. All table build. (Is this neccessary?)
			/*
			foreach( $config['databases'][$database]['tables'] as $table => $conf ){
				$result['tables'][$database][$table]['result'] = false;
			}
			*/
		}
	}

	/** Build tabel.
	 *
	 * @param   array      $config
	 * @param   array      $result
	 * @param   Database   $DB
	 * @return  null
	 */
	static function Table($configs, $result, $DB)
	{
		//	...
		if( empty($result['tables']) ){
			//	Adjust table result.
			foreach( $configs['databases'] as $database_name => $database ){
				//	...
				foreach( $database['tables'] as $table_name => $table ){
					/*
					//	...
					if( $result['databases'][$database_name]['result'] ){
						continue;
					};
					*/

					//	...
					$result['tables'][$database_name][$table_name]['result'] = false;
				};
			};
		};

		//	...
		foreach( $result['tables'] as $database => $tables ){
			foreach( $tables as $table => $temp ){
				if( $temp['result'] ?? null ){
					continue;
				}

				//	...
				if(!$args = $configs['databases'][$database]['tables'][$table] ?? null ){
					continue;
				};

				//	...
				$args['database'] = $database;
				$args['table']    = $table;

				//	...
				if(!$sql = self::_SQL()->DDL()->Create()->Table($args, $DB) ){
					throw new Exception("Failed: $sql");
				}

				//	...
				if(!$io  = $DB->Query($sql, 'create') ){
					throw new Exception("Failed: $io");
				}
			}
		}
	}

	/** Build new field.
	 *
	 * @param  array     $config
	 * @param  array     $result
	 * @param  Database  $DB
	 */
	static function Field($configs, &$results, $_db)
	{
		//	...
		foreach( ifset($results['fields'], []) as $database => $tables ){
			//	...
			foreach( $tables as $table => $columns ){
				//	...
				$first = true;
				$after = null;

				//	...
				foreach( $columns as $field => $column ){
					//	...
					$config = $configs['databases'][$database]['tables'][$table]['columns'][$field];

					//	...
					if( $first ){
						$first = false;
						$config['first'] = true;
					}else{
						$config['after'] = $after;
					}

					//	...
					$after = $field;

					//	Create new column.
					if(!$column['result'] ){
						//	...
						$config['database'] = $database;
						$config['table']    = $table;
						$config['field']    = $field;
						$sql = self::_SQL()->DDL()->Create()->Column($config);

						//	...
						if(!$_db->Query($sql, 'alter') ){
							continue;
						}

						//	...
						if(($config['extra'] ?? null) !== 'auto_increment' ){
							continue;
						};

						//	Touch primary key index result.
						$results['indexes'][$database][$table][$field]['result'] = true;
					};
				};
			};
		};
	}

	/** Modify exist column.
	 *
	 * @param	 array			  $config
	 * @param	 array			  $result
	 * @param	\OP\UNIT\Database $DB
	 */
	static function Column($config, &$result, $_db)
	{
		//	...
		foreach( $result['columns'] ?? [] as $database => $tables ){
			//	...
			foreach( $tables as $table => $columns ){
				//	...
				foreach( $columns as $name => $column ){
					//	Change each column.
					foreach( $column as $field => $value ){
						//	...
						if( $value['result'] ){
							continue;
						}

						//	Change is modify.
						$conf = $config['databases'][$database]['tables'][$table]['columns'][$name];
						$conf['database'] = $database;
						$conf['table']    = $table;

						//	...
						$sql = self::_SQL()->DDL()->Alter()->Column($conf);
						$io  = $_db->Query($sql, 'alter');

						//	...
						if( false ){
							D($field, $io);
						};

						//	...
						break;
					}
				}
			}
		}
	}

	/** Build index.
	 *
	 * @param  array     $config
	 * @param  array     $result
	 * @param  Database  $DB
	 */
	static function Index($_configs, &$_results, $DB)
	{
		//	...
		foreach( ($_results['indexes'] ?? []) as $database_name => $tables ){
			//	...
			foreach( $tables as $table_name => $indexes ){
				//	...
				foreach( $indexes as $index_name => $result ){
					//	...
					if( $result['result'] ){
						continue;
					};

					//	...
					$config = $_configs['databases'][$database_name]['tables'][$table_name]['indexes'][$index_name];

					//	...
					$config['database'] = $database_name;
					$config['table']    = $table_name;

					//	...
					$sql = self::_SQL()->DDL()->Create()->Index($config);

					//	...
					$DB->Query($sql, 'alter');
				};
			};
		};
	}

	/** Build user.
	 *
	 * @param array $configs
	 * @param array $results
	 * @param Database $DB
	 */
	static function User($configs, $results, $DB)
	{
		//	...
		foreach( $results as $user => $result ){
			//	...
			$host = $configs[$user]['host'];

			//	...
			$config = [];
			$config['host'] = $host;
			$config['user'] = $user;

			//	...
			if(!ifset($result['exist']) ){
				//	...
				if(!$qu = self::_SQL()->DDL()->Create()->User($config) ){
					throw new Exception("Failed: $qu");
				};

				//	...
				if(!$io = $DB->Query($qu) ){
					throw new Exception("Failed: $qu");
				};
			};

			//	...
			if(!ifset($result['password']) ){
				//	...
				$config = [];
				$config['host'] = $host;
				$config['user'] = $user;
				$config['password'] = $configs[$user]['password'];

				//	...
				if(!$qu = self::_SQL()->DDL()->Create()->Password($config) ){
					throw new Exception("Failed: $qu");
				};

				//	...
				if(!$io = $DB->Query($qu) ){
					throw new Exception("Failed: $qu ($io)");
				};
			};

			//	...
			if(!ifset($result['privilege']) ){
				//	...
				$config['database']  = '*';
				$config['table']     = '*';
				$config['privileges']= 'USAGE';

				//	USAGE
				if(!$qu = self::_SQL()->DCL()->Grant()->Privilege($config) ){
					throw new Exception("Failed: $qu");
				};

				//	...
				if(!$io = $DB->Query($qu) ){
					throw new Exception("Failed: $qu ($io)");
				};
			};

			/**
			 * CREATE USER 'user_name'@'localhost' IDENTIFIED WITH mysql_native_password;
			 * GRANT USAGE ON *.* TO 'user_name'@'localhost';
			 * SET PASSWORD FOR 'user_name'@'localhost' = PASSWORD('***');
			 */
		}
	}

	/** Add missing grant.
	 *
	 * @param  array     $result
	 * @param  Database  $DB
	 */
	static function Grant($configs, $results, $DB)
	{
		//	...
		foreach( $results as $user => $result ){
			//	...
			if( $result['privilege'] ?? null ){
				continue;
			};

			//	...
			$host = $configs[$user]['host'];

			//	...
			if( empty($configs[$user]['privilege']) ){
				Notice::Set("privilege config is empty. ($user)");
				return false;
			};

			//	...
			foreach( $configs[$user]['privilege'] as $database => $tables ){
				/*
				//	...
				$sql        = self::_SQL()->DDL()->Show()->Table(['database'=>$database]);
				$table_list = $DB->Query($sql);
				*/

				//	...
				foreach( $tables as $table_names => $privileges ){
					//	...
					foreach( explode(',', str_replace(' ', '', $table_names )) as $table_name ){
						/*
						//	...
						if( array_search($table_name, $table_list) === false ){
							continue;
						};
						*/

						//	...
						foreach( $privileges as $privilege => $column ){
							//	...
							$config = [];
							$config['host']      = $host;
							$config['user']      = $user;
							$config['database']  = $database;
							$config['table']     = $table_name;
							$config['privileges']= $privilege;
							$config['field']     = $column;

							//	...
							$qu = self::_SQL()->DCL()->Grant()->Privilege($config);
							$DB->Query($qu);
						};
					}; // table names
				}; // privileges
			}; // tables
		}; // users
	}
}
