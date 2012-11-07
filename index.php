<?php

	set_time_limit(0);
	error_reporting(-1);
	header('Content-Type: text/plain');
	ini_set('display_errors', true);
	while(ob_get_level())ob_end_flush();
	ob_implicit_flush(true);
	
	function write($msg){
		echo $msg;
		flush();
	}
	
	function writeln($msg){
		write($msg.PHP_EOL);
	}
	
	function writehr(){
		writeln(str_repeat('-', 80));
	}
	
	writeln('EXECUTION EVENT LOG'.str_repeat(' ', 1024));

	require('src/InterExec.php');
	
	$ds = DIRECTORY_SEPARATOR;
	$ext = $ds=='/' ? 'sh' : 'bat';
	$cmd = '"'.__DIR__.$ds.'test'.$ds.'test.'.$ext.'"';
	
	$exec = new InterExec($cmd);
	$exec->interval = 1;
	$exec->on('start', function($exec){
		writeln('Started');
	});
	$exec->on('stop', function($exec, $exitcode){
		writeln('Stopped');
	});
	$exec->on('abort', function($exec, $reason){
		writeln('Aborted');
	});
	$exec->on('tick', function($exec){
		writeln('Tick');
	});
	$exec->on('input', function($exec){
		writeln('Input');
		return 'Chris'.PHP_EOL;
	});
	$exec->on('output', function($exec, $data){
		writeln('Output: '.$data);
	});
	$exec->on('error', function($exec, $data){
		writeln('Error: '.$data);
	});
	$exec->run();
	
	writehr();
	
	writeln('EXECUTION CONTEXT');
	
	print_r($exec);

?>