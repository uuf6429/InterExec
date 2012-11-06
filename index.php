<pre><?php

	set_time_limit(0);
	error_reporting(-1);
	ini_set('display_errors', true);
	ob_implicit_flush(true);
	while(ob_get_level())ob_end_flush();

	require('src/InterExec.php');
	
	$ext = DIRECTORY_SEPARATOR=='/' ? 'sh' : 'bat';
	
	$exec = new InterExec("test/test.$ext");
	$exec->on('start', function($exec){
		echo 'Started'.PHP_EOL;
	});
	$exec->on('stop', function($exec, $exitcode){
		echo 'Stopped'.PHP_EOL;
	});
	$exec->on('abort', function($exec, $reason){
		echo 'Aborted'.PHP_EOL;
	});
	$exec->on('tick', function($exec){
		echo 'Tick'.PHP_EOL;
	});
	$exec->on('input', function($exec){
		echo 'Input'.PHP_EOL;
	});
	$exec->on('output', function($exec, $data){
		echo 'Output: '.$data.PHP_EOL;
	});
	$exec->run();
	
	?><hr/><?php
	
	print_r($exec);

?></pre>