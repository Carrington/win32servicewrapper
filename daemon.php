<?php

define('WIN32_ERROR_CALL_NOT_IMPLEMENTED', 120);

//Base class for Win32 services
abstract class Daemon {
	protected $serviceName					= '';
	protected $serviceDisplayName		= '';
	protected $serviceDescription		= '';
	protected $user									= null;
	protected $password							= null;
	protected $path									= 'C:\\wamp\\bin\\php\\php5.5.14\\php-win.exe';
	protected $svc_type							= WIN32_SERVICE_WIN32_OWN_PROCESS;
	protected $start_type						= WIN32_SERVICE_AUTO_START;
	protected $error_control				= WIN32_SERVER_ERROR_IGNORE;
	protected $delayed_start				= false;
	protected $base_priority				= WIN32_NORMAL_PRIORITY_CLASS;
	protected $msgs									= array();

	//Pre-run work
	abstract protected function setupRun($params = null);
	
	//Work to be executed in each loop frame
	abstract protected function work();
	
	public function create() {
		$params =  array(
			"service" 			=> $this->serviceName,
			"dispaly" 			=> $this->serviceDisplayName,
			"description" 	=> $this->serviceDescription,
			"path"					=> $this->path,
			"svc_type"			=> $this->svc_type,
			"start_type"		=> $this->start_type,
			"error_control"	=> $this->error_control,
			"delayed_start"	=> $this->delayed_start,
			"base_priority" => $this->base_priority
		);
		if ($this->user) {
			$params['user'] = $this->user;
			$params['password'] = $this->password;
		}
		if(win32_create_service($params)) {
			error_log("Service " . $this->serviceDisplayName . " created.");
			return true;
		}
		throw new \Exception("Error creating service: " . $this->serviceDisplayName);
	}
	
	public function start() {
		if(win32_start_service($ServiceName)) {
			error_log($this->serviceDisplayName . " Status: Started");
			return true;
		}
		throw new \Exception("Error Stopping Service: " . $this->serviceDisplayName);
	}
	
	public function run() {
		//TRUE on success; FALSE on parameter problem; Win32 Error Code on error
		$code =  win32_start_service_ctrl_dispatcher($ServiceName);
		if(! $code) {
			throw new \Exception("Error running service: " . $this->serviceDisplayName . ". Was the service started?");
		}
		if($code !== TRUE) {
			throw new \Exception("Error running service: " . $this->serviceDisplayName . " with code: " . $code);
		}
        win32_set_service_status(WIN32_SERVICE_START_PENDING);
		
		$this->setupRun();
		
		win32_set_service_status(WIN32_SERVICE_RUNNING);
		
		while(WIN32_SERVICE_CONTROL_STOP 1== win32_get_last_control_message()) {
			$this->msgs[] = win32_get_last_control_message();
			$this->work();
		}
	}
	
	public function stop() {
		if(win32_delete_service($this->serviceName)) {
			error_log($this->serviceDisplayName . " Status: Stopped");
			return true;
		}
		throw new \Exception("Error Starting Service: " . $this->serviceDisplayName);
	}
	
	public function delete() {
		if ($this->status() !== WIN32_SERVICE_STOPPED) {
			throw new \Exception("Service  " . $this->serviceDisplayName . " is not stopped.");
		}
		if(win32_delete_service($this->serviceName)) {
			error_log("Service " . $this->serviceDisplayName . "  deleted");
			return true;
		}
		throw new \Exception("Error deleting service: " . $this->serviceDisplayName);
	}
	
	public function status() {
		return win32_query_service_status($ServiceName);
	}
}

?>
