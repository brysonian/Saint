<?php



class Usher
{
	var $maps = array();
	var $base = false;
	
	static public $params;
	
	# php 4 singleton
	function  get_instance() {
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
		return $this->base?$this->base:'/';
	}
	
	
	/**
	* Find the best matching map for the url
	*/
	function match_url($url) {
		# remove the base
		$url = substr($url, strlen($this->get_base()));
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
		$u = Usher::get_instance();

		$params = $u->match_url($url);

		# if no match was found, show the error
		if (!$params) throw(new SaintException("No mapping was found for &quot;$url&quot;.", NOMAP));

		# add params to request
		self::$params = $params;

		# get the controller name
		$cname = ucfirst($params['controller']).'Controller';

		# include the right classes
		# this can be killed in PHP5
		#__autoload($cname);

		# make an instance of the controller class
		$controller = &new $cname;

		# include the right class for this controller
		#__autoload(ucfirst($params['controller']));

	
		# set the method name
		$action = '_'.$params['action'];

		# tell the controller to execute the action
		$controller->execute($action);
	}
	
	/**
	* Return a value for a param
	*/
	static function get_param($p=false) {
		if ($p === false) return self::$params;
		return self::$params[$p];
	}
	
	/**
	Just return how it would be parsed
	*/
	function get_params_for_url($url) {
		$u = Usher::get_instance();

		$params = $u->match_url($url);

		return $params;
	}
	
	// add a map for the given pattern
	function map($pattern, $defaults=false, $requirements=false) {
		# parse pattern into regex
		$this->maps[] = new UsherMap($pattern, $defaults, $requirements);
	}
	
	// add a named map
	function map_named($name, $pattern, $defaults=false, $requirements=false) {
		# parse pattern into regex
		$this->maps[$name] = new UsherMap($pattern, $defaults, $requirements);
	}
	
}



// ===========================================================
// - SOME THINGS IN USHER NEED TO BE EASIER TO GET TO
// ===========================================================
function params($name=false) {
	return Usher::get_param($name);
}


// URL STUFF
function get_root() {
	$u = Usher::get_instance();
	return $u->get_base();
}

function link_to($name, $args) {
	if (!is_array($args)) {
		$args = func_get_args();
		array_shift($args);
	}

	return "<a href='".url_for($args)."'>$name</a>";
}

// construct a url using the maps
# call using either an array, or in this order:
# controller, action, uid, params
function url_for($args=false) {
	$u = Usher::get_instance();

	# build URL
	$url = $u->get_base();

	# if nothing is passed, return a url for the root
	if ($args === false || $args == '/') {
		return $url;

	} else if (!is_array($args)) {
		# if the args is not an array, grab them all
		$args = func_get_args();
	}
	
	# reinterpret using order
	if (array_key_exists(0, $args)) {
		$args['controller']	= $args[0];
		unset($args[0]);
	}
	if (array_key_exists(1, $args)) {
		$args['action']			= $args[1];
		unset($args[1]);
	}
	if (array_key_exists(2, $args)) {
		$args['uid']				= $args[2];
		unset($args[2]);
	}
	if (array_key_exists(3, $args)) {
		$args['params']			= $args[3];
		unset($args[3]);
	}
	
	
	# if controller is empty, use the current controller
	if (!array_key_exists('controller', $args)) {
		$args['controller'] = params('controller');
	}

	# for each param, try to find the map that fits the best
	$score = 999;
	foreach($u->maps as $k => $v) {
		$theargs = $args;
		# get the map
		$temp = $v->usermap;
		
		# replace tokens with values
		foreach($theargs as $k2 => $v2) {
			if (strpos($temp, ":$k2") !== false) {
				$temp = str_replace(":$k2", $v2, $temp);	
				unset($theargs[$k2]);
			}
		}
		
		# replace defaults
		foreach($v->defaults as $k2 => $v2) {
			$temp = str_replace(":$k2", '', $temp);
		}
		
		# clear out //
		$temp = str_replace("//", '', $temp);

		
		# score based on number of : left
		$s = substr_count(':', $temp);
		if ($s < $score) {
			$score = $s;
			$url = $temp;
		}
	}
		
	# if there are any left over make them into a query string
	if (!empty($theargs)) {
		$urlparams = array();
		foreach($theargs as $k => $v) {
			$urlparams[] = "$k=$v";
		}
		# trim trailing /
		if ($url{strlen($url)-1} == '/') 
			$url = substr($url, 0, strlen($url)-1);
		$url .= "?".join("&amp;", $urlparams);
	}

	return $url;
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
			array($this, 'map_to_regex'),
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
					} 
					#else {
					#	$c = @__autoload($out[$name].'Controller');
					#	if (!$c) return false;
					#}
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