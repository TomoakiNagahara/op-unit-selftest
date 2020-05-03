/**
 * unit-selftest:/result.js
 *
 * @creation  2018-04-30
 * @version   1.0
 * @package   unit-selftest
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright Tomoaki Nagahara All right reserved.
 */
//	...
setTimeout(function(){
	//	...
	var text = null;
	var area = document.querySelector('#OP_SELFTEST');
	if( area ){
		text = area.innerText;
	};

	//	...
	if(!text ){
		return;
	};

	//	...
	area.innerText = '';

	//	...
	var json = JSON.parse(text);

	//	...
	var roots = document.createElement('ul');
	var div   = document.createElement('div');
		div.appendChild(roots);

	//	...
	area.appendChild(div);

	//	...
	for(var dsn in json ){
		//	...
		var root = document.createElement('li');
			root.innerText = dsn;

		//	...
		roots.appendChild(root);

		//	...
		var list = document.createElement('ul');
		root.appendChild(list);

		//	...
		__user(list, json[dsn]['users']);

		//	...
		__privilege(list, json[dsn]['privileges']);

		//	...
		__database(list, json[dsn]['databases']);

		//	...
		__tables(list, json[dsn]['tables']);

		//	...
		__fields(list, json[dsn]['fields']);

		//	...
		__columns(list, json[dsn]['columns']);

		//	...
		__indexes(list, json[dsn]['indexes']);
	}

	//	...
	function __user( list, json ){
		//	...
		var item = document.createElement('li');
		list.appendChild(item);

		//	...
		var span = document.createElement('span');
			span.innerText = 'Users';
		item.appendChild(span);

		//	...
		var list = document.createElement('ol');
			list.classList.add('user');
		item.appendChild(list);

		//	...
		for(var user in json ){
			//	...
			var result = json[user]['result'];
			var color  = result ? 'success':'error';

			//	...
			var name   = document.createElement('span');
			var error  = document.createElement('span');
			var modify = document.createElement('span');
			var lack   = document.createElement('span');
			var item   = document.createElement('li');
				item.classList = color;
				item.appendChild(name);
				item.appendChild(error);
				item.appendChild(lack);
				item.appendChild(modify);
				item.dataset.user = user;
			list.appendChild(item);

			//	...
			name.innerText = user;
			name.classList = 'name';

			//	...
			lack.classList.add('lack');

			//	...
			error.classList.add('error');
			if(!json[user]['exist']    ){ error.classList.add('exist')    }else
			if(!json[user]['password'] ){ error.classList.add('password') }else
			if(!json[user]['privilege']){ error.classList.add('privilege')};

			//	...
			if( json[user]['modify'] ){
				modify.innerText = json[user]['modify'];
				modify.classList.add('modify');
			};
		}
	}
	function __privilege(root, json){
		//	...
		var area = document.createElement('div');
		var ol   = document.createElement('ol');
		var p    = document.createElement('p');
			p.innerText = 'privilege';
			area.appendChild(p);
			area.appendChild(ol);

		//	...
		var fail = false;

		//	...
		for(var user in json ){
			for(var host in json[user]){
				//	...
				var ol = document.createElement('ol');

				//	...
				for(var table in json[user][host]){
					var result = json[user][host][table]['result'];
					var exist  = json[user][host][table]['exist'];

					//	...
					if( result && exist ){
						continue;
					};

					//	...
					var li = document.createElement('li');
						li.innerText = table;
					ol.appendChild(li);
				};

				//	...
				if( ol.childElementCount ){
					root.querySelector('[data-user="'+user+'"]').appendChild(ol);
				};
			};
		};

		//	...
		if( fail ){
			root.appendChild(fail);
		};
	};

	//	...
	function __database(root, json){
		//	...
		var item = document.createElement('li');
			item.innerText = 'Databases';
		root.appendChild(item);

		//	...
		var list = document.createElement('ol');
		item.appendChild(list);

		//	...
		for(var database in json ){
			var result = json[database]['result'];
			var color  = result ? 'success':'error';
			var li = document.createElement('li');
				li.classList = color;
				li.innerText = database;
				li.dataset.database = database;
			list.appendChild(li);

			//	...
			if( json[database]['result'] ){
				continue;
			}

			//	...
			for(var key in json[database] ){
				if(!json[database][key] ){
					D(key, false);
				}
			}
		}
	}

	//	...
	function __tables(root, json){
		//	...
		for(var database in json ){
			//	...
			var ol = document.createElement('ol');

			//	...
			root.querySelector('[data-database="'+database+'"]').appendChild(ol);

			//	...
			for(var table in json[database]){
				var result = json[database][table]['result'];
				var color  = result ? 'success':'error';
				var li = document.createElement('li');
					li.classList = color;
					li.innerText = table;
					li.dataset.table = table;
					ol.appendChild(li);

				//	...
				if( result ){
					continue;
				}
			}
		}
	}

	//	...
	function __fields(root, json){
		//	...
		for(var database in json ){
			for(var table in json[database] ){
				//	...
				var list = document.createElement('ul');

				//	...
				root.querySelector('[data-database="' + database + '"]')
					.querySelector('[data-table="'    + table    + '"]').appendChild(list);

				//	...
				for(var field in json[database][table] ){
					//	...
					var result = json[database][table][field]['result'];
					var item = document.createElement('li');
						item.innerText = field;
						item.classList.add('field');
						item.dataset.field = field;
					list.appendChild(item);

					//	...
					if( result ){
						item.classList.add('success');

						//	Remove list tag.
					//	list.removeChild(item);
					}else{

						//	Missing or Leftover.
						if( result === null ){
							item.classList.add('leftover');
						}else{
							item.classList.add('error');
							item.classList.add('bold');
							item.classList.add('missing');
						}
					}
				}
			}
		}
	};

	//	...
	function __columns(root, json){
		for(var database in json ){
			for(var table in json[database] ){
				for(var field in json[database][table] ){
					//	...
					var list = document.createElement('ol');

					//	...
					for(var column in json[database][table][field] ){
						//	...
						if( json[database][table][field][column].result ){
							continue;
						}

						//	...
						var item = root	.querySelector('[data-database="' + database + '"]')
										.querySelector('[data-table="'    + table    + '"]')
										.querySelector('[data-field="'    + field    + '"]');
						item.appendChild(list);

						//	...
						__column(list, column, json[database][table][field][column]);
					}
				}
			}
		}
	};

	//	...
	function __column(list, column, json){

		//	...
		var item    = document.createElement('li');
			item.classList.add('error');
		var name    = document.createElement('name');
			name.innerText = column;

		//	...
		var arrow = __arrow(json.current, json.modify);

		//	...
		item.appendChild(name);
		item.appendChild(arrow);
		list.appendChild(item);
	};

	//	...
	function __indexes(root, json){
		//	...
		let item = __get_list_item('Indexes');
		root.appendChild(item);

		//	...
		for(let database_name in json ){
			//	...
			let database_list = __get_list(item, database_name);
			let database_item = database_list.querySelector('li');

			//	..
			for(let table_name in json[database_name] ){
				//	...
				let table_list = __get_list(database_item, table_name);
				let table_item = table_list.querySelector('li');

				//	...
				for(let index_name in json[database_name][table_name] ){

					//	...
					let index_list = __get_list(table_item, index_name);
					let index_item = index_list.querySelector('li');
					let index_span = index_list.querySelector('span');

					//	...
					let result = json[database_name][table_name][index_name];

					//	...
					index_span.classList.add( result.result ? 'success': 'error' );

					//	...
					if( result.result ){
						continue;
					}

					//	...
					if( result.exists === false ){
						index_span.classList.add('exists');
						continue;
					}

					//	...
					if( result.type === false ){
						index_span.appendChild( __arrow(result.current, result.modify) );
					}

					//	...
					let field_list = __get_list(index_item);

					//	Each fileds.
					for(let field_name in result.field ){
						let field_value = result.field[field_name];
						let field_item  = __get_list_item(field_name, field_value);
						field_list.appendChild(field_item);
					}
				};
			};
		};
	};

	//	...
	function __arrow(text_current, text_modify){
		//	...
		var span         = document.createElement('span');
		var span_current = document.createElement('span');
		var span_arrow   = document.createElement('span');
		var span_modify  = document.createElement('span');

		//	...
		span_current.innerText = text_current;
		span_modify .innerText = text_modify;

		//	...
		span_current.classList.add('current');
		span_arrow  .classList.add('arrow');
		span_modify .classList.add('modify');

		//	...
		span.appendChild(span_current);
		span.appendChild(span_arrow);
		span.appendChild(span_modify);

		//	...
		if( text_current === null || text_current.length === 0 ){
			span_current.classList.add('empty');
		}
		if( text_modify  === null || text_modify.length === 0 ){
			span_modify .classList.add('empty');
		}

		//	...
		return span;
	}

	//	...
	function __get_list(item, text){
		//	...
		if( item.tagName !== 'LI' ){
			D('item is not li tag.');
			return null;
		}

		//	...
		let ol = document.createElement('ol');

		//	...
		if( text ){
			//	...
			let item = __get_list_item(text);
			ol.appendChild(item);
		}

		//	...
		item.appendChild(ol);

		//	...
		return ol;
	}

	//	...
	function __get_list_item(text, io){
		//	...
		let item = document.createElement('li');
		let span = document.createElement('span');
			span.innerText = text;
		item.appendChild(span);

		//	...
		if( io !== undefined ){
			span.classList.add( io ? 'success': 'failed' );
		}

		//	...
		return item;
	};
}, 0);
