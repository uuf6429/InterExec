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
		 * Size of buffer for reading from pipes.
		 * @var integer
		 */
		public $bufsize = 4096;
		
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
		
		const STDIN  = 0;
		const STDOUT = 1;
		const STDERR = 2;
		
		/**
		 * Creates new instance.
		 * @param string $cmd The command line to execute.
		 * @param array $env (Optional) Environment variables.
		 */
		public function __construct($cmd, $env = null){
			$this->cmd = $cmd;
			$this->environment = $env;
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
		 * Returns whether process is currently running or not.
		 * @return boolean
		 */
		public function is_running(){
			if(is_resource($this->process)){
				$stat = proc_get_status($this->process);
				return !(!$stat['running'] || $stat['signaled'] || $stat['stopped']);
			}
			return false;
		}
		
		/**
		 * Returns whether stream currently has pending content or not.
		 * @param resource $stream The stream resource.
		 * @return boolean True if there is unread content, false otherwise.
		 */
		protected function stream_has_content($stream){
			$stat = stream_get_meta_data($stream);
			//print_r($stat);
			return !$stat['eof'];// && !$stat['blocked'];
		}
		
		/**
		 * Runs the command!
		 * @return InterExec
		 */
		public function run(){
			$cmd = $this->cmd;
			$this->stdout = '';
			$this->stderr = '';
			
			if($this->winPathFix && DIRECTORY_SEPARATOR=='\\'){
				// This hack fixes a legacy issue in popen not handling escaped command filenames on Windows.
				// Basically, if we're on windows and the first command part is double quoted, we CD into the
				// directory and execute the command from there.
				// Example: '"C:\a test\b.exe" -h'  ->  'cd "C:\a test\" && b.exe -h'
				$cmd = trim(preg_replace(
					'/^\s*"([^"]+?)\\\\([^\\\\]+)"\s?(.*)/s',
					'cd "$1" && "$2" $3',
					$cmd
				));
			}
			
			// start profiling execution
			$start  = microtime(true);
			
			// contains last text returned by stdout
			$lastBuffer = '';
			
			// the pipes we will be using
			$this->pipes = array();
			$desc = array(
				self::STDIN  => array('pipe', 'r'), // STDIN
				self::STDOUT => array('pipe', 'w'), // STDOUT
				self::STDERR => array('pipe', 'w')  // STDERR
			);
			
			// create the process
			$this->process = proc_open($cmd, $desc, $this->pipes, null, $this->environment);
			
			$this->fire('start');

			// avoid blocking on pipes
			//foreach($this->pipes as $pipe)
			//	stream_set_blocking($pipe, 0);
			stream_set_blocking($this->pipes[self::STDIN], 0);

			// wait for process to finish
			while(true){
			
				$this->fire('tick');
				
				// if process quit, break main loop
				if(!$this->is_running()){
					break;
				}
				
				// pipe stream wrappers
				$w = array($this->pipes[self::STDIN]);
				$r = array($this->pipes[self::STDOUT], $this->pipes[self::STDERR]);
				$e = null;
				
				// handle any pending I/O
				if(stream_select($r, $w, $e, null/*, 25000*/) > 0){

//$ts = array(self::STDIN=>'I',self::STDOUT=>'O',self::STDERR=>'E'); $t = '';
//$pa = array_merge(array_values($r), array_values($w));
//foreach($pa as $h)$t .= ($p=array_search($h, $this->pipes))!==false ? $ts[$p] : 'U';
//echo '['.$t.'] stream selected'.PHP_EOL;
					
					// handle STDOUT, STDERR
					foreach($r as $h){
						// clear the buffer
						$buf = '';
						
						// read data into buffer
						$t = array_search($h, $this->pipes);
						if($t!==false /*TEST->*/&& $t != self::STDERR/*<-TEST*/){
//echo '['.($t==self::STDOUT ? 'STDOUT' : 'STDERR').'] reading...'.PHP_EOL;
							if($this->stream_has_content($h)){
//echo '['.($t==self::STDOUT ? 'STDOUT' : 'STDERR').'] reading chunk...';
								$buf .= fread($h, $this->bufsize);
//echo var_export($buf, true).PHP_EOL;
							}
						}

						// if buffer is not empty...
						if($buf !== ''){
							// fire output event
							if($t == self::STDOUT){
								$this->stdout .= $buf;
								$lastBuffer = $buf;
								$this->fire('output', array($buf));
							}

							// fire error event
							if($t == self::STDERR){
								$this->stderr .= $buf;
								$this->fire('error', array($buf));
							}
						}
					}
					
					// handle STDIN
					foreach($w as $h){
						// fire input event
						if($h===$this->pipes[self::STDIN]){
//echo '[STDIN] writing...'.PHP_EOL;
							$data = $this->fire('input', array($lastBuffer));
							fwrite($this->pipes[self::STDIN], $data ? $data : PHP_EOL);
							fflush($this->pipes[self::STDIN]);
							$lastBuffer = '';
						}
					}
					
					// if process quit, break I/O loop
					if(!$this->is_running()){
						break;
					}
				}
				
				// this is the old, faulty code - it blocks on input, leading to a deadlock
				//$this->stdout .= stream_get_contents($this->pipes[1]);
				//$this->stderr .= stream_get_contents($this->pipes[2]);
			
				// calculate time taken so far
				$this->taken = microtime(true) - $start;
			
				// check for timeout
				if($this->timeout && $this->taken > $this->timeout){
					break;
				}
				
				// sleep for a while
				if($this->interval){
					usleep($this->interval * 1000000);
				}
			}

			// close and clean used resources
			foreach($this->pipes as $pipe)fclose($pipe);
			$this->pipes = null;
			$this->return = proc_close($this->process);
			$this->process = null;
			
			$this->fire('stop', array($this->return));
			
			// return result (for chaining)
			return $this;
		}
	}

?>