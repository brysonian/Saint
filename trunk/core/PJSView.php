<?php

class PJSView extends PHTMLView
{	
	function __construct($template) {
		parent::__construct($template);
		
		# set the header
		$this->header = 'Content-Type: text/javascript';
	}	
}


?>