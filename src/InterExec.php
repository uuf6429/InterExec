<?php

	class InterExec {
		
		/**
		 * The command to run.
		 * @var string
		 */
		public $cmd = '';
		
		/**
		 * Environment variables (null to use existing variables).
		 * @var array|null 
		 */
		public $environment = null;
		
		/**
		 * Time, in seconds, after which command is forcefully aborted.
		 * @var integer 
		 */
		public $timeout = 0;
		
		/**
		 * All of the program's standard output till now.
		 * @var string
		 */
		public $stdout = '';
		
		/**
		 * All of the program's error output till now.
		 * @var string
		 */
		public $stderr = '';
		
		/**
		 * Program's exit code (obviously only set after program quits).
		 * @var integer
		 */
		public $return = 0;
		
		/**
		 * The time taken for the program to run and close.
		 * @var float
		 */
		public $taken = 0;
		
		/**
		 * If enabled, fixes a problem with popen not allow spaces inside program path (even when quoted).
		 * @var boolean
		 */
		public $winPathFix = true;
		
		/**
		 * Interval between ticks, in seconds (a value of zero disables interval)
		 * @var float 
		 */
		public $interval = 0;
		
		/**
		 * Array containing event callbacks.
		 * @var array
		 */
		protected $events = array();
		
		/**
		 * Process resource.
		 * @var resource
		 */
		public $process = null;
		
		/**
		 * Process I/O pipes.
		 * @var array
		 */
		public $pipes = null;
		
		/**
		 * 
		 * @param string $cmd The command line to execute.
		 */
		public function __construct($cmd){
			$this->cmd = $cmd;
		}
		
		/**
		 * Call callback when an event is triggered.
		 * @param string $event Name of event.
		 * @param callable $callback The callback to call.
		 */
		public function on($event, $callback){
			$this->events[$event] = $callback;
		}
		
		/**
		 * Trigger an event.
		 * @param string $event Name of event.
		 * @param array $args (Optional) Arguments to pass to event.
		 *    Note that the first argument is always $this.
		 * @return mixed Value resulting from event callback.
		 */
		protected function fire($event, $args=array()){
			if(!is_array($args))
				throw new InvalidArgumentException('Event arguments should be an array');
			if(isset($this->events[$event])){
				array_unshift($args, $this);
				return call_user_func_array($this->events[$event], $args);
			}
		}
		
		/**
		 * Runs the command!
		 */
		public function run(){
			$cmd = $this->cmd;
			$this->stdout = '';
			$this->stderr = '';
			
			if($this->winPathFix){
				// This hack fixes a legacy issue in popen not handling escaped command filenames on Windows.
				// Basically, if we're on windows and the first command part is double quoted, we CD into the
				// directory and execute the command from there.
				// Example: '"C:\a test\b.exe" -h'  ->  'cd "C:\a test\" && b.exe -h'
				$uname = strtolower(php_uname());
				$is_win = (strpos($uname,'win')!==false) && (strpos($uname,'darwin')===false);
				if($is_win && is_string($ok = preg_replace(
						'/^(\s*)"([^"]*\\\\)(.*?)"(.*)/s', // pattern
						'$1cd "$2" && "$3" $4',            // replacement
						$cmd ))) $cmd = $ok;               // success!
			}
			
			// start profiling execution
			$start  = microtime(true);
			
			// the pipes we will be using
			$this->pipes = array();
			$desc = array(
				0 => array('pipe', 'r'), // STDIN
				1 => array('pipe', 'w'), // STDOUT
				2 => array('pipe', 'w')  // STDERR
			);
			
			// create the process
			$this->process = proc_open($cmd, $desc, $this->pipes, null, $this->environment);
			
			$this->fire('start');

			// avoid blocking on pipes
			foreach($this->pipes as $pipe)
				stream_set_blocking($pipe, 0);
			
			// wait for process to finish
			while(true){
			
				$this->fire('tick');
				
				// check process status
				$stat = proc_get_status($this->process);
				if(!$stat['running'] || $stat['signaled'] || $stat['stopped'])
					break;
				
				$write  = array($this->pipes[0]);
				$read   = array($this->pipes[1], $this->pipes[2]);
				$except = null;
				
				while(($r = stream_select($read, $write, $except, 0))>0){
					
				}
/*
				// handle input event
				if(*$needs_stdin){
					$data = $this->fire('input');
					fwrite($this->pipes[0], $data ? $data : PHP_EOL);
					fflush($this->pipes[0]);
				}
				
				// handle output event
//				if($has_stdout){
					$buf = stream_get_contents($this->pipes[1]);
					if($buf){
						$this->stdout .= $buf;
						$this->fire('output', array($buf));
					}
//				}
				
				// handle error event
				if($has_stderr){
					$buf = stream_get_contents($this->pipes[2]);
					if($buf){
						$this->stderr .= $buf;
						$this->fire('error', array($buf));
					}
				}
*/
				// this code is a bit faulty - it blocks on input, leading to a deadlock
				//$this->stdout .= stream_get_contents($this->pipes[1]);
				//$this->stderr .= stream_get_contents($this->pipes[2]);
			
				// calculate time taken so far
				$this->taken = microtime(true) - $start;
			
				// check for timeout
				if($this->timeout && $this->taken>$this->timeout)
					break;
				
				// sleep for a while
				if($this->interval)
					usleep($this->interval * 1000000);
				
			}

			// close used resources
			foreach($this->pipes as $pipe)fclose($pipe);
			$this->pipes = null;
			$this->return = proc_close($this->process);
			
			// clear resource
			$this->process = null;
			
			$this->fire('stop', array($this->return));
			
			// return result
			return $this;
		}
	}

?>