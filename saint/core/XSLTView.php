<?php

/**
* Base view for XML and XSL
**/
class XSLTView extends AbstractView
{
	
	protected $params = array();


	function __construct($template) {
		parent::__construct($template);
		
		# see if we're running sablo on PHP 4
		if (!class_exists('DomDocument')) {
			die("Couldn't find valid XSLT interface.");
		}
	}
	
	function render($controller_name, $layout_template=false) {
		parent::parse($controller_name, $layout_template);
				
		$xsl = new DomDocument;
		$xsl->load($this->template);
		
		$proc = new xsltprocessor();
		$proc->importStyleSheet($xsl);
		echo $proc->transformToXML($this->props_to_xml());
	}

	function set_param($tagname, $value, $namespace='')	{
		$this->params[$tagname] = array(
			'value'		=> $value,
			'namespace'	=> $namespace);
	}
	
	
	
}

?>