<pre><?php

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
	
	print_r($exec);

?></pre>