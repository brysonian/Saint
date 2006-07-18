<?php

/**
* Base view for XML and XSL
**/
class XSLTView extends ViewCore
{
	
	protected $params = array();


	function XSLTView($template) {
		parent::ViewCore($template);
		
		# see if we're running sablo on PHP 4
		if (!class_exists('DomDocument')) {
			die("Couldn't find valid XSLT interface.");
		}
	}
	
	function render($controller_name, $layout_template=false) {
		parent::parse($controller_name, $layout_template);
		
		# make the source xml
		# make doc and root
		$xml = new DomDocument;
		$root = $xml->createElement(params('controller').'-'.params('action'));
		$root = $xml->appendChild($root);

		
		# unpack the props into xml
		foreach($this->props as $k=>$v) {
			# if it will become xml, do that, otherwise make a dumb tag
			if (method_exists($v, 'to_xml')) {
				$obj_xml = $v->to_xml();
				$obj_xml = $xml->importNode($obj_xml->documentElement, true);
				$root->appendChild($obj_xml);
			} else {
				$node = $xml->createElement($k);
				if (is_numeric($v)) {
					$cdata = $xml->createTextNode($v);
				} else {
					$cdata = $xml->createCDATASection($v);
				}
				$node->appendChild($cdata);
				$node = $root->appendChild($node);				
			}
		}
		$xsl = new DomDocument;
		$xsl->load($this->template);
		
		$proc = new xsltprocessor();
		$proc->importStyleSheet($xsl);
		echo $proc->transformToXML($xml);
	}

	function set_param($tagname, $value, $namespace='')	{
		$this->params[$tagname] = array(
			'value'		=> $value,
			'namespace'	=> $namespace);
	}
	
	
	
}

?>