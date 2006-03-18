<?php

class MySQLResult {	

	var $result;
	var $parent;
	
	function MySQLResult(&$result, &$parent) {
		$this->result =& $result;
		$this->parent = & $parent;
		
		# increase parent's retain count if it's a result
		if (is_resource($result)) {
			$this->parent->retain();
		}
	}
	
	function & fetch_assoc() {
		$r = mysql_fetch_assoc($this->result);
		return $r;
	}
	
	function free() {
		if (mysql_free_result($this->result)) {
			$this->parent->free();
		} else {
			return false;
		}
		return true;
	}	
	
}

?>