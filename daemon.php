<?php

define('WIN32_ERROR_CALL_NOT_IMPLEMENTED', 120);

//Base class for Win32 services
abstract class Daemon {
	protected $serviceName		= 'testService';
	protected $serviceDisplayName	= 'testService';
	protected $serviceDescription	= 'testService';
	protected $user			= null;
	protected $password		= null;
	protected $path			= 'c:\php\php-win.exe';
	protected $svc_type		= WIN32_SERVICE_WIN32_OWN_PROCESS;
	protected $start_type		= WIN32_SERVICE_AUTO_START;
	protected $error_control	= WIN32_SERVICE_ERROR_IGNORE;
	protected $delayed_start	= false;
	protected $base_priority	= WIN32_NORMAL_PRIORITY_CLASS;
	protected $msgs			= array();
	
	//Pre-run work
	abstract protected function setupRun($params = null);
	//Work to be executed in each loop frame
	abstract protected function work();
	
	public function __construct($params = null) {
		/*if (! $params) {
			return;
		}*/
		foreach([	'user'		=>null,
					'password'	=>null,
					'path'		=>$this->path,
					'svc_type'	=>WIN32_SERVICE_WIN32_OWN_PROCESS	] as $param => $val){
			if(array_key_exists($param,$params)) $this->{$param} = $params[$param] ?: $val;
		}
	}
	
	public function create() {
		$params =  array(
			"service" 	=> $this->serviceName,
			"dispaly" 	=> $this->serviceDisplayName,
			"description" 	=> $this->serviceDescription,
			"path"		=> $this->path,
			"svc_type"	=> $this->svc_type,
			"start_type"	=> $this->start_type,
			"error_control"	=> $this->error_control,
			"delayed_start"	=> $this->delayed_start,
			"base_priority" => $this->base_priority
		);
		if ($this->user) {
			$params['user']     = $this->user;
			$params['password'] = $this->password;
		}
		if(win32_create_service($params) == WIN32_NO_ERROR) {
			error_log("Service " . $this->serviceDisplayName . " created.");
			return true;
		}
		throw new \Exception("Error creating service: " . $this->serviceDisplayName);
	}
	
	public function start() {
		if(win32_start_service($this->serviceName)) {
			error_log($this->serviceDisplayName . " Status: Started");
			return true;
		}
		throw new \Exception("Error Starting Service: " . $this->serviceDisplayName);
	}
	
	public function run() {
		//TRUE on success; FALSE on parameter problem; Win32 Error Code on error
		$code =  win32_start_service_ctrl_dispatcher($this->serviceName);
		if(! $code) {
			throw new \Exception("Error running service: " . $this->serviceDisplayName . ". Was the service started?");
		}
		if($code !== TRUE && php_sapi_name() !== 'cli') {
			$codeName = array_search($code, get_defined_constants(true)['win32service']);
			throw new \Exception("Error running service: " . $this->serviceDisplayName . " with code: " . $codeName	);
		}
        win32_set_service_status(WIN32_SERVICE_START_PENDING);
		error_log("Service " . $this->serviceDisplayName . " setting up run...");
		$this->setupRun();
		
		win32_set_service_status(WIN32_SERVICE_RUNNING);
		error_log("Service " . $this->serviceDisplayName . " running...");
		while(WIN32_SERVICE_CONTROL_STOP !== win32_get_last_control_message()) {
			$this->msgs[] = win32_get_last_control_message();
			$this->work();
		}
	}
	
	public function stop() {
		if(win32_delete_service($this->serviceName)) {
			error_log($this->serviceDisplayName . " Status: Stopped");
			return true;
		}
		throw new \Exception("Error Stopping Service: " . $this->serviceDisplayName);
	}
	
	public function delete() {
		/*if ($this->status() !== WIN32_SERVICE_STOPPED) {
			throw new \Exception("Service  " . $this->serviceDisplayName . " is not stopped.");
		}*/
		if(win32_delete_service($this->serviceName)) {
			error_log("Service " . $this->serviceDisplayName . "  deleted");
			return true;
		}
		throw new \Exception("Error deleting service: " . $this->serviceDisplayName);
	}
	
	public function status() {
		return win32_query_service_status($this->serviceName);
	}
}

?>
