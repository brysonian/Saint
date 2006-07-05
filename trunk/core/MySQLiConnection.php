<?php


class MySQLiConnection
{

	protected $db;
	protected $host;
	protected $user;
	protected $pass;
	protected $dbname;
	protected $last_query;
	protected $options;

	protected static $query_count = 0;

	// ===========================================================
	// - CONSTRUCTOR
	// ===========================================================
	function MySQLiConnection($host, $user, $pass, $dbname, $options=array()) {
		$this->db			= false;
		$this->host			= $host;
		$this->user			= $user;
		$this->pass			= $pass;
		$this->dbname		= $dbname;
		$this->options	= $options;
	}
		
	function open() {
		$this->db = mysqli_init();
		foreach($this->options as $k => $v) {
			$this->db->options(constant($k), $v);
		}
		$this->db->real_connect($this->host, $this->user, $this->pass, $this->dbname);

		# make sure we conneted
		if (mysqli_connect_errno())
			throw new MySQLiConnectionFailure("Failed to connect to mysql.\n".$this->db->error, $this->db->errno, '');
		
	}
	
	
	// ===========================================================
	// - INTERFACE
	// ===========================================================	
	function escape_string($str) {
		# open db connection if there isn't open
		if(!$this->db) $this->open();
		return $this->db->real_escape_string($str);
	}
	
	function  query($sql) {
		MySQLiConnection::$query_count++;
		
		# open db connection if there isn't open
		if(!$this->db) $this->open();
		
		# get result
		$r = $this->db->query($sql);
		
		if(defined('MYSQLI_DEBUG') && MYSQLI_DEBUG > 1 && !SHELL) error_log($sql);		
		
		# if it's true, return that (insert, etc)
		# else make sure it's a resource and has rows
		if ($r === true) {
			$q = true;
		} else if ($r === false) {
			$q = false;
		} else {
			$q = new MySQLiResult($r);
		}		
		return $q;
	}

	function insert_id() {
		return $this->db->insert_id;
	}
	
	function table_info($table, $full=false) {
		if(!$this->db) $this->open();

		MySQLiConnection::$query_count++;
		
		$sql = "SHOW COLUMNS FROM `$table`";
		$result = $this->db->query($sql);
		if(defined('MYSQLI_DEBUG') && MYSQLI_DEBUG > 1 && !SHELL) error_log($sql);

		$output = array();
		$i=0;
		if ($result) {
			while ($row = $result->fetch_assoc()) {
				if ($full) {
					$output[] = $row;
				} else {
					$output[$i] = $row['Field'];
				}
				$i++;
			}
		}
		return $output;
	}
	
	function errno() {
		return $this->db->errno;
	}

	function error() {
		return $this->db->error;
	}
	
	function free() {
		$this->close();
	}
	
	function close() {
		if ($this->db) {
			$this->db->close();
			$this->db = false;
		}
	}
	
	function __destruct() {
		if(defined('MYSQLI_DEBUG') && MYSQLI_DEBUG > 0 && !SHELL && MySQLiConnection::$query_count > 0) error_log('MySQLiConnection made '.MySQLiConnection::$query_count.' queries.');
		$this->close();
	}
}

// ===========================================================
// - EXCEPTIONS
// ===========================================================
class MySQLiConnectionFailure extends SaintException {}


?>