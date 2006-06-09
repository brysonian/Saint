<?php


class MySQLConnection {

	var $db;
	var $host;
	var $user;
	var $pass;
	var $dbname;
	var $persistent;
	var $last_query;

	// ===========================================================
	// - CONSTRUCTOR
	// ===========================================================
	function MySQLConnection($host, $user, $pass, $dbname, $persistent=false) {
		$this->db			= false;
		$this->host			= $host;
		$this->user			= $user;
		$this->pass			= $pass;
		$this->dbname		= $dbname;
		$this->persistent	= $persistent;
		
		$this->open();
	}
	
	function set_autorelease($val) {
		$this->autorelease = $val;
	}
	
	function open() {
		if ($this->persistent) {
			$this->db = @mysql_pconnect($this->host, $this->user, $this->pass);			
		} else {
			$this->db = @mysql_connect($this->host, $this->user, $this->pass);
		}
		
		# make sure we conneted
		if (!$this->db)
			throw new MySQLConnectionFailure("Failed to connect to mysql.\n".mysql_error(), mysql_errno(), '');
		
		# choose our db
		if (!mysql_select_db($this->dbname)) {
			throw new MySQLDatabaseSelectionFailure('Failed to select database '.$this->dbname.".\n".mysql_error(), mysql_errno(), '');
		}
		
	}
	
	
	// ===========================================================
	// - INTERFACE
	// ===========================================================	
	function escape_string($str) {
		return mysql_real_escape_string($str);
	}
	
	function  query($sql) {
		# open db connection if there isn't open
		if(!$this->db) $this->open();
		
		# get result
		$r = mysql_query($sql);
		
		# if it's true, return that (insert, etc)
		# else make sure it's a resource and has rows
		if ($r === true) {
			$q = true;
		} else if ($r === false) {
			$q = false;
		} else {
			$q = new MySQLResult($r, $this);
		}		
		return $q;
	}

	function insert_id() {
		return mysql_insert_id($this->db);
	}
	
	function table_info($table) {
		$sql = "SHOW COLUMNS FROM $table";
		$result = new MySQLResult(mysql_query($sql), $this);
		$output = array();
		$output['order'] = array();
		$i=0;
		while ($row = $result->fetch_assoc()) {
			$output['order'][$row['Field']] = $i;
			$i++;
		}
		return $output;
	}
	
	function errno() {
		return mysql_errno($this->db);
	}

	function error() {
		return mysql_error($this->db);
	}
	
	function free() {
		$this->close();
	}
	
	function close() {
		if ($this->db && !$this->persistent) mysql_close($this->db);
	}
}

// ===========================================================
// - EXCEPTIONS
// ===========================================================
class MySQLConnectionFailure extends DBRecordError {}
class MySQLDatabaseSelectionFailure extends DBRecordError {}


?>