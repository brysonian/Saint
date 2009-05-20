<?php

class MySQLResult implements Countable
{	

	protected $result;
	protected $parent;
	
	function __construct($result, $parent) {
		$this->result = $result;
		$this->parent = $parent;		
	}
	
	function fetch_assoc() {
		$r = mysql_fetch_assoc($this->result);
		return $r;
	}
	
	function free() {
		if (!is_resource($this->result)) return true;
		if (mysql_free_result($this->result)) {
			$this->parent->free();
		} else {
			return false;
		}
		return true;
	}
	
// ===========================================================
// - COUNTABLE INTERFACE
// ===========================================================
	public function count() {
		return mysql_num_rows($this->result);
	}

}

?>