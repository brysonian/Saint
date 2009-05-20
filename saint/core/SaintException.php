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
	function log($format=false, $address=false, $title='') {
		$out = '';
		if ($format === false) $format = self::$log_format;
		switch ($format) {
			case 'text':
				$out .= $this->get_message()."\n\n";
				break;
			
			case 'email':
			case 'mail':
				ob_start();				
				echo $this->get_message();
				echo "\nFor URL: ".$_SERVER['REQUEST_URI']."\n\n";
				echo "With Reqeust:";
				var_export(params());
				echo "\n\n";
				echo "In ".$this->get_file()." on line ".$this->get_line()."\n";
				mail($address, $title.': '.get_class($this).' Error', ob_get_clean());
				break;

			case 'html':
				$out = $this->to_html();
				break;
			
			case 'xml':
				$out .= $this->to_xml();
				break;
			
			case 'silent':
				# do nothing
				break;
		}
		error_log("Exception in ".$this->get_file().":".$this->get_line()." - ".$this->get_message());	
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


	public static function set_log_format($v)		{ self::$log_format = $v; }


// ===========================================================
// - ERROR FORMATTING
// ===========================================================
	function to_html() {
		$type = get_class($this);
		$file = $this->get_file();
		$line = $this->get_line();
		$msg = nl2br($this->get_message());
		$trace = "<li>".str_replace("\n", "</li>\n<li>", $this->get_trace_as_string())."</li>\n</ul>";

		ob_start();
		include SAINT_ROOT.'/templates/exception.phtml';
		
		return ob_get_clean();
	}

	function to_xml() {
		// $out = "<error code='".$this->get_code()."'>";
		// $out .= $this->get_message();
		// $out .= "</error>";
		// return $out;

		$type = get_class($this);
		$msg = nl2br($this->get_message());
		$file = $this->get_file();
		$line = $this->get_line();
		$code = $this->get_code();
		$trace = $this->get_trace_as_string();

		ob_start();
		include SAINT_ROOT.'/templates/exception.pxml';
		
		return ob_get_clean();


	}
}

class InvalidStatement extends SaintException {}



?>