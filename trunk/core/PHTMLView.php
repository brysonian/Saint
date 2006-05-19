<?php

class PHTMLView extends ViewCore
{
	
	function PHTMLView($template) {
		parent::ViewCore($template);
	}
	
	function parse($layout_template=false) {
		parent::parse($layout_template);
		
		# unpack the props
		extract($this->props);
		
		# trap the buffer
		ob_start();
		
		# include the template
		include $this->template;
		
		# get the buffer contents
		$parsed = ob_get_contents();
		
		# clean the buffer
		ob_clean();
		
		# if there is a layout
		if ($this->layout) {
			# validate it
			$templateinfo = ViewFactory::template_info($layout_template);
			
			# push the content into the layout
			$content_for_layout = $parsed;
			
			# include the template
			include $templateinfo['file'];
		
			# get the buffer contents
			$parsed = ob_get_contents();
		}
		
		# close the output buffer
		ob_end_clean();
		
		# save the result
		$this->parsed = $parsed;
		return $parsed;
	}
}


?>