<?php

class ViewFactory
{
	
	function __construct() {}
	
	static function  make_view($template, $layout=false) {
		# get the template type
		$template = PROJECT_VIEWS.'/'.$template;
		$type = ViewFactory::get_template_type($template);
		
		if ($type === false) {
		 throw(new MissingTemplate("No recognizable template was found at $template."));
		}
		
		# return the right view depending on the extension of the template
		switch ($type) {
			case 'phtml':
				$the_view = new PHTMLView("$template.$type");
				break;

			case 'pxml':
				$the_view = new PXMLView("$template.$type");
				break;
				
			case 'pjs':
				$the_view = new PJSView("$template.$type");
				break;

			case 'xsl':
				$the_view = new XSLTView("$template.$type");
				break;
				
			default:
				throw(new UnknownViewType("ViewFactory doesn't know how to use a $type file."));

		}
		return $the_view;
	}	

	static function get_template_type($template) {
		if (file_exists("$template.phtml")) return "phtml";
		if (file_exists("$template.pxml")) return "pxml";
		if (file_exists("$template.pjs")) return "pjs";
		if (file_exists("$template.xsl")) return "xsl";
		return false;		
	}
}

// ===========================================================
// - EXTENSIONS
// ===========================================================
class UnknownViewType extends SaintException {}
class MissingTemplate extends SaintException {}

?>