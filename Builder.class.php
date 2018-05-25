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
	use \OP_CORE;

	/** Automatically.
	 *
	 * @param array $config
	 * @param array $result
	 * @param \OP\UNIT\DB $DB
	 */
	static function Auto($configs, $results, $DB)
	{
		//	...
		$prod = $DB->Driver();
		$host = $DB->Host();
		$port = $DB->Port();
		$dsn  = "{$prod}://{$host}:{$port}";

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
			self::Column  ($config, $result, $DB);
			self::Index   ($config, $result, $DB);

			//	...
			self::User($configs[$dsn]['users'], $result['users'], $DB);
			self::Grant($result['users'], $DB);
		} catch ( \Throwable $e ){
			\Notice::Set($e);
		}
	}

	/** Build database.
	 *
	 * @param   array      $config
	 * @param   array      $result
	 * @param  \OP\UNIT\DB $DB
	 * @throws \Exception
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
			$sql = \OP\UNIT\SQL\Database::Create($DB, $database, $charset, $collate);
			if(!$DB->Query($sql) ){

				D($database, $config['databases'][$database]);

				throw new \Exception(\Notice::Get()['message']);
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
	 * @param  \OP\UNIT\DB $DB
	 * @return  null
	 */
	static function Table($configs, $result, $DB)
	{
		//	...
		foreach( $result['tables'] as $database => $tables ){
			foreach( $tables as $table => $temp ){
				if( $temp['result'] ){
					continue;
				}

				//	...
				$args = $configs['databases'][$database]['tables'][$table];
				$args['database'] = $database;
				$args['table']    = $table;
				if( $sql = \OP\UNIT\SQL\Table::Create($args, $DB) ){
					$io  = $DB->Query($sql, 'create');
				}
			}
		}
	}

	/** Build column.
	 *
	 * @param array $config
	 * @param array $result
	 * @param \OP\UNIT\DB $DB
	 */
	static function Column($config, &$result, $_db)
	{
		//	...
		foreach( ifset($result['columns'], []) as $database => $tables ){
			//	...
			foreach( $tables as $table => $columns ){
				//	...
				$first = true;
				$after = null;

				//	...
				foreach( $columns as $name => $column ){
					//	...
					$conf = $config['databases'][$database]['tables'][$table]['columns'][$name];

					//	...
					if( $first ){
						$first = false;
						$conf['first'] = true;
					}else{
						$conf['after'] = $after;
					}

					//	...
					$after = $name;

					//	Create new column.
					if(!$column['result'] ){
						//	...
						$sql = \OP\UNIT\SQL\Column::Create($database, $table, $name, $conf, $_db);
						$io  = $_db->Query($sql, 'alter');

						//	...
						continue;
					}

					//	...
					unset($column['result']); // Why?
					$fail = null;

					//	Change each column.
					foreach( $column as $field => $value ){
						if( $value['result'] === false ){
							//	key is index
							if( $field === 'key' ){
								$result['indexes'][$database][$table][$name] = $value['detail']['modify'];
							}else{
								$fail = true;
								break;
							}
						}
					}

					//	Update already exists column.
					if( $fail ){
						//	Change is modify.
						$sql = \OP\UNIT\SQL\Column::Change($database, $table, $name, $conf, $_db);
						$io  = $_db->Query($sql, 'alter');
					}
				}
			}
		}
	}

	/** Build index.
	 *
	 * @param array $config
	 * @param array $result
	 * @param \OP\UNIT\DB $DB
	 */
	static function Index($config, &$result, $DB)
	{
		//	...
		foreach( ifset($result['indexes'], []) as $database => $tables ){
			//	...
			$database = $DB->Quote($database);

			//	...
			foreach( $tables as $table => $indexes ){
				//	...
				$table = $DB->Quote($table);

				//	...
				foreach( $indexes as $column => $index ){
					//	...
					$column = $DB->Quote($column);

					//	...
					$index = $index === 'uni' ? 'UNIQUE': 'INDEX';

					//	...
					$modifier = 'ADD';

					//	...
					$sql = "ALTER TABLE {$database}.{$table} {$modifier} $index($column)";
					$io  = $DB->Query($sql, 'alter');
				}
			}
		}
	}

	/** Build user.
	 *
	 * @param array $configs
	 * @param array $results
	 * @param \OP\UNIT\DB $DB
	 */
	static function User($configs, $results, $DB)
	{
		//	...
		$host = $DB->Host();

		//	...
		foreach( $results as $user => $result ){
			//	...
			$config = [];
			$config['host'] = $host;
			$config['user'] = $user;

			//	...
			if(!ifset($result['exist']) ){
				//	...
				if( $qu = \OP\UNIT\SQL\User::Create($config, $DB) ){
					$io = $DB->Query($qu);

					//	...
					$config['database']  = '*';
					$config['table']     = '*';
					$config['privileges']= 'USAGE';

					//	USAGE
					if( $qu = \OP\UNIT\SQL\Grant::Privilege($config, $DB) ){
						$io = $DB->Query($qu);
					}

					//	...
					foreach( $configs[$user]['privilege'] as $database => $tables ){
						foreach( $tables as $table => $privileges ){
							foreach( $privileges as $privilege => $column ){
								$config = [];
								$config['host']      = $host;
								$config['user']      = $user;
								$config['database']  = $database;
								$config['table']     = $table;
								$config['privileges']= $privilege;
								$config['column']    = $column;

								//	...
								if( $qu = \OP\UNIT\SQL\Grant::Privilege($config, $DB) ){
									$io = $DB->Query($qu);
								}
							}
						}
					}
				}
			}

			//	...
			if(!ifset($result['password']) ){
				//	...
				$config = [];
				$config['host'] = $host;
				$config['user'] = $user;
				$config['password'] = $configs[$user]['password'];
				if( $qu = \OP\UNIT\SQL\User::Password($config, $DB) ){
					$io = $DB->Query($qu);
				}
			}

			/**
			 * CREATE USER 'user_name'@'localhost' IDENTIFIED WITH mysql_native_password;
			 * GRANT USAGE ON *.* TO 'user_name'@'localhost';
			 * SET PASSWORD FOR 'user_name'@'localhost' = PASSWORD('***');
			 */
		}
	}

	/** Add missing grant.
	 *
	 * @param	 array		 $result
	 * @param	\OP\UNIT\DB	 $DB
	 */
	static function Grant($results, $DB)
	{
		//	...
		foreach( $results as $user => $result ){
			//	...
			if( ifset($result['privileges']) === true ){
				continue;
			}

			//	...
			$host = $DB->Host();

			//	...
			if( empty($result['privileges']) ){
				continue;
			}

			//	...
			foreach( $result['privileges'] as $database => $tables ){
				foreach( $tables as $table => $privileges ){
					foreach( $privileges as $privilege => $fields ){
						//	...
						if( $fields !== '*' ){
							D('Un support each fields yet.', $fields);
							continue;
						}

						//	...
						$config = [];
						$config['host']      = $host;
						$config['user']      = $user;
						$config['database']  = $database;
						$config['table']     = $table;
						$config['privileges']= $privilege;

						//	...
						if( $qu = \OP\UNIT\SQL\Grant::Privilege($config, $DB) ){
							$io = $DB->Query($qu);
						}
					}
				}
			}
		}
	}
}