# InterExec

Copyright 2012 Christian Sciberras & Contributors

No Specific Licensing (do whatever you want with it)

## Description

PHP command execution on steroids (aka interactively).

A PHP class for running programs through PHP, (uses `proc_open()`), interactively,
that is giving a chance to your script to respond to input requests accordingly.

## Example

Assuming we have the following batch script:

```shell
@ECHO OFF
SET /p name=Enter your name: 
ECHO Hello, %name%!
PAUSE
```

One can interact with this batch script as follows:

```php
$exec = new InterExec('test.bat');
$exec->on('start', function(){
	echo 'Start<br/>';
});
$exec->on('output', function($exec, $data){
	echo '&gt; '.$data.'<br/>';
});
$exec->on('input', function($exec, $last){
	$data = PHP_EOL; // default input, to unblock stream
	if($last == 'Enter your name: ')
		$data = 'John Doe'.PHP_EOL;
	echo '&lt; '.$data.'<br/>';
});
$exec->on('stop', function(){
	echo 'Stop<br/>';
});
$exec->run();
```

The above would result in the following page:

```plain
Start
> Enter your name: 
< John Doe
> Hello, John Doe!
> Press any key to continue...
< 
Stop
```

## Documentation

### Properties

    string $cmd

The command to run.
		
    array|null $environment

Environment variables (null to use existing variables).

	integer $timeout

Time, in seconds, after which command is forcefully aborted.
		
	string $stdout

All of the program's standard output till now.
		
	string $stderr

All of the program's error output till now.
		
	integer $return

Program's exit code (obviously only set after program quits).
		
	float $taken

The time taken for the program to run and close.
		
	boolean $winPathFix

If enabled, fixes a problem with popen not allow spaces inside program path (even when quoted).
		
	float $interval

Interval between ticks, in seconds (a value of zero disables interval)
		
	integer $bufsize

Size of buffer for reading from pipes.

### Methods

	__construct($cmd [, $env])

Constructs new execution instance. 
string $cmd The command line to execute.
array $env (Optional) Environment variables.

	on($event, $callback)

Calls $callback when an event is triggered.
string $event Name of event.
callable $callback The callback to call.

	is_running()

Returns whether process is currently running or not.

	run()

Runs the specified command.

### Events

 - **start** - Triggered when program starts.
 - **stop** - Triggered when program ends.
 - **tick** - Triggered on each execution step (see interval property).
 - **input**($last):$data - Called when program is requesting input.
 Argument `$last` contains last output buffer.
 Callback should return `$data` to be sent to program.
 - **output**($data) - Called when program sent some output.
 Argument `$data` contains the standard output fragment.
 - **error**($data) - Called when program signals erroneous state.
 Argument `$data` contains the standard error fragment.

**All callbacks will receive the command object making triggering the event as first parameter.**