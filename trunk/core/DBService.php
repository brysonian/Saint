<?php


// ===========================================================
// - DBSERVICE CLASS
// ===========================================================
class DBService
{
	static private	$map = array();	# mapping of classnames to user/pass/db/[host]
	static private	$connections = array();	# the actual connections
	static private	$instance = false;
		
	private function __construct() {}
	
	static function get_instance() {
		if(!self::$instance) {
			$c = __CLASS__;
			self::$instance = new $c;
		}
		return self::$instance;
	}
	
	static function get_connection($serviceid) {
#		$class = debug_backtrace();
#		$class = '$serviceid='.$class[1]['class'].'::get_service_id();';
#		eval($class);
		
		# first see if the connection exists, then return it
		if (isset(self::$connections[$serviceid])) return self::$connections[$serviceid];
		
		# make sure this id has an entry
		if (!array_key_exists($serviceid, self::$map)) throw new RopeException('No connection with id: "'.$serviceid.'" found', 666);

		# indicate that the connection exists
		self::$map[$serviceid]['set'] = true;
		
		# now make sure an equivalent (same conenction params) connection isn't there to be used
		$called = self::$map[$serviceid];

		foreach (self::$map as $name => $conf) {
			if ($name == $serviceid) continue;		# skip me
			if (!$conf['set']) continue;		# make sure there is a connection

			$diff = array_diff($conf, $called);
			if (empty($diff)){
				self::$connections[$serviceid] = self::$connections[$name];
				return self::$connections[$name];
			}
		}
		
		# create the new connection of the right type
		switch ($called['type']) {
			case 'mysqli':
				self::$connections[$serviceid] = new MySQLiConnection(
					$called['host'],
					$called['user'],
					$called['pass'],
					$called['dbname'],
					$called['options']
				);
				break;
				
			case 'sqlite':
				self::$connections[$serviceid] = new SQLiteConnection(
					$called['dbname']
				);
				break;
		}		
		
		# return it
		return self::$connections[$serviceid];
	}
	
	function __destruct() {
		foreach(self::$connections as $connection) {
			$connection->close();
		}
	}

	static function close() {
		DBService::get_instance()->__destruct();
	}


// ===========================================================
// - INIT
// ===========================================================
	static public function add_connection_for_classes($classes, $type, $dbname, $user=false, $pass=false, $host='localhost') {
		# loop through classes and add connection for each
		foreach($classes as $class) {
			self::add_connection_for_class($class, $type, $dbname, $user, $pass, $host);
		}
	}

	static public function add_connection_for_class($class, $type, $dbname, $user=false, $pass=false, $host='localhost', $options=array()) {
		self::$map[$class] = array(
			'type'		=> $type,
			'host'		=> $host,
			'user'		=> $user,
			'pass'		=> $pass,
			'dbname'		=> $dbname,
			'set'			=> false,
			'options'	=> $options
		);
	}

	protected function set_connection($classname) {
		$this->db->change_user(
			$this->connections[$classname]['user'],
			$this->connections[$classname]['pass'],
			$this->connections[$classname]['dbname']
		);
	}
}




?>