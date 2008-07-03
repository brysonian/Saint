<?php


class DBRecordError extends SaintException
{
	
	protected $query = false;

	function __construct($message, $errorcode, $query) {
		$this->set_query($query);
		parent::__construct($message, $errorcode);
	}


	
// ===========================================================
// - Accessors
// ===========================================================
	// getters
	function get_query() { return $this->query; }
	function set_query($q) { $this->query = $q; }
	function get_message() {
		return parent::get_message()."\n<code>".$this->get_query()."<code>";
	}
}


?>