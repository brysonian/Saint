<?php

class MySQLiResult
{

	var $result;
	
	function MySQLiResult($result) {
		$this->result = $result;
	}
	
	public function fetch_assoc() {
		return $this->result->fetch_assoc();
	}
	
	function free() {
		if (!is_resource($this->result)) return true;
		$this->result->free();
		return true;
	}
	
	function num_rows() {
		return $this->result->num_rows;
	}

}

?>