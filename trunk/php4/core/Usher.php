<?php


//	TODO: link generation
	




class Usher
{
	var $maps = array();
	var $base = false;
	
	# php 4 singleton
	function &get_instance() {
		static $_instance;		
		if (!isset($_instance)) {
			$_instance = new Usher;
		}
		return $_instance;
	}
	
	/**
	* Set the base for urls
	*/
	function set_base($val) {
		$this->base = $val;
	}
	
	function get_base() {
		return $this->base;
	}
	
	function get_root() {
		$u =& Usher::get_instance();
		return $u->get_base()?$u->get_base():'';
	}

	
	
	/**
	* Find the best matching map for the url
	*/
	function match_url($url) {
		# remove the base
		if ($this->get_base() !== false) {
			$url = substr($url, strlen($this->get_base()));
		}		
		if ($url{0} != '/') $url = "/$url";
				
		foreach($this->maps as $k => $v) {
			$params = $v->match($url);
			if ($params) return $params;
		}
		return false;
	}
	
	/**
	* find the best map include the right classes and
	* start hand off to the controller
	*/
	function handle_url($url) {
		$u =& Usher::get_instance();

		$params = $u->match_url($url);

		# if no match was found, show the error
		if (!$params) throw(new Exception("No mapping was found for &quot;$url&quot;.", NOMAP));

		# add params to request
		$_REQUEST = array_merge($_REQUEST, $params);

		# get the controller name
		$cname = ucfirst($params['controller']).'Controller';

		# include the right classes
		# this can be killed in PHP5
		__autoload($cname);

		# make an instance of the controller class
		$controller = &new $cname;

		# include the right class for this controller
		__autoload(ucfirst($params['controller']));

	
		# set the method name
		$action = '_'.$params['action'];

		# tell the controller to execute the action
		$controller->execute($action);

		
	}
	
	/**
	Just return how it would be parsed
	*/
	function get_params_for_url($url) {
		$u =& Usher::get_instance();

		$params = $u->match_url($url);

		return $params;
	}
	
	// add a map for the given pattern
	function map($pattern, $defaults=false, $requirements=false) {
		# parse pattern into regex
		$this->maps[] =& new UsherMap($pattern, $defaults, $requirements);
	}
	
	// add a named map
	function map_named($name, $pattern, $defaults=false, $requirements=false) {
		# parse pattern into regex
		$this->maps[$name] =& new UsherMap($pattern, $defaults, $requirements);
	}
	
	
	
	// construct a url using the maps
	function url_for($controller=false, $action=false, $params=false) {
		$u =& Usher::get_instance();

		# build URL
		$url = $u->get_base()?$u->get_base().'/':'';
		
		if ($action) $url .= "/$action";
		
		# if there is an ID, add it
		if ($id) $url .= "?item=$id";
		
		# if there are params, add them
		if (is_array($params)) {
			foreach ($params as $k=>$v) {
				$url .= "&$k=$v";
			}
		}
		return realpath($url)?realpath($url):$url;
	}
	

}



/**
	Maps a url to a resource

**/

class UsherMap
{
	var $map				= array();
	var $usermap		= false;
	var $regex			= false;
	var $hasAllCapture	= false;
	var $defaults		= array();
	var $requirements	= array();
	
 function UsherMap($map, $defaults=false, $requirements=false) {
		$this->usermap			= $map;
		if (is_array($defaults)) $this->defaults = $defaults;
		if (is_array($requirements)) $this->requirements = $requirements;

		# if no action is set in defaults, set it to index
		if (!array_key_exists('action', $this->defaults)) $this->defaults['action'] = 'index';		

		# grab the default action
		$def = $this->defaults['action'];
		
		# clear it to make the regex 
		$this->defaults['action'] = NULL;
		
		# create the regex
		$this->regex = '|'.preg_replace_callback(
			'/\/([:|\*])?([a-zA-Z0-9_]*)/',
			array(&$this, 'map_to_regex'),
			$map
		).'/?$|';

		# add the default action back
		$this->defaults['action'] = $def;
	}
	
	// add the name of the param to the map
	// return the default pattern element
 function map_to_regex($matches) {
		if ($this->hasAllCapture) return '';

		$this->map[] = $matches[2];
		if ($matches[1] == '*') {
			$this->hasAllCapture = true;
			return '/?(.*)';
		} else if ($matches[1] != ':') {
			return '/?('.$matches[2].')';
		}
		
		if (array_key_exists($matches[2],$this->defaults) && is_null($this->defaults[$matches[2]])) {
			return '/?([^\/]*)';
		} else {
			return '/([^\/]+)';
		}
	}
	
	// see if this map matches a url
 function match($url) {
		# grab out the query string if there is one
		if (strpos($url, '?') > -1) {
			$query = explode('?', $url);
			$url = $query[0];
			$query = $query[1];
			
			$qparams = array();
			parse_str($query, $qparams);
			foreach($qparams as $k => $v) {
				$out[$k] = $v;
			}
		}

		# make sure they match
		$urlparts = array();

		if (!preg_match($this->regex, $url, $urlparts)) return false;

		# add defaults
		$out = $this->defaults;

		# map it
		$parts = explode('/', $this->usermap);
		array_shift($parts);
		array_shift($urlparts);

		foreach($parts as $k => $v) {			
			# if it's a placeholder
			if ($v{0} == ':' || $v{0} == '*') {
				$name = substr($v,1);
				
				# get the value
				if (array_key_exists($k, $urlparts) && ($urlparts[$k] != '')) $out[$name] = $urlparts[$k];

				# handle controller a little different
				if ($name == 'controller') {

					# can't be NULL
					if (is_null($out[$name])) return false;

					# if the requirement hasn't been overridden, make sure the 
					# class is available
					if (array_key_exists($name, $this->requirements)) {
						if (function_exists($this->requirements[$name])) {
							if (!call_user_func($this->requirements[$name], $out[$name])) return false;
						} else {
							if (!preg_match($this->requirements[$name], $out[$name])) return false;
						}
					} else {
						$c = @__autoload($out[$name].'Controller');
						if (!$c) return false;
					}
				} else {
					# if there is a requirement for this, 
					# and it isn't NULL
					if (array_key_exists($name, $this->requirements) && !is_null($out[$name])) {
						# if the req is a function, call it and pass the value,
						# otherwise assume it's a regex
						if (function_exists($this->requirements[$name])) {
							if (!call_user_func($this->requirements[$name], $out[$name])) return false;
						} else {
							if (!preg_match($this->requirements[$name], $out[$name])) return false;
						}
					}
				}
				
				# clear nulls
				if (is_null($out[$name])) unset($out[$name]);
			}
		}
		
		# if no action is set, set it to the default
		if (!array_key_exists('action', $out)) $out['action'] = $this->defaults['action'];
		return $out;
	}
}



?>