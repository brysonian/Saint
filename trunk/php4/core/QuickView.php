<?php

class QuickView extends ViewCore
{
	var $_data		= false;
	var $_template = false;
	var $_parsed	= false;
	var $_layout	= false;
	var $_props		= array();
	
	function QuickView($template) {
		$this->_data = array();
		$this->_template = $template;
	}
	
	function addProp($k, $v) {
		$this->_props[$k] = $v;
	}
	
	function getProp($k) {
		return $this->_props[$k];
	}
	
	function parse($layout_template=false) {
		# save the layout
		$this->_layout = $layout_template;

		# unpack the props
		extract($this->_props);
		
		# local ref to the data
		$data = $this->_data;
		
		# trap the buffer
		ob_start();
		
		# include the template
		include $this->_template;
		
		# get the buffer contents
		$parsed = ob_get_contents();
		
		# clean the buffer
		ob_clean();
		
		# if there is a layout
		if ($this->_layout) {
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
		$this->_parsed = $parsed;
	}
	
	function render($layout_template=false) {
		if (!$this->_parsed) {
			$this->parse($layout_template);
		}
		echo $this->_parsed;
	}

	function render_partial($controller, $action, $id=false) {
		# include the right classes
		# this can be killed in PHP5
		__autoload(ucfirst($controller.'Controller'));
		__autoload($controller);

		# make an instance of the controller class
		$conname = "{$controller}Controller";
		$theController = new $conname;

		# set the method name
		$theMethod = '_'.$action;

		# add all user data from this view to the controller
		# add all user data to the view
		foreach($this->_props as $k=>$v) {
			$theController->$k = $v;
		}


		# tell the controller to execute the action
		$theController->execute($theMethod, $id);
	}

}
?>