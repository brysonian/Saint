<?php
	(php_sapi_name() == 'cli')?define('SHELL', 1):define('SHELL', 0);

	# start session is not a shell
	// if (!SHELL) {
	// 	session_name('saint_session');
	// 	session_start();
	// }

	// ===========================================================
	// - GET THE SAINT ROOT
	// ===========================================================

	if(!defined('SAINT_ROOT')) define('SAINT_ROOT', realpath(dirname(__FILE__)));
	if(!defined('PROJECT_ROOT')) define('PROJECT_ROOT', realpath(dirname($_SERVER['SCRIPT_FILENAME']).'/../'));
	if(!defined('DOC_ROOT')) define('DOC_ROOT', $_SERVER['DOCUMENT_ROOT']);

	# Add some things to the include path
	# lib modules
	$inc = SAINT_ROOT.PATH_SEPARATOR.get_include_path().PATH_SEPARATOR.SAINT_ROOT.'/modules';
	$inc .= PATH_SEPARATOR.SAINT_ROOT.'/core';

	
	# user locations
	$inc .= PATH_SEPARATOR.PROJECT_ROOT.'/lib';
	$inc .= PATH_SEPARATOR.PROJECT_ROOT.'/app/models';
	$inc .= PATH_SEPARATOR.PROJECT_ROOT.'/app/controllers';	
	$inc .= PATH_SEPARATOR.PROJECT_ROOT.'/app/helpers';	
	$inc .= PATH_SEPARATOR.PROJECT_ROOT.'/app/plugins';	

/*	
	# add plugin dirs and include plugin inits
	$dir = new DirectoryIterator(PROJECT_ROOT.'/plugins');
	$out = array();
	$plugin_inits = array();
	foreach($dir as $file) {
		$fname = $file->getFilename();
		if ($fname{0} != '.') {
			$inc .= PATH_SEPARATOR.PROJECT_ROOT.'/plugins/'.$file->getFilename();
			if (file_exists(PROJECT_ROOT.'/plugins/'.$file->getFilename().'/init.php'))
				$plugin_inits[] = PROJECT_ROOT.'/plugins/'.$file->getFilename().'/init.php';
		}
	}
	
	$inc .= PATH_SEPARATOR.PROJECT_ROOT.'/plugins';	
*/
	set_include_path($inc);


	// ===========================================================
	// - INCLUDE SAINT LIB
	// ===========================================================
	# core
	require_once (SAINT_ROOT.'/core/DBRecord.php');
	require_once (SAINT_ROOT.'/core/AbstractView.php');
	require_once (SAINT_ROOT.'/core/AbstractController.php');
	require_once (SAINT_ROOT.'/core/ViewFactory.php');
	require_once (SAINT_ROOT.'/core/SaintException.php');

	# db classes
	require_once (SAINT_ROOT.'/core/MySQLiConnection.php');
	require_once (SAINT_ROOT.'/core/MySQLiResult.php');
		
	# error codes and messages
	require_once (SAINT_ROOT.'/locale/en/error_messages.php');




	// ===========================================================
	// - GET USER CONFIG VALUES.
	// ===========================================================
	require_once (PROJECT_ROOT."/config/environment.php");
	if (!defined('SAINT_SESSION_NAME')) define('SAINT_SESSION_NAME', 'saint_session');

	if (!SHELL) {
		session_name(SAINT_SESSION_NAME);
		session_start();
	}

	if(!defined('DISABLE_DEBUG') && SAINT_ENV == 'development') {
		define('DEBUG', 1);
		define('MYSQLI_DEBUG', 2);
	}


	// ===========================================================
	// - SET THE TIMEZONE
	// ===========================================================
	if (function_exists('date_default_timezone_set')) {
		if(!defined('TIME_ZONE')) {
			date_default_timezone_set(date_default_timezone_get());
		} else {
			date_default_timezone_set(TIME_ZONE);
		}
	}


	// ===========================================================
	// - GET USER USHER CONFIG.
	// ===========================================================
	require_once (PROJECT_ROOT."/config/urls.php");



