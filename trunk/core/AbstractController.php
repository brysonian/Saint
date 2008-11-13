<?php

/**
	Base class for controller objects.
	All controllers should subclass this base class.
	
	@author Chandler McWilliams
	@version 2006-03-22
*/

abstract class AbstractController
{
	
	protected $_viewname;
	protected $_layout		= false;
	protected $_templatebase;
	protected $_template;
	protected $_beforefilters;
	protected $_afterfilters;
	protected $_cache_page = false;
	protected static $_cache_extension = 'cache';
	protected $_data;
	protected $_rendered = false;
	
	
// ===========================================================
// - CONSTRUCTOR
// ===========================================================
	function __construct() {		
		# set the template base
		$this->set_template_base(url_name(str_replace('Controller', '', get_class($this))));
		$this->_data = array();
		
		# gather all ivars not preceded with _
		$cname = get_class($this);
		foreach($this as $k => $v) {
			if ($k{0} != '_') {
				$prop = new ReflectionProperty($cname, $k);
				if ($prop->isPublic()) {
					$this->__set(substr($k, 0), $v);
					unset($this->$k);
				}
			}
		}

	}
	

// ===========================================================
// - EXECUTE A URL ACTION
// ===========================================================
	function execute($the_method) {
		# make sure the method exists
		if (!method_exists($this, $the_method)) {
			# if it doesn't, look for default
			if (false && method_exists($this, 'index')) {
				$the_method = 'index';
			} else {
				# if that's no good
				# throw an error
				throw new InvalidAction(ucfirst(get_class($this))." does not have an action named $the_method.", 0);
			}
		}
		
		# perform the before filters
		$bf = $this->get_before_filters();
		$ok = true;
		$test_method = $the_method;
		if ($bf) {
			foreach ($bf as $method => $filters) {
				# if it's a global filter or one for this method
				if ($method == 'all' || $method == $test_method) {

					# loop through all filters and call each
					foreach($filters as $filter) {
						if (in_array($test_method, $filter['exceptions'])) continue;
						$fok = call_user_func($filter['callback']);
					
						# only fail if it's really false not just undef
						if ($fok === false) $ok = false;
					}
				}
			}
		}

		# call the method if none of the filters returned false
		#if ($ok) call_user_func(array($this, $the_method));
		if ($ok !== false) {
			$this->$the_method();
		} else {
			die();
		}

		# perform the after filters
		$af = $this->get_after_filters();
		if ($af) {
			foreach ($af as $method => $filters) {
				# if it's a global filter or one for this method
				if ($method == 'all' || $method == $the_method) {
					if (in_array($test_method, $filter['exceptions'])) continue;
					foreach($filters as $filter) {
						call_user_func($filter['callback']);
					}
				}
			}
		}
		
		# set the template
		if (!$this->_template) $this->set_template($the_method);
		
		# render the view
		$this->render_view(false, true);
	}
	

// ===========================================================
// - RENDER
// ===========================================================
	function render_view($_viewname=false, $final=false) {
		if ($this->_rendered && $final) return;
		if ($_viewname !== false) $this->set_template($_viewname);

		if (!$final) return;
		$this->_rendered = true;
		$view = $this->get_view_for_action($this->_template);

		$view->set_all_props($this->_data);

		# if we cache, do that
		if ($this->_cache_page && empty($_GET) && empty($_POST)) {
			$output = $view->parse($this->get_layout());
			$this->save_cache($_SERVER['PHP_SELF'], $output);
		}
		$view->render(url_name(str_replace('Controller', '', get_class($this))), $this->get_layout());
	}

	function render_action($action) {
		$this->render_view($action);
		$a = "_{$action}";
		#$this->$a();
		$this->$action();
	}

	function render_text($text, $isxml=false) {
		$this->_rendered = true;
		$view = new PTXTView('');
		# if we cache, do that
		if ($this->_cache_page && empty($_GET) && empty($_POST)) {
			$this->save_cache($_SERVER['PHP_SELF'], $text);
		}
		if ($isxml) {
			if ($text == false) $view->set_all_props($this->_data);
			$view->render_xml($text);
		} else {
			$view->render_text($text);
		}
	}

	function render_xml($text=false) {
		$this->render_text($text, true);
	}



	// reference to redirect_to
	function redirect_to($args=false) {
		redirect_to($args);
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
		$file = array_pop($dirs).'.'.AbstractController::_cache_extension;
		
		# new path
		$mkdir = DOC_ROOT;
		
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
			throw new CacheWrite("Failed to write cache file to: $mkdir/$file.");
		}
		return true;
	}


	function clear_cache($path) {
		$base = DOC_ROOT.'/';
	
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
					if ($pi['extension'] == AbstractController::_cache_extension) {
						unlink($base.$targetpath.'/'.$file);
					} else if ($file{0} != '.' && is_dir($base.$targetpath.'/'.$file)) {
						AbstractController::clear_cache($targetpath.'/'.$file.'/*');
					}
				}
				closedir($dirhandle);
			}
		
			# also delete the cache file for the dir
			if (file_exists($base.$targetpath.'.'.AbstractController::_cache_extension)) {
				unlink($base.$targetpath.'.'.AbstractController::_cache_extension);
			}
		
		} else {
			unlink($base.$path.'.'.AbstractController::_cache_extension);
		}
		
	}



