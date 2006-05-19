<?php

/**
	Validates form values.

	@author Chandler McWilliams
	@version 2005-08-31
*/
class Validator
{
	var $errs;
	
	function Validator() {
		$errs = array();
	}
	
	function check($str, $name=false, $type='string') {
		if (empty($str)) {
			$this->add_error('cannot be empty.', $name);
			return false;
		}
		switch ($type) {
			case 'date':
				if ((strtotime($str) === false) || (strtotime($str) == -1)) {
					$this->add_error('is not a valid date.', $name);
					return false;
				}
				break;



			case 'email':
				if (!eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$", $str)) {
					$this->add_error('is not a valid email address.', $name);
					return false;
				}
				break;			
		}
		
		return true;
	}

	function clean($str) {
		// make clean
		strip_tags($str);
	}
	
	function check_and_clean($str, $name=false, $type='string') {
		$o = $this->check($str, $name, $type);
		if ($o) return $this->clean($str);
	}
	
	function add_error($message, $name=false) {
		if ($name) {
			$this->errs[$name] = ucfirst($name)." $message";
		} else {
			$this->errs[] = ucfirst($message);
		}
	}
	
	function get_errors() {
		if (empty($this->errs)) return false;
		return $this->errs;
	}


/**
	checks if a word is a curse word
*/
	function has_bad_words($str) {
		$badwords = array("Pis","piss","breasts","bastard","BASTARD","b*stard","F*cking","Phuck","Kike","Fuck","f*ck","fuc","fu*k","fuc*","f**k","f_ck","f__k","f@ck","Fu??","Fa??","F*??","F'cking","fuckin'","Shit","sh|t","sh!t","sh*t","shit's","shitty","nigger","n*gger","n-gger","n_gger","darky","darkies","asshole","cunt","c*nt","c_nt","c/nt,","c-nt","pussy","p*ssy","fucker","slut","sl*t","dickhead","d*ck","f*cker","n*gg*r","clit","prick","faggot","f*gg*t","b*tch","wh*re","f@ggot","tw@t","goddamn","godamn","d@mn","gnikcuf","motherfucker","dickhead","blowjob","cocksucker","c*ck","c*sucker","c*cks*cker","asswipe","assmunch","fucking","fucked","feck","whatafucking","fags","fag","FAG","Fag","FAGS","Fags","Shitting","Shits","Chink","Buttsex","Shithole","Bunghole","Butthole","Bullshit","Bullshitter","Bullshiter","Bullshiters","Assman","Shit","Bullshit","Assfucker","Tit","Tits","Cuntrag","Bitch","Bitchie","Bitchy","Whore","Motherfucker","vagina");
		foreach ($badwords as $word) {
			if (stripos($str, $word) !== false) return true;
		}
		return false;
	}

}

?>