<?php


class SaintException extends Exception
{	
	var $message;
	var $code;
	var $file;
	var $line;
	
// ===========================================================
// - Constructor
// ===========================================================
	function __construct($message, $code=0, $file=false, $line=false) {
		parent::__construct($message, $code);
		if ($file !== false) $this->file = $file; 
		if ($line !== false) $this->line = $line; 
	}

// ===========================================================
// - logs error
// ===========================================================
	function log($format='html') {
		$out = '';
		switch ($format) {
			case 'txt':
				$out .= $this->get_message();
				break;
			
			case 'html':
				$out = $this->get_html("<b>".$this->get_message()."</b><br />Occured in ".$this->get_file()." on line ".$this->get_line());
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
	function get_message()					{ return $this->getMessage(); }
	function get_code()							{ return $this->getCode(); }
	function get_file()							{ return $this->getFile(); }
	function get_line()							{ return $this->getLine(); }
	function get_trace()	 					{ return $this->getTrace(); }
	function get_trace_as_string()	{ return $this->getTraceAsString(); }


// ===========================================================
// - ERROR HTML
// ===========================================================
	function get_html($msg) {
		$out = "<html><head><title>Exception caught</title><style>";
		$out .= "    * { font-family: Trebuchet MS, arial, helvetica, sans-serif; color: #282220;}";
		$out .= " body { background-color: #fff; margin: 0px; }";
		$out .= "	h1 { background-color: #fdfdfd; color: #1EB136; margin-top: 0px; margin-bottom: 0px; padding: 10px; border-top: 10px solid #282220; border-bottom: 1px solid #282220; height: 70px; }";
		$out .= "    p { background-color: #eee; margin-top: 0px; padding: 10px; font-size: 11px; }";
		$out .= "  </style></head><body><h1>Error</h1>";
		$out .= "<p>".$msg."</p>";

		/*
		$out .= "<p>Maybe Toggle Debug here.</p>";		
		$out .= "<p id='db'>";
		ob_start();
		var_export(debug_backtrace());
		$out .= ob_get_clean();
		$out .= "</p>";
		*/
		$out .= "</body></html>";
		return $out;
	}

}



?>