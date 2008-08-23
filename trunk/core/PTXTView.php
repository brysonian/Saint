<?php

class PTXTView extends AbstractView
{	
	function __construct($template) {
		parent::__construct($template);
		
		# set the header
		$this->header = 'Content-Type: application/text';
	}	
}


?>