<?php


class MySQLiConnection
{

	protected $db;
	protected $host;
	protected $user;
	protected $pass;
	protected $dbname;
	protected $last_query;

	protected static $query_count = 0;

	// ===========================================================
	// - CONSTRUCTOR
	// ===========================================================
	function MySQLiConnection($host, $user, $pass, $dbname) {
		$this->db			= false;
		$this->host			= $host;
		$this->user			= $user;
		$this->pass			= $pass;
		$this->dbname		= $dbname;
		
		$this->open();
	}
	
	function set_autorelease($val) {
		$this->autorelease = $val;
	}
	
	function open() {
		$this->db = new mysqli($this->host, $this->user, $this->pass, $this->dbname);
		
		# make sure we conneted
		if (!$this->db)
			throw new DBException("Failed to connect to mysql.\n".$this->db->error, $this->db->errno, '');		
		
	}
	
	
	// ===========================================================
	// - INTERFACE
	// ===========================================================	
	function escape_string($str) {
		return $this->db->real_escape_string($str);
	}
	
	function  query($sql) {
		MySQLiConnection::$query_count++;
		
		# open db connection if there isn't open
		if(!$this->db) $this->open();
		
		# get result
		$r = $this->db->query($sql);
		
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
		$sql = "SHOW COLUMNS FROM `$table`";
		$result = new MySQLiResult($this->db->query($sql));
		$output = array();
		if (!$full) $output['order'] = array();
		$i=0;
		while ($row = $result->fetch_assoc()) {
			if ($full) {
				$output[] = $row;
			} else {
				$output['order'][$row['Field']] = $i;
			}
			$i++;
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
		if ($this->db) $this->db->close();
	}
	
	function __destruct() {
		if(defined('DEBUG') && DEBUG == 1) error_log('MySQLiConnection made '.MySQLiConnection::$query_count.' queries.');
		$this->close();
	}
}

?>