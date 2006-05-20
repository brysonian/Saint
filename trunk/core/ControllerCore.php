<?php

/**
	Base class for controller objects.
	All controllers should subclass this base class.
	
	Note that in the php4 version all instance vars are prefaced with a _, this
	is a trick to convert vars set in the controller methods into properties for the view
	in PHP 5 this is done using the magical __get and __set methods.

	@author Chandler McWilliams
	@version 2006-03-22
*/

class ControllerCore
{
	
	var $_viewname;
	var $_layout		= false;
	var $_templatebase;
	var $_forced_template;
	var $_beforefilters;
	var $_afterfilters;
	var $_beforefilterexceptions = array();
	var $_afterfilterexceptions = array();
	var $_cache_page = false;
	private static $_cache_extension = 'cache';
	
	
// ===========================================================
// - CONSTRUCTOR
// ===========================================================
	function ControllerCore() {		
		# set the template base
		$this->set_template_base(str_replace('controller', '', strtolower(get_class($this))));
		
		# call if there is an init() method in the App class
		$m = get_class_methods('AppController');
		if (in_array('init', $m)) AppController::init();
		
		# if there is an init method, call it		
		if (method_exists($this, 'init')) $this->init();
	}
	

// ===========================================================
// - EXECUTE A URL ACTION
// ===========================================================
	function execute($the_method) {
		# make sure the method exists
		if (!method_exists($this, $the_method)) {
			# if it doesn't, look for default
			if (method_exists($this, '_index')) {
				$the_method = '_index';
			} else {
				# if that's no good
				# cause an error
				throw(new SaintException("Invalid action ".substr($the_method,1).".", 0));
			}
		}
		
		# perform the before filters
		$bf = $this->get_before_filters();
		$bfe = $this->get_before_filter_exceptions();
		$ok = true;
		$test_method = substr($the_method,1);
		if ($bf) {
			foreach ($bf as $method => $filters) {
				# if it's excepted, skip
				if (array_key_exists($test_method, $bfe)) continue;
				
				# if it's a global filter or one for this method
				if ($method == '*' || $method == $test_method) {


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
		if ($ok) call_user_func(array($this, $the_method));
		
		# perform the after filters
		$af = $this->get_after_filters();
		$afe = $this->get_after_filter_exceptions();
		if ($af) {
			foreach ($af as $method => $filters) {
				# if it's excepted, skip
				if (array_key_exists($test_method, $afe)) continue;

				# if it's a global filter or one for this method
				if ($method == '*' || $method == $the_method) {
					# loop through all filters and call each
					foreach($filters as $filter) {
						call_user_func($filter);
					}
				}
			}
		}
		
		# render the view
		$this->render_view(substr($the_method,1));
	}
	

// ===========================================================
// - RENDER
// ===========================================================
	function render_view($viewname=false) {
		$view = $this->get_view_for_action($viewname);
		# add all user data to the view
		foreach($this as $k=>$v) {
			if ($k{0} != '_') $view->add_prop($k, $v);
		}

		# if we cache, do that
		if ($this->_cache_page && empty($_GET) && empty($_POST)) {
			$output = $view->parse($this->get_layout());
			$this->save_cache($_SERVER['REQUEST_URI'], $output);
		}
		$view->render($this->get_layout());
	}



// ===========================================================
// - CACHE
// ===========================================================
	function save_cache($path, $data) {
		if ($path == '/') $path = '/index';
		
		# trim trailing /
		$path = (strrpos($path, '/') == (strlen($path)-1))?substr($path,0, -1):$path;
		
		# parse path
		$dirs = explode('/', $path);

		# pop filename
		$file = array_pop($dirs).'.'.ControllerCore::_cache_extension;
		
		# new path
		$mkdir = PROJECT_ROOT.'/public_html';
		
		# create path
		foreach($dirs as $dir) {
			if (empty($dir)) continue;
			$mkdir .= '/'.$dir;

			if (!file_exists($mkdir)) {
				$ok = mkdir($mkdir);
				if (!$ok) return false;
			}
		}
		
		#save file
		$fp = fopen($mkdir.'/'.$file, "w");
		$ok = fwrite($fp, $data);
		fclose($fp);
		if (!$ok) {
			throw(new SaintException("Failed to write cache file to: ".$mkdir.'/'.$file.".", 0));
		}
		return true;
	}


	function clear_cache($path) {
		$base = PROJECT_ROOT.'/public_html';
	
		# trim trailing /
		$path = (strrpos($path, '/') == (strlen($path)-1))?substr($path,0, -1):$path;

		# parse path
		$dirs = explode('/', $path);
	
		# if the last item is a * then clear all cache files at that location
		$last = array_pop($dirs);
		if ($last == '*') {
			$targetpath = join('/', $dirs);
			if (file_exists($base.$targetpath)) {
				$dirhandle=opendir($base.$targetpath);
				while (($file = readdir($dirhandle))!==false) {
					$pi = pathinfo($file);
					if ($pi['extension'] == ControllerCore::_cache_extension) {
						unlink($base.$targetpath.'/'.$file);
					} else if ($file{0} != '.' && is_dir($base.$targetpath.'/'.$file)) {
						ControllerCore::clear_cache($targetpath.'/'.$file.'/*');
					}
				}
				closedir($dirhandle);
			}
		
			# also delete the cache file for the dir
			if (file_exists($base.$targetpath.'.'.ControllerCore::_cache_extension)) {
				unlink($base.$targetpath.'.'.ControllerCore::_cache_extension);
			}
		
		} else {
			unlink($base.$path.'.'.ControllerCore::_cache_extension);
		}
		
	}



// ===========================================================
// - RETURN THE CORRECT VIEW FOR DIFFERENT SITUATIONS
// ===========================================================		
	// get a view using the template
	// this is the only method that returns an actual view object
	function  get_view($template) {
		return ViewFactory::make_view($template);
	}

	// get a view object using the specified action
	function  get_view_for_action($action) {
		if ($this->get_forced_template()) {
			$template = $this->get_forced_template();
		} else {
			$template = $this->get_template_base().$action;
		}
		return $this->get_view($template);
	}





// ===========================================================
// - ACCESSORS
// ===========================================================
	# get/set the template base
	function get_template_base() { return $this->_templatebase.'/';}
	function set_template_base($v) { $this->_templatebase = $v; }

	
	# get/set the forced template
	function get_forced_template() {
		if ($this->_forced_template) return PROJECT_VIEWS.'/'.$this->_forced_template;
		return false;
	}
	
	function force_template($template) {
		$this->_forced_template = $template;
	}

	function set_layout($x) {
		$this->_layout = $x;
	}

	function get_layout() {
		if ($this->_layout) return PROJECT_VIEWS.'/layouts/'.$this->_layout;
		return false;
	}


// ===========================================================
// - FILTER METHODS
// ===========================================================
	function filter_before($filter, $methods='*', $except=false) { $this->add_filter('before', $filter, $methods, $except); }
	function filter_after($filter, $methods='*', $except=false) { $this->add_filter('after', $filter, $methods, $except); }

	function filter_before_except($filter, $except) { $this->add_filter('before', $filter, '*', $except); }
	function filter_after_except($filter, $except) { $this->add_filter('after', $filter, '*', $except); }
	
	
	function add_filter($type, $filter, $methods='*', $except=false) {
		# grab the filter array to use
		if ($type = 'before') {
			$farray = $this->get_before_filters();
		} else {
			$farray = $this->get_after_filters();
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
				$earray = $this->get_before_filter_exceptions();
			} else {
				$earray = $this->get_after_filter_exceptions();
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

	function  get_before_filters() { return $this->_beforefilters; }
	function  get_after_filters() { return $this->_afterfilters; }

	function  get_before_filter_exceptions() { return $this->_beforefilterexceptions; }
	function  get_after_filter_exceptions() { return $this->_afterfilterexceptions; }
}

?>