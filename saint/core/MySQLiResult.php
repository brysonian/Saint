<?php

class MySQLiResult implements Countable
{

	protected $result;
	
	function __construct($result) {
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
	
	public function data_seek($s) {
		$this->result->data_seek($s);
	}
	
// ===========================================================
// - COUNTABLE INTERFACE
// ===========================================================
	public function count() {
		return $this->result->num_rows;
	}

}

?>