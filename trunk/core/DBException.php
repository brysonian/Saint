<?php


class DBException extends SaintException
{
	
	var $query = false;

	function DBException($message, $errorcode, $query) {
		$this->set_query($query);
		parent::__construct($message, $errorcode);
	}
	
	/**
	*	logs error.
	*/
	function log($format='html') {
		$out = '';
		switch ($format) {
			case 'txt':
				$out .= $this->get_message();
				break;
			
			case 'html':
				$out = $this->get_html($this->get_message()."</p><p><code>".$this->get_query()."<code>");
				break;
			
			case 'xml':
				$out .= "<error code='".$this->get_code()."'>";
				$out .= $this->get_message();
				$out .= "</error>";
				break;
			
			case 'silent':
				# do nothing
				break;
		}
		error_log("Exception in ".$this->get_file()." : ".$this->get_message());	
		return $out;
	}




	
// ===========================================================
// - Accessors
// ===========================================================
	// getters
	function get_query() { return $this->query; }
	
	// setters
	function set_query($q) { $this->query = $q; }

}



?>