// ===========================================================
// - SETUP THE DB CONNECTION
// ===========================================================
	# get the DB config params
	$database_config = parse_ini_file(PROJECT_ROOT."/config/database.ini", true);

	$db_name		= $database_config[SAINT_ENV]['database'];
	$user				= $database_config[SAINT_ENV]['username'];
	$pass				= $database_config[SAINT_ENV]['password'];
	$host				= $database_config[SAINT_ENV]['host'];
	$db_options = array_key_exists('options', $database_config[SAINT_ENV]) ? $database_config[SAINT_ENV]['options']	: array();
	$db_type		= array_key_exists('type',		$database_config[SAINT_ENV]) ? $database_config[SAINT_ENV]['type']		: 'mysqli';
	
#	require_once (PROJECT_ROOT."/config/database.php");
	
	if (!DBService::has_connection('DBRecord') && isset($db_name, $user, $pass, $host)) {
		# DB SERVICE
		DBService::add_connection(
			'DBRecord', $db_type, $db_name, $user, $pass, $host, $db_options);
	}

	# clear DB setup vars
	unset($database_config, $db_name, $user, $pass, $host, $db_options, $db_type);






// ===========================================================
// - ERRORS
// ===========================================================
	error_reporting(E_ALL);
	function saint_error_handler($errno, $errstr, $errfile, $errline) {
		$l = ob_get_level();
		while($l--) ob_end_clean();
		
		# manually attempt to close all db connections
		DBService::close();
		$e = new InvalidStatement($errstr, $errno, $errfile, $errline);
		if (SHELL) {
			$e->log();
		} else {
			Usher::handle_error($e);
		}
		exit;
	}
	set_error_handler('saint_error_handler');

	function saint_exception_handler($e) {
		$l = ob_get_level();
		while($l--) ob_end_clean();
		
		# manually attempt to close all db connections
		DBService::close();
		if (SHELL) {
			$e->log();
		} else {
			Usher::handle_error($e);
		}
		exit;
	}
	set_exception_handler('saint_exception_handler');




// ===========================================================
// - CREATE THE MODULE LOADING FUNCTION
// ===========================================================
	function use_module($module) {
		require_once "$module.php";
	}



// ===========================================================
// - AUTOLOAD
// ===========================================================
	function __autoload($class_name) {
		#$class_name = ucfirst($class_name);

		# if it's a controller, make sure it exists
		if (strpos($class_name, 'Controller') !== false) {
			$ok = file_exists(PROJECT_ROOT."/app/controllers/$class_name.php");
			if(!$ok) {
				# php is dumb so you can't throw from inside autoload, to fix this we
				# make a dummy class that throws the error in the constructor
				# note this won't work for static methods
				eval("class $class_name {function __construct() {throw new UnknownController('No controller with the name ".str_replace('Controller', '', $class_name)." could be found.');}}");
				return;
			}
		}
		
	#	$ext = pathinfo($class_name, PATHINFO_EXTENSION) == 'php'?'':'.php';
	#	require_once("$class_name{$ext}");
	require_once("$class_name.php");
	}

	# mark out debug section in log
	// TODO: Logger
	if (defined('DEBUG') && DEBUG) {
		error_log("");
		error_log("--==--");
		error_log("");
	}


	// ===========================================================
	// - LOAD PLUGIN INITS
	// ===========================================================
#	foreach($plugin_inits as $k => $v) include_once($v);




	// ===========================================================
	// - DEFINE LOCATION CONSTANTS. MAY BE OVERRIDDEN BY environment.php or plugin inits
	// ===========================================================
	# define the base url for views (templates)
	if (!defined('PROJECT_VIEWS'))
		define('PROJECT_VIEWS', 		PROJECT_ROOT.'/app/views');

	# define the base url for the html pages if this is web request
	if (array_key_exists('HTTP_HOST', $_SERVER)) {
		if (!defined('WEBBASE'))
			define('WEBBASE', 		'http://'.$_SERVER['HTTP_HOST']);

		# define the translated URI for this request
		define('PROJECT_URI', str_replace(WEBBASE, '', 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']));
	}

	if (!defined('STYLESHEET_BASE')) 	define('STYLESHEET_BASE', '_css');
	if (!defined('JAVASCRIPT_BASE')) 	define('JAVASCRIPT_BASE', '_js');
	if (!defined('MEDIA_BASE')) 			define('MEDIA_BASE', '_media');
	if (!defined('CONTENT_BASE')) 		define('CONTENT_BASE', '_content');
	if (!defined('PUBLIC_BASE')) 			define('PUBLIC_BASE', 'public');

	# always need the AppController
	require_once (PROJECT_ROOT.'/app/controllers/AppController.php');


?>