// ===========================================================
// - RETURN THE CORRECT VIEW FOR DIFFERENT SITUATIONS
// ===========================================================		
	// get a view using the template
	// this is the only method that returns an actual view object
	function  get_view($template) {
		return ViewFactory::make_view(strtolower($template));
	}

	// get a view object using the specified action
	function  get_view_for_action($action) {
		$template = $this->get_template_base().'/'.url_name($action);
		return $this->get_view($template);
	}





// ===========================================================
// - ACCESSORS
// ===========================================================
	function &__get($prop) {
		# check in data
		if (isset($this->_data[$prop])) return $this->_data[$prop];
		return false;
	}
	
	// set
	function __set($prop, $val) {
		if (is_null($val)) {
			unset($this->_data[$prop]);
		} else {
			$this->_data[$prop] = $val;
		}
	}




	# get/set the template base
	function get_template_base() { return $this->_templatebase;}
	function set_template_base($v) { $this->_templatebase = $v; }

	function set_view($template) {
		$this->set_template($template);
	}

	function set_template($template) {
		$this->_template = $template;
	}

	function set_layout($x) {
		$this->_layout = $x;
	}

	function get_layout() {
		if ($this->_layout) return PROJECT_VIEWS.'/_layouts/'.$this->_layout;
		return PROJECT_VIEWS.'/_layouts/'.$this->get_template_base();
	}


// ===========================================================
// - FILTER METHODS
// ===========================================================
	function filter_before($filter, $methods='all', $except=array()) { $this->add_filter('before', $filter, $methods, $except); }
	function filter_after($filter, $methods='all', $except=array()) { $this->add_filter('after', $filter, $methods, $except); }

	function filter_before_except($filter, $except) { $this->add_filter('before', $filter, 'all', $except); }
	function filter_after_except($filter, $except) { $this->add_filter('after', $filter, 'all', $except); }
	
	
	function add_filter($type, $filter, $methods='all', $except=array()) {
		# grab the filter array to use
		if ($type == 'before') {
			$farray =& $this->get_before_filters();
		} else {
			$farray =& $this->get_after_filters();
		}			

		# make sure the filter array exists
		if (!is_array($farray))		$farray = array();
		
		# force methods and exceptions into an array
		if (!is_array($methods))	$methods = array($methods);
		if (!is_array($except))		$except = array($except);

		
		# then add this filter to each of those methods, otherwise add it to the
		# specified method
		# all applies to all methods and is the default
		foreach ($methods as $method) {
			# check that there isn't already a filter
			# if there is add this one on
			if (!array_key_exists($method, $farray)) $farray[$method] = array();
			# add filter
			$farray[$method][] = array('callback' => $filter, 'exceptions' => $except);
		}
	}

	function  &get_before_filters() { return $this->_beforefilters; }
	function  &get_after_filters() { return $this->_afterfilters; }

	function remove_before_filter($filter, $methods='all') { $this->remove_filter('before', $filter, $methods); }
	function remove_after_filter($filter, $methods='all') { $this->remove_filter('after', $filter, $methods); }

	function remove_filter($type, $filter, $methods='all') {
		if ($type == 'before') {
			$farray =& $this->get_before_filters();
		} else {
			$farray =& $this->get_after_filters();
		}			

		if (!is_array($farray)) return;

		if (is_array($methods)) {
			foreach ($methods as $method) {
				$k = array_search($filter, $farray[$method]);
				if ($k !== false) unset($farray[$method][$k]);
			}
		} else {
			$k = array_search($filter, $farray[$methods]);
			if ($k !== false) unset($farray[$methods][$k]);
		}
	}
	
	// ===========================================================
	// - ERROR HANDLING
	// ===========================================================
	function rescue($e) {
		if ($this->local_request()) {
			echo $e->log();
		} else {
			$this->rescue_in_public($e);
		}
	}

	function rescue_in_public($e) {
		header('Location:http://'.$_SERVER['HTTP_HOST'].'/500.html', true, 500);
	}

	function local_request() {
		return ($_SERVER['REMOTE_ADDR'] == '127.0.0.1' || $_SERVER['REMOTE_ADDR'] == '::1');
	}
}


// ===========================================================
// - EXCEPTIONS
// ===========================================================
class InvalidAction	extends SaintException {}
class CacheWrite		extends SaintException {}


?>