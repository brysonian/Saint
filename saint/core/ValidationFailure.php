<?php


class ValidationFailure extends SaintException
{	
	public $errors;
	public $code;
	public $file;
	public $line;
	public $parent_class;
	
// ===========================================================
// - Constructor
// ===========================================================
	public function __construct($errors, $parent_class, $code=VALIDATION_ERROR, $file=false, $line=false) {
		parent::__construct('A Validation Exception has occurred.', $code);
		$this->parent_class = $parent_class;
		if ($file !== false) $this->file = $file;
		if ($line !== false) $this->line = $line;
		$this->errors = $errors;
		$this->message = $this->get_message_from_errors(false);
	}

// ===========================================================
// - MAKE THE ERROR MESSAGE FROM THE ERRORS ARRAY
// ===========================================================
	public function get_message_from_errors($html=true) {
		$msg = ($html)?'<ul>':'';
		foreach($this->errors as $err) {
			#if (strpos($err[2], ':property') === false) $err[2] = ":property ".$err[2];
			if ($html) $msg .= '<li>';
			$msg .= ucfirst(trim(str_replace(':property', $err[0], $err[2])));
			$msg .= ($html)?'</li>':"\n";
		}
		if ($html) $msg .= '</ul>';
		return $msg;
	}
	
	public function __toString() {
		$html = '<div class="error">';
		$c = count($this->errors);
		$html .= '<h2>'.$c.' error'.(($c>1)?'s were':' was').' encountered while trying to save this '.$this->parent_class.'.</h2>';
		$html .= $this->get_message_from_errors();
		$html .= '</div>';
		return $html;
	}

}



?>