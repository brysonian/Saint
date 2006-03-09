<?php


class ControllerCore
{
	
	var $_viewname;
	var $_layout		= false;
	var $_templatebase;
	var $_forcedTemplate;
	var $_beforefilters;
	var $_afterfilters;
	var $_beforefilterexceptions = array();
	var $_afterfilterexceptions = array();
	
// ===========================================================
// - CONSTRUCTOR
// ===========================================================
	function ControllerCore() {
		# set the template base
		$this->setTemplateBase(str_replace('controller', '', strtolower(get_class($this))));
		
		# call if there is an appinit() method in the App class
		if (method_exists($this, 'appinit')) $this->appinit();
		
		# if there is an init method, call it		
		if (method_exists($this, 'init')) $this->init();
	}


// ===========================================================
// - EXECUTE A URL ACTION
// ===========================================================
	function execute($theMethod) {
		# make sure the method exists
		if (!method_exists($this, $theMethod)) {
			# if it doesn't, look for default
			if (method_exists($this, '_index')) {
				$theMethod = '_index';
			} else {
				# if that's no good
				# cause an error
				throw(new Exception("Invalid action ".substr($theMethod,1).".", 0));
			}
		}
		
		# perform the before filters
		$bf = $this->getBeforeFilters();
		$bfe = $this->getBeforeFilterExceptions();
		$ok = true;
		$testMethod = substr($theMethod,1);
		if ($bf) {
			foreach ($bf as $method => $filters) {
				# if it's excepted, skip
				if (array_key_exists($testMethod, $bfe)) continue;
				
				# if it's a global filter or one for this method
				if ($method == '*' || $method == $testMethod) {


					# loop through all filters and call each
					foreach($filters as $filter) {
						$fok = call_user_func($filter);
					
						# only fail if it's really false not just undef
						if ($fok === false) $ok = false;
					}
				}
			}
		}

		# call the method if none of the filters returned false
		if ($ok) call_user_func(array(&$this, $theMethod));
		
		# perform the after filters
		$af = $this->getAfterFilters();
		$afe = $this->getAfterFilterExceptions();
		if ($af) {
			foreach ($af as $method => $filters) {
				# if it's excepted, skip
				if (array_key_exists($testMethod, $afe)) continue;

				# if it's a global filter or one for this method
				if ($method == '*' || $method == $theMethod) {
					# loop through all filters and call each
					foreach($filters as $filter) {
						call_user_func($filter);
					}
				}
			}
		}
		
		# render the view
		$this->renderView(substr($theMethod,1));
	}
	

// ===========================================================
// - RENDER
// ===========================================================
	function renderView($viewname=false) {
		$view =& $this->getViewForAction($viewname);
		# add all user data to the view
		foreach($this as $k=>$v) {
			if ($k{0} != '_') $view->addProp($k, $v);
		}
		$view->render($this->getLayout());
	}




// ===========================================================
// - RETURN THE CORRECT VIEW FOR DIFFERENT SITUATIONS
// ===========================================================		
	// get a view using the template
	// this is the only method that returns an actual view object
	function & getView($template) {
		return ViewFactory::make_view($template);
	}

	// get a view object using the specified action
	function & getViewForAction($action) {
		if ($this->getForcedTemplate()) {
			$template = $this->getForcedTemplate();
		} else {
			$template = $this->getTemplateBase().$action;
		}
		return $this->getView($template);
	}





// ===========================================================
// - ACCESSORS
// ===========================================================
	# get/set the template base
	function getTemplateBase() { return PROJECT_VIEWS.'/'.$this->_templatebase.'/';}
	function setTemplateBase($v) { $this->_templatebase = $v; }

	
	# get/set the forced template
	function getForcedTemplate() {
		if ($this->_forcedTemplate) return PROJECT_VIEWS.'/'.$this->_forcedTemplate;
		return false;
	}
	
	function force_template($template) {
		$this->_forcedTemplate = $template;
	}

	function silent() {
		$this->forceTemplate(false);
	}

	function setLayout($x) {
		$this->_layout = $x;
	}

	function getLayout() {
		if ($this->_layout) return PROJECT_VIEWS.'/layouts/'.$this->_layout;
		return false;
	}


// ===========================================================
// - FILTER METHODS
// ===========================================================
	function filterBefore($filter, $methods='*', $except=false) { $this->addFilter('before', $filter, $methods, $except); }
	function filterAfter($filter, $methods='*', $except=false) { $this->addFilter('after', $filter, $methods, $except); }

	function filterBeforeExcept($filter, $except) { $this->addFilter('before', $filter, '*', $except); }
	function filterAfterExcept($filter, $except) { $this->addFilter('after', $filter, '*', $except); }
	
	
	function addFilter($type, $filter, $methods='*', $except=false) {
		# grab the filter array to use
		if ($type = 'before') {
			$farray =& $this->getBeforeFilters();
		} else {
			$farray =& $this->getAfterFilters();
		}			

		# make sure the arry is set
		if (!is_array($farray)) $farray = array();
		
		# if methods is an array, then add this filter
		# to each of those methods, otherwise add it to the
		# specified method
		# * applies to all methods and is the default
		if (is_array($methods)) {
			foreach ($methods as $method) {
				# check that there isn't already a filter
				# if there is add this one on
				if (!array_key_exists($method, $farray)) $farray[$method] = array();
				$farray[$method][] = $filter;
			}
		} else {
			if (array_key_exists($methods, $farray) && !is_array($farray[$methods])) $farray[$methods] = array();
			$farray[$methods][] = $filter;
		}
		
		# if except is an array, add those to the skip list
		if ($except !== false) {
			# get the right exceptions array
			if ($type = 'before') {
				$earray =& $this->getBeforeFilterExceptions();
			} else {
				$earray =& $this->getAfterFilterExceptions();
			}			

			if (is_array($except)) {
				foreach ($except as $method) {
					# check that there isn't already a filter
					# if there is add this one on
					if (!is_array($earray[$method])) $earray[$method] = array();
					$earray[$method][] = $filter;
				}
			} else {
				if (array_key_exists($except, $earray) && !is_array($earray[$except])) $earray[$except] = array();
				$earray[$except][] = $filter;
			}
		}
		
	}

	function &getBeforeFilters() { return $this->_beforefilters; }
	function &getAfterFilters() { return $this->_afterfilters; }

	function &getBeforeFilterExceptions() { return $this->_beforefilterexceptions; }
	function &getAfterFilterExceptions() { return $this->_afterfilterexceptions; }




// ===========================================================
// - REDIRECT TO A NEW CONTROLLER
// ===========================================================
	function redirect_to($controller, $action=false, $id=false, $params=false) {

		# build URL
		$url = "/$controller";
		
		if ($action) $url .= "/$action";
		
		# if there is an ID, add it
		if ($id) $url .= "?item=$id";
		
		# if there are params, add them
		if (is_array($params)) {
			foreach ($params as $k=>$v) {
				$url .= "&$k=$v";
			}
		}
		if (substr($url, 0,2) == '//') $url = substr($url, 1);
		$url = realpath($url)?realpath($url):$url;
		header('Location: '.WEBBASE.$url);
		
	}

}


?>