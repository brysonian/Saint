<?php

class MySQLResult {	

	var $result;
	
	function MySQLResult(&$result) {
		$this->result =& $result;
	}

	function valid() {
		return !empty($this->result);
	}
	
	function & fetch_assoc() {
		$r = mysql_fetch_assoc($this->result);
		return $r;
	}
	
	function free() {
		return mysql_free_result($this->result);			
	}	
	
}

?>