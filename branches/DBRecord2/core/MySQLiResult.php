<?php

class MySQLiResult implements Countable
{

	protected $result;
	
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
	
// ===========================================================
// - COUNTABLE INTERFACE
// ===========================================================
	public function count() {
		return $this->result->num_rows;
	}

}

?>