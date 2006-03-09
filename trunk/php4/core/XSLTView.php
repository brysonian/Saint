<?php

/**
* Base view for XML and XSL
**/
class XSLTView extends ViewCore
{
	
	var $_xml;
	var $_xsl;
	var $_mode;
	var $_params = array();


	function XSLTView($xml, $xsl) {
		$this->_xml = $xml;
		$this->_xsl = $xsl;
		
		# see if we're running sablo on PHP 4
		if (function_exists('xslt_create')) {
			$this->_mode = 4;
		} else if (class_exists('DomDocument')) {
			$this->_mode = 5;
		} else {
			die("Couldn't find valid XSLT interface.");
		}
	}
	
	function display() {
		if ($this->_mode == 4) {
			echo $this->sablotron();
		} else if ($this->_mode == 5) {
			echo $this->domdoc();
		}
	}

	function setParam($tagname, $value, $namespace='')	{
		$this->_params[$tagname] = array(
			'value'		=> $value,
			'namespace'	=> $namespace);
	}
	
	function sablotron() {
		# perform XSLT
		# create parser
		$parser = xslt_create() or die("Can't create XSLT handle!");
		
		# Perform the XSL transformation
		# need to switch if either var is a string of xml not a file
		$params = array();
		if (!file_exists($this->_xml)) {
			$params['/_xml'] = $this->_xml;
		}
		if (!file_exists($this->_xsl)) {
			$params['/_xsl'] = $this->_xsl;
		}
		
		# if both are strings
		if (array_key_exists('/_xml', $params) && array_key_exists('/_xsl', $params)) {
			$output = xslt_process($parser, 'arg:/_xml', 'arg:/_xsl', NULL, $params);
			
		# if only xml is string
		} else if (array_key_exists('/_xml', $params)) {
			$output = xslt_process($parser, 'arg:/_xml', $this->_xsl, NULL, $params);
			
		# if only xsl is string
		} else if (array_key_exists('/_xsl', $params)) {
			$output = xslt_process($parser, $this->_xml, 'arg:/_xsl', NULL, $params);
		
		# if both are files
		} else {
			$output = xslt_process($parser, $this->_xml, $this->_xsl);
		}
				
		# free the parser
		xslt_free($parser);

		# output the transformed XML file
		return $output;
	}	

	function domdoc() {
		$xml = new DomDocument();
		if (file_exists($this->_xml)) {
			$xml->load($this->_xml);
		} else {
			$xml->loadXML($this->_xml);
		}
		
		$xsl = new DomDocument;
		if (file_exists($this->_xsl)) {
			$xsl->load($this->_xsl);
		} else {
			$xsl->loadXML($this->_xsl);
		}
		
		$proc = new xsltprocessor();
		$proc->importStyleSheet($xsl);
		return $proc->transformToXML($xml);
	}
	
	
}

?>