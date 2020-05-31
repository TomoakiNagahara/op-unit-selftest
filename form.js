//<?php
/** op-unit-selftest:/form.css
 *
 * @created   2020-05-31
 * @version   1.0
 * @package   op-unit-selftest
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright Tomoaki Nagahara All right reserved.
 */
//?>

//<?php
/** If you use localhost as the host name, socket communication will be used.
 *
 * @created   2020-05-31
 * @version   1.0
 * @package   op-unit-selftest
 * @author    Tomoaki Nagahara <tomoaki.nagahara@gmail.com>
 * @copyright Tomoaki Nagahara All right reserved.
 */
//?>
(function(){
	//	DOM is onload
	document.addEventListener('DOMContentLoaded', (event) => {
		//	Check
		CHECK_HOST_VALUE();

		//	Add change event listener.
		let input = document.querySelector('#UNIT_SELFTEST div.tr.host input[name=host]');
			input.addEventListener('change', (event) => {
				CHECK_HOST_VALUE();
			});
	});

	//	Check host input value.
	function CHECK_HOST_VALUE(){
		//	...
		let input = document.querySelector('#UNIT_SELFTEST div.tr.host input[name=host]');

		//	...
		if( input.value === 'localhost' ){
			DISPLAY_HOST_NOTICE();
		}
	}

	//	Display host name notice.
	function DISPLAY_HOST_NOTICE(){
		//	...
		let span = document.createElement('span');
			span.innerText = 'If you use "localhost" as the host name, socket communication will be used.';

		//	...
		let div = document.querySelector('#UNIT_SELFTEST div.tr.host div.error');
			div.appendChild(span);
	}
})();
