<?php


class AbstractView
{

	protected $template = false;
	protected $parsed		= false;
	protected $layout		= false;
	protected $props		= array();
	protected $header		= false;


	function AbstractView($template) {
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
	
	function props_to_xml() {
		# make the source xml
		# make doc and root
		$xml = new DomDocument;
		$root = $xml->createElement('request');
		$root->setAttribute('controller', params('controller'));
		$root->setAttribute('action', params('action'));

		$root = $xml->appendChild($root);
		
		# unpack the props into xml
		foreach($this->props as $k=>$v) {
			# if it will become xml, do that, otherwise make a dumb tag
			if (is_object($v) && method_exists($v, 'to_xml')) {
				$obj_xml = $v->to_xml(array(), true, true);
				$obj_xml = $xml->importNode($obj_xml->documentElement, true);
				$root->appendChild($obj_xml);
			} else {
				$node = $xml->createElement($k);
				if (((strpos($v, '<')!==false) || (strpos($v, '>')!==false) || (strpos($v, '&')!==false))) {
					$cdata = $xml->createCDATASection($v);
				} else {
					$cdata = $xml->createTextNode($v);
				}
				$node->appendChild($cdata);
				$node = $root->appendChild($node);				
			}
		}
		return $xml;
	}
	
	
	// render the page
	function render($controller_name, $layout_template=false) {
		# include the helpers
		if (file_exists(PROJECT_ROOT.'/app/helpers/app.php')) include_once(PROJECT_ROOT.'/app/helpers/app.php');
		if (file_exists(PROJECT_ROOT.'/app/helpers/'.$controller_name.'.php')) include_once(PROJECT_ROOT.'/app/helpers/'.$controller_name.'.php');

		if (!$this->parsed) $this->parse($layout_template);
		
		# set the header if there is one
		if ($this->header !== false) header($this->header);
		
		# echo the page
		echo $this->parsed;
	}

	function render_text($text='') { echo $text; }
	function render_xml($text=false) { 
		header('Content-Type: application/xml');
		if ($text==false) $text = $this->props_to_xml()->saveXML();
		if (is_object($text) && method_exists(get_class($text), 'to_xml')) {
			echo $text->to_xml();
		} else {
			echo $text;
		}
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
	// TODO: abs path with leading /
	function render_partial($action, $object=false, $collect=false, $controller=false) {
		#if the controller is an array, use it and ignore the rest
		if (is_array($action)) {
			if (array_key_exists('object', $action))			$object = $action['object'];
			if (array_key_exists('collect', $action))			$collect = $action['collect'];			
			if (array_key_exists('controller', $action))	$controller = $action['controller'];			
			if (array_key_exists('action', $action))			$action = $action['action'];
		} else {
			if ($controller == false)	$controller = params('controller');
		}

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


		# include the helpers
		if (file_exists(PROJECT_ROOT.'/app/helpers/app.php')) include_once(PROJECT_ROOT.'/app/helpers/app.php');
		if (file_exists(PROJECT_ROOT.'/app/helpers/'.$controller.'.php')) include_once(PROJECT_ROOT.'/app/helpers/'.$controller.'.php');


		# if it's a collection, call it for each item in the obj
		if ($collect === true) {
			foreach($object as $k => $v) {
				echo $view->parse_partial($objectname, $v);
			}

		} else {
			echo $view->parse_partial($objectname, $object);				
		}
	}





	// TODO: FINISH THE COMPONENTS
	function render_component($controller, $action=false, $object=false, $collect=false) {
		#if the controller is an array, use it and ignore the rest
		if (is_array($controller)) {
			if (array_key_exists('object', $controller))	$object = $controller['object'];
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
		$conname = to_class_name($controller).'Controller';
		$theController = new $conname;

		# set the method name
		$theMethod = '_'.$action;
		
		# see if there is a method with this name, if not just use the template
		$me = method_exists($theController, $theMethod);
		
		if (!$me) {
			$view = ViewFactory::make_view(strtolower($controller).'/'.$theMethod);
		}
					
		# if it's a collection, call it for each item in the obj
		if ($collect === true) {
			foreach($object as $k => $v) {
				if ($me) {
					# add the obj to the controller if there is one
					if ($object !== false) $theController->$action = $v;
				
					# tell the controller to execute the action
					$theController->execute($theMethod);
				} else {
					echo $view->parse_partial($controller, $v);
				}
			}

		} else {
			if ($me) {
				# add the obj to the controller if there is one
				if ($object !== false) $theController->$action = $object;

				# tell the controller to execute the action
				$theController->execute($theMethod);
			} else {
				echo $view->parse_partial($controller, $object);				
			}
		}
	}

?>