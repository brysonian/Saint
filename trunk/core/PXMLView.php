<?php

// requires PHTMLView
#__autoload('PHTMLView');


class PXMLView extends PHTMLView
{	
	function PXMLView($template) {
		parent::PHTMLView($template);
		
		# set the header
		$this->header = 'Content-Type: application/xml';
	}	
}


?>