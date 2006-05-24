<?php


class SaintException extends Exception
{	
	public $message;
	public $code;
	public $file;
	public $line;
	public static $log_format = 'html';
	
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
	function log($format=false) {
		$out = '';
		if ($format === false) $format = SaintException::$log_format;
		switch ($format) {
			case 'text':
				$out .= $this->get_message()."\n\n";
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
	function get_message()					{ return $this->getMessage(); }
	function get_code()							{ return $this->getCode(); }
	function get_file()							{ return $this->getFile(); }
	function get_line()							{ return $this->getLine(); }
	function get_trace()	 					{ return $this->getTrace(); }
	function get_trace_as_string()	{ return $this->getTraceAsString(); }


	public static function set_log_format($v)		{ SaintException::$log_format = $v; }


// ===========================================================
// - ERROR HTML
// ===========================================================
	function get_html($msg) {
		$msg = nl2br($msg);
		$trace = "<li>".str_replace("\n", "</li>\n<li>", $this->get_trace_as_string())."</li>\n</ul>";

		ob_start();
		include SAINT_ROOT.'/templates/exception.phtml';
		
		return ob_get_clean();
	}

}



?>