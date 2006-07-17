<?php

/**
	Base class for controller objects.
	All controllers should subclass this base class.
	
	@author Chandler McWilliams
	@version 2006-03-22
*/

class ControllerCore
{
	
	protected $viewname;
	protected $layout		= false;
	protected $templatebase;
	protected $template;
	protected $beforefilters;
	protected $afterfilters;
	protected $before_filter_exceptions = array();
	protected $after_filter_exceptions = array();
	protected $cache_page = false;
	protected static $cache_extension = 'cache';
	protected $data;
	protected $rendered = false;
	
	
// ===========================================================
// - CONSTRUCTOR
// ===========================================================
	function ControllerCore() {		
		# set the template base
#		$this->set_template_base(str_replace('controller', '', strtolower(get_class($this))));
		$this->set_template_base(to_url_name(str_replace('Controller', '', get_class($this))));
		$this->data = array();

		# call if there is an init() method in the App class
		#$m = get_class_methods('AppController');
		#if (in_array('init', $m)) AppController::init();
		AppController::init();
		
		# if there is an init method, call it		
		#if (method_exists($this, 'init')) $this->init();
		$this->init();
	}
	
	# just overridden
	function init() {}
	

// ===========================================================
// - EXECUTE A URL ACTION
// ===========================================================
	function execute($the_method) {
		# make sure the method exists
		if (!method_exists($this, $the_method)) {
			# if it doesn't, look for default
			if (false && method_exists($this, '_index')) {
				$the_method = '_index';
			} else {
				# if that's no good
				# throw an error
				throw new InvalidAction(ucfirst(get_class($this))." does not have an action named ".substr($the_method,1).".", 0);
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
				if ($method == 'all' || $method == $test_method) {


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
		#if ($ok) call_user_func(array($this, $the_method));
		if ($ok !== false) {
			$this->$the_method();
		} else {
			die();
		}

		# perform the after filters
		$af = $this->get_after_filters();
		$afe = $this->get_after_filter_exceptions();
		if ($af) {
			foreach ($af as $method => $filters) {
				# if it's excepted, skip
				if (array_key_exists($test_method, $afe)) continue;

				# if it's a global filter or one for this method
				if ($method == 'all' || $method == $the_method) {
					# loop through all filters and call each
					foreach($filters as $filter) {
						call_user_func($filter);
					}
				}
			}
		}
		
		# set the template
		if (!$this->template) $this->set_template(substr($the_method,1));
		
		# render the view
		$this->render_view(false, true);
	}
	

// ===========================================================
// - RENDER
// ===========================================================
	function render_view($viewname=false, $final=false) {
		if ($this->rendered && $final) return;
		if ($viewname !== false) $this->set_template($viewname);

		if (!$final) return;
		$this->rendered = true;
		$view = $this->get_view_for_action($this->template);

		$view->set_all_props($this->data);

		# if we cache, do that
		if ($this->cache_page && empty($_GET) && empty($_POST)) {
			$output = $view->parse($this->get_layout());
			$this->save_cache($_SERVER['REQUEST_URI'], $output);
		}
		$view->render(to_url_name(str_replace('Controller', '', get_class($this))), $this->get_layout());
	}

	function render_action($action) {
		$this->render_view($action);
		$a = "_{$action}";
		$this->$a();
	}

	function render_text($text, $isxml=false) {
		$this->rendered = true;
		$view = new ViewCore('');
		# if we cache, do that
		if ($this->cache_page && empty($_GET) && empty($_POST)) {
			$this->save_cache($_SERVER['REQUEST_URI'], $text);
		}
		if ($isxml) {
			$view->render_xml($text);
		} else {
			$view->render_text($text);
		}
	}

	function render_xml($text) {
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
		$file = array_pop($dirs).'.'.ControllerCore::cache_extension;
		
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
			throw new CacheWrite("Failed to write cache file to: $mkdir/$file.");
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
					if ($pi['extension'] == ControllerCore::cache_extension) {
						unlink($base.$targetpath.'/'.$file);
					} else if ($file{0} != '.' && is_dir($base.$targetpath.'/'.$file)) {
						ControllerCore::clear_cache($targetpath.'/'.$file.'/*');
					}
				}
				closedir($dirhandle);
			}
		
			# also delete the cache file for the dir
			if (file_exists($base.$targetpath.'.'.ControllerCore::cache_extension)) {
				unlink($base.$targetpath.'.'.ControllerCore::cache_extension);
			}
		
		} else {
			unlink($base.$path.'.'.ControllerCore::cache_extension);
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
		$template = $this->get_template_base().'/'.$this->template;
		return $this->get_view($template);
	}





// ===========================================================
// - ACCESSORS
// ===========================================================
	function __get($prop) {
		# check in data
		if (isset($this->data[$prop])) return $this->data[$prop];
				
		return false;
	}
	
	// set
	function __set($prop, $val) {
		if (is_null($val)) {
			unset($this->data[$prop]);
		} else {
			$this->data[$prop] = $val;
		}
	}




	# get/set the template base
	function get_template_base() { return $this->templatebase;}
	function set_template_base($v) { $this->templatebase = $v; }

	function set_template($template) {
		$this->template = $template;
	}

	function set_layout($x) {
		$this->layout = $x;
	}

	function get_layout() {
		if ($this->layout) return PROJECT_VIEWS.'/layouts/'.$this->layout;
		return PROJECT_VIEWS.'/layouts/'.$this->get_template_base();
	}


// ===========================================================
// - FILTER METHODS
// ===========================================================
	function filter_before($filter, $methods='all', $except=false) { $this->add_filter('before', $filter, $methods, $except); }
	function filter_after($filter, $methods='all', $except=false) { $this->add_filter('after', $filter, $methods, $except); }

	function filter_before_except($filter, $except) { $this->add_filter('before', $filter, 'all', $except); }
	function filter_after_except($filter, $except) { $this->add_filter('after', $filter, 'all', $except); }
	
	
	function add_filter($type, $filter, $methods='all', $except=false) {
		# grab the filter array to use
		if ($type == 'before') {
			$farray =& $this->get_before_filters();
		} else {
			$farray =& $this->get_after_filters();
		}			

		# make sure the arry is set
		if (!is_array($farray)) $farray = array();
		
		# if methods is an array, then add this filter
		# to each of those methods, otherwise add it to the
		# specified method
		# all applies to all methods and is the default
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

	function  &get_before_filters() { return $this->beforefilters; }
	function  &get_after_filters() { return $this->afterfilters; }

	function  get_before_filter_exceptions() { return $this->before_filter_exceptions; }
	function  get_after_filter_exceptions() { return $this->after_filter_exceptions; }
	
	
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
		header('Location:http://'.$_SERVER['HTTP_HOST'].'/404.html');
	}

	function local_request() {
		return ($_SERVER['REMOTE_ADDR'] == '127.0.0.1');
	}
}


// ===========================================================
// - EXCEPTIONS
// ===========================================================
class InvalidAction	extends SaintException {}
class CacheWrite		extends SaintException {}


?>