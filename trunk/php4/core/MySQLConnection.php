<?php


class MySQLConnection {

	var $db;
	var $host;
	var $user;
	var $pass;
	var $dbname;
	var $persistent;
	var $retain_count;
	var $autorelease;


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
		
		$this->retain_count	= 0;
		$this->autorelease	= true;
	}
	
	function set_autorelease($val) {
		$this->autorelease = $val;
	}
	
	function open() {
		if ($this->persistent) {
			$this->db = mysql_pconnect($this->host, $this->user, $this->pass)
				or die('Could not connect: ' . mysql_error());
		} else {
			$this->db = mysql_connect($this->host, $this->user, $this->pass)
				or die('Could not connect: ' . mysql_error());
		}
		mysql_select_db($this->dbname) or die('Could not select database');
	}
	
	
	// ===========================================================
	// - INTERFACE
	// ===========================================================	
	function escape_string($str) {
		return mysql_real_escape_string($str);
	}
	
	function &query($sql) {
		# open db connection if there isn't open
		if(!$this->db) {
			$this->open();
		}
		$q =& new MySQLResult(mysql_query($sql), $this);
		return $q;
	}

	function insert_id() {
		return mysql_insert_id($this->db);
	}
	
	function tableInfo($table) {
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
		if (--$this->retain_count == 0 && $this->autorelease) {
			$this->close();
		}
	}
	
	function retain() {
		$this->retain_count++;
	}
	
	function close() {
		if ($this->db && !$this->persistent) mysql_close($this->db);
	}
}

?>