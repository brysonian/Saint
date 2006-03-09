<?php


class DBException extends Exception
{
	
	var $query = false;

	function DBException($message, $errorcode, $query) {
		$this->setQuery($query);
		parent::Exception($message, $errorcode);
	}
	
	/**
	*	logs error.
	*/
	function log($format='html') {
		$out = '';
		switch ($format) {
			case 'txt':
				$out .= $this->getMessage();
				break;
			
			case 'html':
				$out = $this->getHTML($this->getMessage()."</p><p><code>".$this->getQuery()."<code>");
				break;
			
			case 'xml':
				$out .= "<error code='".$this->getCode()."'>";
				$out .= $this->getMessage();
				$out .= "</error>";
				break;
			
			case 'silent':
				# do nothing
				break;
		}
		error_log("Exception in ".$this->getFile()." : ".$this->getMessage());	
		return $out;
	}




	
// ===========================================================
// - Accessors
// ===========================================================
	// getters
	function getQuery() { return $this->query; }
	
	// setters
	function setQuery($q) { $this->query = $q; }

}



?>