<?php


class ViewCore
{

	protected $template = false;
	protected $parsed		= false;
	protected $layout		= false;
	protected $props		= array();
	protected $header		= false;


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

	function set_all_props($p) {
		return $this->props = $p;
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
	
	function parse_partial($var=false, $obj=false) {
		if ($var) {
			$$var = $obj;
		}

		# trap the buffer
		ob_start();

		# include the template
		include $this->template;

		# get the buffer contents
		return ob_get_clean();
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
	function render_partial($action, $object=false, $collect=false) {
		#if the controller is an array, use it and ignore the rest
		if (is_array($action)) {
			if (array_key_exists('object', $action))	$object = $action['object'];
			if (array_key_exists('action', $action))	$action = $action['action'];
			if (array_key_exists('collect', $action))	$collect = $action['collect'];			
		}

		$controller = params('controller');

		# set the template and object name
		$slashloc = strrpos($action, '/');
		if ($slashloc !== false) {			
			$objectname = substr($action, $slashloc+1);
			$template = substr($action, 0, $slashloc).'/_'.$objectname;
		} else {
			$template = strtolower($controller).'/_'.$action;
			$objectname = $action;
		}

		$view = ViewFactory::make_view($template);
					
		# if it's a collection, call it for each item in the obj
		if ($collect === true) {
			foreach($object as $k => $v) {
				echo $view->parse_partial($objectname, $v);
			}

		} else {
			echo $view->parse_partial($objectname, $object);				
		}
	}
?>