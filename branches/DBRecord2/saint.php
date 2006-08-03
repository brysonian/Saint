<?php

	// ===========================================================
	// - GET THE SAINT ROOT
	// ===========================================================
	(php_sapi_name() == 'cli')?define('SHELL', 1):define('SHELL', 0);
	
	if(!defined('SAINT_ROOT')) define('SAINT_ROOT', realpath(dirname(__FILE__)));
	if(!defined('PROJECT_ROOT')) define('PROJECT_ROOT', realpath(dirname($_SERVER['SCRIPT_FILENAME']).'/../'));
	if(!defined('DOC_ROOT')) define('DOC_ROOT', $_SERVER['DOCUMENT_ROOT']);
	if(!defined('PUBLIC_BASE')) {
		$pi = pathinfo($_SERVER['DOCUMENT_ROOT']);
		define('PUBLIC_BASE', $pi['basename']);
	}
	

	# Add some things to the include path
	# lib modules
	$inc = get_include_path().PATH_SEPARATOR.SAINT_ROOT.'/modules';
	$inc .= PATH_SEPARATOR.SAINT_ROOT.'/core';

	
	# user locations
	$inc .= PATH_SEPARATOR.PROJECT_ROOT.'/app/models';
	$inc .= PATH_SEPARATOR.PROJECT_ROOT.'/app/controllers';	
	$inc .= PATH_SEPARATOR.PROJECT_ROOT.'/app/helpers';	
	
	$dir = new DirectoryIterator(PROJECT_ROOT.'/plugins');
	$out = array();
	foreach($dir as $file) {
		$fname = $file->getFilename();
		if ($fname{0} != '.') $inc .= PATH_SEPARATOR.PROJECT_ROOT.'/plugins/'.$file->getFilename();
	}
	
	$inc .= PATH_SEPARATOR.PROJECT_ROOT.'/plugins';	
	set_include_path($inc);


	// ===========================================================
	// - INCLUDE SAINT LIB
	// ===========================================================
	# core
	require_once (SAINT_ROOT.'/core/DBRecord.php');
	require_once (SAINT_ROOT.'/core/Usher.php');
	require_once (SAINT_ROOT.'/core/ViewCore.php');
	require_once (SAINT_ROOT.'/core/ControllerCore.php');
	require_once (SAINT_ROOT.'/core/ViewFactory.php');
	require_once (SAINT_ROOT.'/core/SaintException.php');

	# db classes
	require_once (SAINT_ROOT.'/core/MySQLiConnection.php');
	require_once (SAINT_ROOT.'/core/MySQLiResult.php');
		
	# error codes and messages //TODO: Localize
	require_once (SAINT_ROOT.'/error_messages.php');

	# always need the AppController
	require_once (PROJECT_ROOT.'/app/controllers/AppController.php');



	// ===========================================================
	// - GET USER CONFIG VALUES.
	// ===========================================================
	require_once (PROJECT_ROOT."/config/environment.php");


	// ===========================================================
	// - GET USER USHER CONFIG.
	// ===========================================================
#	__autoload('Usher');
	require_once (PROJECT_ROOT."/config/urls.php");


	// ===========================================================
	// - DEFINE LOCATION CONSTANTS. MAY BE OVERRIDDEN BY environment.php
	// ===========================================================
	# define the base url for views (templates)
	if (!defined('PROJECT_VIEWS'))
		define('PROJECT_VIEWS', 		PROJECT_ROOT.'/app/views');

	# define the base url for the html pages if this is web request
	if (array_key_exists('HTTP_HOST', $_SERVER)) {
		if (!defined('WEBBASE'))
			define('WEBBASE', 		'http://'.$_SERVER['HTTP_HOST']);

		# define the translated URI for this request
		define('PROJECT_URI', str_replace(WEBBASE, '', 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']));
	}

	if (!defined('STYLESHEET_BASE')) 	define('STYLESHEET_BASE', '_css');
	if (!defined('JAVASCRIPT_BASE')) 	define('JAVASCRIPT_BASE', '_js');
	if (!defined('MEDIA_BASE')) 			define('MEDIA_BASE', '_media');
	if (!defined('CONTENT_BASE')) 			define('CONTENT_BASE', '_content');


// ===========================================================
// - SETUP THE DB CONNECTION
// ===========================================================
	# get the DB config params
	require_once (PROJECT_ROOT."/config/database.php");
	
	if (!DBService::has_connection('DBRecord') && isset($db_name, $user, $pass, $host)) {
		# DB SERVICE
		DBService::add_connection(
			'DBRecord', 'mysqli', $db_name, $user, $pass, $host, isset($db_options)?$db_options:array());
	}

	# clear DB setup vars
	unset($db_name, $user, $pass, $host, $db_options);






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
// - SET THE TIMEZONE
// ===========================================================
if (function_exists('date_default_timezone_set')) {
	if(!defined('TIME_ZONE')) {
		date_default_timezone_set('America/New_York');
	} else {
		date_default_timezone_set(TIME_ZONE);
	}
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
		require_once("$class_name.php");
	}

	# mark out debug section in log
	// TODO: Logger
	if (defined('DEBUG') && DEBUG) {
		error_log("");
		error_log("--==--");
		error_log("");
	}

?>