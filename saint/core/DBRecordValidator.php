<?php

/**
	Used as a mixin to validate a DBRecord

	@author Chandler McWilliams
	@version 2006-05-23
*/
class DBRecordValidator {
	
	protected $errors;
	protected $parent_class;
	protected $parent;
	protected $data;

	protected $validate_presence_of			= array();
	protected $validate_numericality_of	= array();
	protected $validate_truth_of				= array();
	protected $validate_falsity_of			= array();
	protected $validate_date_of					= array();
	protected $validate_uniqueness_of		= array();
	protected $validate_format_of				= array();
	protected $validate_politeness_of		= array();
	protected $validate_image_of				= array();

	
// ===========================================================
// - CONSTRUCTOR
// ===========================================================
	public function __construct($parent) {
		$errors = array();
		$this->parent = $parent;
		$this->parent_class = get_class($parent);
	}


// ===========================================================
// - ACCESSORS
// ===========================================================
	public function errors() {
		if (!empty($this->errors)) 
			return new ValidationFailure($this->errors, $this->parent_class);
		
		return false;
	}

	public function add_error($name, $message, $code=0) {
		$this->errors[] = array($name, $code, $message);
	}


// ===========================================================
// - EXECUTE THE VALIDATION
// ===========================================================
	
	# does the actual validation
	public function validate(&$data) {
		$this->data =& $data;
		
		# check all the validation lists and make sure each one is ok
		$noErr = true;
		
		# presence
		foreach($this->validate_presence_of as $prop => $msg) {
			$noErr = $this->test_presence_of($prop, $msg);
		}
		
		# numericality
		foreach($this->validate_numericality_of as $prop => $msg) {
			$noErr = $this->test_numericality_of($prop, $msg);
		}

		# truth
		foreach($this->validate_truth_of as $prop => $msg) {
			$noErr = $this->test_truth_of($prop, $msg);
		}

		foreach($this->validate_falsity_of as $prop => $msg) {
			$noErr = $this->test_falsity_of($prop, $msg);
		}

		# date
		foreach($this->validate_date_of as $prop => $msg) {
			$noErr = $this->test_date_of($prop, $msg);
		}
		
		# uniqueness
		foreach($this->validate_uniqueness_of as $prop => $msg) {
			$noErr = $this->test_uniqueness_of($prop, $msg);
		}
		
		# format
		foreach($this->validate_format_of as $prop => $args) {
			$noErr = $this->test_format_of($prop, $args);
		}
		
		# curse words
		foreach($this->validate_politeness_of as $prop => $args) {
			$noErr = $this->test_politeness_of($prop, $args);
		}
		
		# images
		foreach($this->validate_image_of as $prop => $args) {
			$noErr = $this->test_image_of($prop, $args);
		}		
		
		return $noErr;
	}


// ===========================================================
// - VALIDATION OPERATIONS
// ===========================================================
	# the general form of the validation adding methods,
	# shared by most of them
	public function validates_x_of(&$type, $args, $code) {
		if ($args === false) {
			$type = array();
			return;
		}

		# add the error message if there isn't one
		if (!array_key_exists('message', $args)) {
			$args['message'] = get_error_message($code, '');
		}

		# add each prop as a key to the array
		foreach($args as $k => $v) {
			if (is_numeric($k)) {
				$type[strtolower($v)] = $args['message'];
			}
		}
	}

// ===========================================================
// - PRESENCE
// ===========================================================
	public function validates_presence_of($args) {
		
		#$args = func_get_args();
		if (!is_array($args) && $args !== false) $args = func_get_args();
		// $fargs = func_get_args();
		// if (!is_array($args) && $args !== false || (count($fargs) > 1)) {
		// 	$args = $fargs;
		// }
		$this->validates_x_of($this->validate_presence_of, $args, VALIDATION_EMPTY);
	}

	protected function test_presence_of($prop, $msg) {
		if (array_key_exists($prop, $this->data)) {
			$v = trim($this->data[$prop]);
			if (!empty($v))	return true;
		}

		$this->add_error(human_name($prop), $msg, VALIDATION_EMPTY);
		return false;
	}


// ===========================================================
// - NUMERICAL
// ===========================================================
	public function validates_numericality_of($args) {
		if (!is_array($args) && $args !== false) $args = func_get_args();
		$this->validates_x_of($this->validate_numericality_of, $args, VALIDATION_NUMERIC);
	}
	
	protected function test_numericality_of($prop, $msg) {
		if (!array_key_exists($prop, $this->data)) return true;

		if (is_numeric($this->data[$prop]))
			return true;

		$this->add_error(human_name($prop), $msg, VALIDATION_NUMERIC);
		return false;
	}
	

// ===========================================================
// - TRUTH
// ===========================================================
	public function validates_truth_of($args) {
		if (!is_array($args) && $args !== false) $args = func_get_args();
		$this->validates_x_of($this->validate_truth_of, $args, VALIDATION_TRUTH);
	}
	
	protected function test_truth_of($prop, $msg) {
		if (array_key_exists($prop, $this->data) && is_numeric($this->data[$prop]) && $this->data[$prop] == 1)
			return true;

		$this->add_error(human_name($prop), $msg, VALIDATION_TRUTH);
		return false;
	}

	
	public function validates_falsity_of($args) {
		if (!is_array($args) && $args !== false) $args = func_get_args();
		$this->validates_x_of($this->validate_falsity_of, $args, VALIDATION_FALSITY);
	}
	
