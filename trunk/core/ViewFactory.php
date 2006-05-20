<?php

class ViewFactory
{
	
	function ViewFactory() {}
	
	static function  make_view($template, $layout=false) {
		# get the template type
		$tempinfo = ViewFactory::template_info(PROJECT_VIEWS.'/'.$template);

		# return the right view depending on the extension of the template
		switch ($tempinfo['type']) {
			case 'phtml':
				# make sure it's loaded
				#__autoload('PHTMLView');
				$the_view = new PHTMLView($tempinfo['file']);
				break;

			case 'pxml':
				# make sure it's loaded
				#__autoload('PXMLView');				
				$the_view = new PXMLView($tempinfo['file']);
				break;
				
			case 'xsl':
				$the_view = new XSLTView($tempinfo['file']);
				break;
				
			default:
				throw(new SaintException("ViewFactory doesn't know what view object to use for ".$tempinfo['file']."."));

		}
		return $the_view;
	}
	
	/**
	* Given a path to a template without an extension, returns the type of the template
	* and the filesystem path of the template including the extension
	*/
	static function template_info($template) {
		switch (true) {
			case file_exists("$template.phtml"):
				return array(
					'type'		=> 'phtml',
					'file'		=> "$template.phtml",
					'template'	=> $template
				);
							
			case file_exists("$template.pxml"):
				return array(
					'type'		=> 'pxml',
					'file'		=> "$template.pxml",
					'template'	=> $template
				);

			case file_exists("$template.xsl"):
				return array(
					'type'		=> 'xsl',
					'file'		=> "$template.xsl",
					'template'	=> $template
				);
	
			default:
				throw(new SaintException("No html, xml, or xsl template was found at $template."));
		}
	}
}

?>