<?php


class ViewCore
{

	var $template = false;
	var $parsed		= false;
	var $layout		= false;
	var $props		= array();
	var $header		= false;


	function ViewCore($template) {
		$this->template = $template;
	}

	// accessors for properties
	function add_prop($k, $v) {
		$this->props[$k] = $v;
	}
	
	function get_prop($k) {
		return $this->props[$k];
	}
	
	// render the page
	function render($layout_template=false) {
		if (!$this->parsed) {
			$this->parse($layout_template);
		}
		
		# set the header if there is one
		if ($this->header !== false) header($this->header);
		
		# echo the page
		echo $this->parsed;
	}

	function parse($layout_template=false) {
		# save the layout
		$this->layout = $layout_template;
	}
}

// ===========================================================
// - MAKE RENDER_PARTIAL EASIER TO GET TO
// ===========================================================
	/**
	 render_partial
	 usage examples:
		render_partial(array('action'=>'partial', 'obj'=>array('one', 'two'), 'collect'=>true))
		render_partial(false, 'partial')	
	*/
	function render_partial($controller, $action=false, $obj=false, $collect=false) {
		#if the controller is an array, use it and ignore the rest
		if (is_array($controller)) {
			if (array_key_exists('obj', $controller))			$obj = $controller['obj'];
			if (array_key_exists('action', $controller))	$action = $controller['action'];
			if (array_key_exists('collect', $controller))	$collect = $controller['collect'];
			if (array_key_exists('controller', $controller)) {
				$controller = $controller['controller'];
			} else {
				$controller = params('controller');
			}
			
		} else if ($action === false) {		
			# if action is false, use the controller as the action
			# name on the current controller
			$action = $controller;
			$controller = params('controller');
		
		} else if ($controller == false) {
			# if controller is false, use the current
			$controller = params('controller');
		}
		
		# make an instance of the controller class
		$conname = "{$controller}Controller";
		$theController = new $conname;

		# set the method name
		$theMethod = '_'.$action;
		
		# if it's a collection, call it for each item in the obj
		if ($collect === true) {
			foreach($obj as $k => $v) {
				# add the obj to the controller if there is one
				if ($obj !== false) $theController->$action = $v;

				# tell the controller to execute the action
				$theController->execute($theMethod);
			}
		} else {
			# add the obj to the controller if there is one
			if ($obj !== false) $theController->$action = $obj;

			# tell the controller to execute the action
			$theController->execute($theMethod);
		}
	}

?>