	protected function test_falsity_of($prop, $msg) {
		if (!array_key_exists($prop, $this->data)) return true;

		if (is_numeric($this->data[$prop]) && $this->data[$prop] == 0)
			return true;

		$this->add_error(human_name($prop), $msg, VALIDATION_FALSITY);
		return false;
	}
	

	
// ===========================================================
// - DATE
// ===========================================================
	public function validates_date_of($args) {
		if (!is_array($args) && $args !== false) $args = func_get_args();
		$this->validates_x_of($this->validate_date_of, $args, VALIDATION_DATE);
	}

	protected function test_date_of($prop, $msg) {
		if (!array_key_exists($prop, $this->data) || empty($this->data[$prop]) || ($this->data[$prop] == '0000-00-00')) {
			$this->data[$prop] = NULL;
			return true;
		}
		$d = strtotime($this->data[$prop]);
		if ($d === false || $d == -1) {
			$this->add_error(human_name($prop), $msg, VALIDATION_DATE);
			return false;
		}
		$this->data[$prop] = Format::mysqlDateTime($this->data[$prop]);
		return true;
	}



// ===========================================================
// - UNIQUE
// ===========================================================
	public function validates_uniqueness_of($args) {
		if (!is_array($args) && $args !== false) $args = func_get_args();
		$this->validates_x_of($this->validate_uniqueness_of, $args, VALIDATION_UNIQUE);
	}

	protected function test_uniqueness_of($prop, $msg) {
		if (!array_key_exists($prop, $this->data)) return true;
		$testval = is_object($this->data[$prop])?$this->data[$prop]->__toString():$this->data[$prop];
		
		$where = $this->parent->get_table().".$prop = '".$this->parent->escape_string($testval)."'";
		if ($this->parent->get_id()) $where .= " AND ".$this->parent->get_table().".id <> ".$this->parent->get_id();
		
		$r = DBRecord::find_where($where, array('class'=>$this->parent_class));
		// TODO: changed this to use count, watch it
		if (count($r) > 0) {
			$this->add_error(human_name($prop), $msg, VALIDATION_UNIQUE);
			return false;
		}
		return true;
	}



// ===========================================================
// - FORMAT
// ===========================================================
	public function validates_email_of($prop, $message=false) {
		$this->validates_format_of($prop, '/^[_a-zA-Z0-9-]+(\.[_a-zA-Z0-9-]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*(\.[a-zA-Z]{2,3})$/', get_error_message(VALIDATION_EMAIL, $prop));
	}
	
	public function validates_url_of($prop, $message=false) {
		$this->validates_format_of($prop, 
			'/(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/xis',
			get_error_message(VALIDATION_URL, $prop));
	}
	
	public function validates_format_of($prop, $format, $message=false) {
		if ($prop === false) {
			$this->validate_format_of = array();
		} else {
			if (!is_array($prop)) $prop = array($prop);
			foreach($prop as $k => $p) {
				# add the error message if there isn't one
				$message = $message?$message:get_error_message(VALIDATION_FORMAT, $p);
				$this->validate_format_of[strtolower($p)] = array($format, $message);
			}
		}
	}

	protected function test_format_of($prop, $args) {
		if (!array_key_exists($prop, $this->data)) return true;
		if (empty($this->data[$prop])) return true;
		if (preg_match($args[0], $this->data[$prop]) == 0) {
			$this->add_error(human_name($prop), $args[1], VALIDATION_FORMAT);
			return false;
		}
		return true;
	}


// ===========================================================
// - POLITENESS
// ===========================================================
	public function validates_politeness_of($args) {
		if (!is_array($args) && $args !== false) $args = func_get_args();
		$this->validates_x_of($this->validate_politeness_of, $args, VALIDATION_CURSE);
	}

	protected function test_politeness_of($prop, $msg) {
		if (!array_key_exists($prop, $this->data)) return true;
		$badwords = array("pis","piss","breasts","bastard","bastard","b*stard","f*cking","phuck","kike","fuck","f*ck","fuc","fu*k","fuc*","f**k","f_ck","f__k","f@ck","fu??","fa??","f*??","f'cking","fuckin'","shit","sh|t","sh!t","sh*t","shit's","shitty","nigger","n*gger","n-gger","n_gger","darky","darkies","asshole","cunt","c*nt","c_nt","c/nt,","c-nt","pussy","p*ssy","fucker","slut","sl*t","dickhead","d*ck","f*cker","n*gg*r","clit","prick","faggot","f*gg*t","b*tch","wh*re","f@ggot","tw@t","goddamn","godamn","d@mn","gnikcuf","motherfucker","dickhead","blowjob","cocksucker","c*ck","c*sucker","c*cks*cker","asswipe","assmunch","fucking","fucked","feck","whatafucking","fags","fag","fag","fag","fags","fags","shitting","shits","chink","buttsex","shithole","bunghole","butthole","bullshit","bullshitter","bullshiter","bullshiters","assman","shit","bullshit","assfucker","tit","tits","cuntrag","bitch","bitchie","bitchy","whore","motherfucker","vagina");
		foreach ($badwords as $word) {
			if (stripos(strtolower($this->data[$prop]), $word) !== false) {
				$this->add_error(human_name($prop), $msg, VALIDATION_CURSE);
				return false;
			}
		}
		return true;
	}
	
// ===========================================================
// - IMAGENESS
// ===========================================================
		
	public function validates_image_of($args) {
		if (!is_array($args) && $args !== false) $args = func_get_args();
		$this->validates_x_of($this->validate_image_of, $args, VALIDATION_IMAGE);
	}
	
	protected function test_image_of($prop, $msg) {
		if (!array_key_exists($prop, $this->data)) return true;
		if ($this->data[$prop] instanceof UploadedFile){
			if($this->data[$prop]->is_image()){
				return true;
			}else{
				$this->add_error(human_name($prop), $msg, VALIDATION_IMAGE);
				return false;
			} 
		}else{
			// Allow null
			return true;
		}		
	}	
	
}

?>