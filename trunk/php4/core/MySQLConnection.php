<?php


class MySQLConnection {

	var $db;
	
	// ===========================================================
	// - CONSTRUCTOR
	// ===========================================================
	function MySQLConnection($host, $user, $pass, $dbname, $persistent=false) {
		if ($persistent) {
			$this->db = mysql_pconnect($host, $user, $pass)
				or die('Could not connect: ' . mysql_error());
		} else {
			$this->db = mysql_connect($host, $user, $pass)
				or die('Could not connect: ' . mysql_error());
		}
		mysql_select_db($dbname) or die('Could not select database');
	}
	
	
	// ===========================================================
	// - INTERFACE
	// ===========================================================	
	function escape_string($str) {
		$str = mysql_real_escape_string($str);
		return $str;
	}
	
	function &query($sql) {
		$q =& new MySQLResult(mysql_query($sql));
		return $q;
	}

	function insert_id() {
		return mysql_insert_id($this->db);
	}
	
	function table_info($table) {
		$sql = "SHOW COLUMNS FROM $table";
		$result = new MySQLResult(mysql_query($sql));
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
		$e = mysql_errno($this->db);
		return $e;
	}

	function error() {
		return mysql_error($this->db);
	}
}

?>