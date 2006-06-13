<?php



class Usher
{
	public $maps = array();
	public $base = false;

	static private	$instance = false;
	static public $params;
	static public $controller = false;
	
	# singleton
	static function get_instance() {
		if(!self::$instance) {
			$c = __CLASS__;
			self::$instance = new $c;
		}
		return self::$instance;
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
		get the controller used in the current request
		@return (ControllerCore) 
	**/
	static public function controller() {
		return self::$controller;
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
	static function handle_url($url) {
		$u = Usher::get_instance();

		$params = $u->match_url($url);

		# if no match was found, show the error
		if (!$params) throw new NoValidMapping("No mapping was found for &quot;$url&quot;.");

		# add request to params and make sure magic quotes are dealt with
		unset($_REQUEST['MAX_FILE_SIZE']);
		foreach($_POST as $k => $v) {
			if (!array_key_exists($k, $params)) {
				$gpc = (get_magic_quotes_gpc() == 1);
				if (is_array($v)) {
					$params[$k] = array();
					foreach($v as $k2 => $v2) {
						$params[$k][$k2] = ($gpc)?stripslashes($v2):$v2;
					}
				} else {
					$params[$k] = ($gpc)?stripslashes($v):$v;
				}
			}
		}

		foreach($_GET as $k => $v) {
			if (!array_key_exists($k, $params)) {
				$gpc = (get_magic_quotes_gpc() == 1);
				if (is_array($v)) {
					$params[$k] = array();
					foreach($v as $k2 => $v2) {
						$params[$k][$k2] = ($gpc)?stripslashes($v2):$v2;
					}
				} else {
					$params[$k] = ($gpc)?stripslashes($v):$v;
				}
			}
		}

		# add files to params and make sure magic quotes are dealt with		
		foreach($_FILES as $k => $v) {
			if (!array_key_exists($k, $params)) $params[$k] = array();
			$params[$k] = array_merge($params[$k], UploadedFile::create($v));
		}
	
		# save the params
		self::$params = $params;

		# get the controller name
		$cname = preg_replace('/(?:^|_)([a-zA-Z])/e', "strtoupper('\\1')", $params['controller']);
		$cname = ucfirst($cname.'Controller');
		
		# make sure the name is a valid class name
		if ((preg_match('|[^a-zA-Z_]|', $cname) > 0) || (preg_match('|^[^a-zA-Z_]|', $cname) > 0)) {
			 throw new UnknownController("$cname is not a valid controller.");
		}
		
		# make an instance of the controller class
		self::$controller = new $cname;

		# set the method name
		$action = '_'.$params['action'];

		# tell the controller to execute the action
		self::$controller->execute($action);
	}
	
	/**
	* Handle errors
	*/
	static function handle_error($e) {
		if (!self::$controller) self::$controller = new AppController;			
		if (!($e instanceof SaintException))
			$e = new SaintException($e->getMessage(), $e->getCode());
		
		self::$controller->rescue($e);
	}
	
	/**
	* Return a value for a param
	*/
	static function get_param($p=false) {
		if ($p === false) return self::$params;
		if (is_array(self::$params) && array_key_exists($p, self::$params)) {
			return self::$params[$p];
		} else {
			return false;
		}
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

function param_as_uid($name=false) {
	$val = Usher::get_param($name);
	if (!DBRecord::is_valid_uid($val)) return false;
	return $val;
}


// URL STUFF
function get_root() {
	$u = Usher::get_instance();
	return $u->get_base();
}

function link_to($name, $args=false, $confirm=false, $options=array()) {
	if (!is_array($options)) $options = array();
	
	# if there is only one arg, and it's an object, make a link to it
	if (is_object($name) && func_num_args() == 1) {
		$url = url_for($name);
	} else {
		if (!is_object($args) && !is_array($args)) {
			$args = func_get_args();
			array_shift($args);		
		}
		$url = url_for($args);
	}
		

	ob_start();
	echo "<a href='$url'";
	if ($confirm) {
		if (array_key_exists("onclick", $options)) {
			echo ' onclick="if (confirm(\'Are You Sure?\')) {'.$options['onclick'].'} else {return false;}"';
			unset($options['onclick']);			
		} else {
			echo ' onclick="return confirm(\'Are You Sure?\');"';
		}
	}
	foreach($options as $k => $v) {
		echo " $k=\"$v\"";
	}
	echo ">";
	echo $name;
	echo "</a>";
	return ob_get_clean();
}

// construct a url using the maps
# call using either an array, or in this order:
# controller, action, uid, params
function url_for($args=false) {
	if (is_object($args)) {
		if (method_exists($args, 'to_url')) {
			return $args->to_url();
		}
	}
	
	$u = Usher::get_instance();

	# build URL
	$url = $u->get_base();

	# if nothing is passed, return a url for the default action of the current controller
	if ($args === false) {
		$args = array('controller' => params('controller'));

	} else if (!is_array($args)) {
		# if the args is not an array, grab them all
		$args = func_get_args();
		# if only one arg is passed, treat it as the action on the current controller
		if (count($args) == 1) array_unshift($args, params('controller'));
	}
	
	# reinterpret using order
	if (array_key_exists(0, $args)) {
		if ($args[0] == false) {
			$args['controller'] = params('controller');
		} else {
			$args['controller']	= $args[0];
		}
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
	if (!array_key_exists('controller', $args)) $args['controller'] = params('controller');

	# if the controller is root and it's the only arg, then go there
	if ($args['controller'] == '/' && count($args) == 1) return $url;

	# for each param, try to find the map that fits the best
	$score = 999;
	$best_args = array();
	foreach($u->maps as $k => $v) {
		$theargs = $args;
		# get the map
		$temp = $v->usermap;
		$s = 0;
		
		# replace tokens with values
		foreach($theargs as $k2 => $v2) {
			if (strpos($temp, ":$k2") !== false) {
				$temp = str_replace(":$k2", $v2, $temp);	
				unset($theargs[$k2]);
			}
		}
		
		# replace not-null defaults
		foreach($v->defaults as $k2 => $v2) {
			if (is_null($v2)) continue;
			$temp = str_replace(":$k2", '', $temp);
			# get a point for each default that matches a value in the args
			if (array_key_exists($k2, $args) && ($v2 == $args[$k2])) $s--;
		}
		
		# score based on number of : left and the number of args left
		$s += (substr_count($temp, ':') + count($theargs))*2;

		# * items cost a lot
		$s += (substr_count($temp, '*') * 3);

		# dbugin
		#echo "\n Score: $s\n map:".$v->usermap."\n url: $temp\nArgs:";
		#var_export($args);

		if ($s < $score) {
			$score = $s;
			$url = $temp;
			$best_args = $theargs;
		}
	}
	
	# replace defaults including NULL
	#foreach($v->defaults as $k => $v) {
	#	$url = str_replace(":$k", '', $url);
	#}
	
	#replace all :foo placeholders
	$url = preg_replace('|:[a-zA-Z_0-9]*|', '', $url);


	# clear out //
	$url = str_replace("//", '', $url);
	

	# if there are any left over make them into a query string
	if (!empty($best_args)) {
		$urlparams = array();
		foreach($best_args as $k => $v) {
			if ($k != 'controller' && $k != 'action') {
				$urlparams[] = "$k=$v";
			}
		}
		# trim trailing /
		if ($url{strlen($url)-1} == '/') 
			$url = substr($url, 0, strlen($url)-1);
		if (!empty($urlparams)) $url .= "?".join("&amp;", $urlparams);
	}
#		echo "Winner:$url\nArgs:";
#		var_export($args);

	return $url;
}

// ===========================================================
// - REDIRECT TO A NEW LOCATION
// ===========================================================
function redirect_to($args=false) {
	if (!is_array($args) && $args !== false) {
		$args = func_get_args();
	}
	$url = url_for($args);
	header("Location: $url");	
}




















/**
	Maps a url to a resource

**/

class UsherMap
{
	public $map				= array();
	public $usermap		= false;
	public $regex			= false;
	public $hasAllCapture	= false;
	public $defaults		= array();
	public $requirements	= array();
	
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
		$this->regex = '|^'.preg_replace_callback(
			'/\/([:|\*])?([a-zA-Z0-9_]*)/',
			array($this, 'map_to_regex'),
			$map
		).'/?|';

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
			if (empty($v)) continue;
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


// ===========================================================
// - EXCEPTIONS
// ===========================================================
class NoValidMapping extends SaintException {}
class UnknownController extends SaintException {}


?>