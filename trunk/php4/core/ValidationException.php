<?php


class ValidationException extends SaintException
{	
	public $errors;
	public $code;
	public $file;
	public $line;
	
// ===========================================================
// - Constructor
// ===========================================================
	function __construct($errors, $code=VALIDATION_ERROR, $file=false, $line=false) {
		parent::__construct('A Validation Exception has occurred.', $code);
		if ($file !== false) $this->file = $file;
		if ($line !== false) $this->line = $line;
		$this->errors = $errors;
		$this->message = $this->get_message_from_errors();
	}

// ===========================================================
// - MAKE THE ERROR MESSAGE FROM THE ERRORS ARRAY
// ===========================================================
	function get_message_from_errors() {
		$msg = array();
		foreach($this->errors as $err) {
			if (strpos($err[2], ':property') === false) $err[2] = ":property ".$err[2];

			$msg[] = ucfirst(trim(str_replace(':property', $err[0], $err[2])));
		}
		return join("<br />", $msg);
	}


}



?>