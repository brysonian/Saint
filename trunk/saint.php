<?php

	// ===========================================================
	// - GET THE SAINT ROOT
	// ===========================================================
	if(!defined('SAINT_ROOT')) define('SAINT_ROOT', realpath(dirname(__FILE__)));
	define('PROJECT_ROOT', realpath(dirname($_SERVER['PATH_TRANSLATED']).'/../'));


	# Add some things to the include path
	# lib modules
	$inc = get_include_path().PATH_SEPARATOR.SAINT_ROOT.'/modules';
	$inc .= PATH_SEPARATOR.SAINT_ROOT.'/core';

	
	# user locations
	$inc .= PATH_SEPARATOR.PROJECT_ROOT.'/app/models';
	$inc .= PATH_SEPARATOR.PROJECT_ROOT.'/app/controllers';	
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
	require_once (SAINT_ROOT.'/core/DBException.php');
	require_once (SAINT_ROOT.'/core/DBDuplicateException.php');
	require_once (SAINT_ROOT.'/core/DBRecordIterator.php');

	# db classes
	require_once (SAINT_ROOT.'/core/MySQLiConnection.php');
	require_once (SAINT_ROOT.'/core/MySQLiResult.php');
	
	# error codes and messages //TODO: Localize
	require_once (SAINT_ROOT.'/error_codes.php');
	require_once (SAINT_ROOT.'/error_messages.php');

	# base application controller
	require_once (PROJECT_ROOT.'/app/controllers/AppController.php');

	# include all user helpers in app/helpers
	$dirhandle=opendir(PROJECT_ROOT.'/app/helpers');
	while (($file = readdir($dirhandle))!==false) {
		if ($file{0} == '.') continue;
		require_once (PROJECT_ROOT.'/app/helpers/'.$file);
	}
	closedir($dirhandle);



	// ===========================================================
	// - GET USER CONFIG VALUES.
	// ===========================================================
	$redirect_on_error = false;
	require_once (PROJECT_ROOT."/config/environment.php");


	// ===========================================================
	// - GET USER USHER CONFIG.
	// ===========================================================
	__autoload('Usher');
	require_once (PROJECT_ROOT."/config/urls.php");


	// ===========================================================
	// - DEFINE LOCATION CONSTANTS. MAY BE OVERRIDDEN BY config.php
	// ===========================================================
	# define the base url for views (templates)
	if (!defined('PROJECT_VIEWS'))
		define('PROJECT_VIEWS', 		PROJECT_ROOT.'/app/views/');

	# define the base url for the html pages if this is web request
	if (array_key_exists('HTTP_HOST', $_SERVER)) {
		if (!defined('WEBBASE'))
			define('WEBBASE', 		'http://'.$_SERVER['HTTP_HOST']);

		# define the translated URI for this request
		define('PROJECT_URI', str_replace(WEBBASE, '', 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']));
	}

		


// ===========================================================
// - SETUP THE DB CONNECTION
// ===========================================================
	# get the DB config params
	require_once (PROJECT_ROOT."/config/database.php");
	
	# DB SERVICE
	$d = DBService::get_instance();
	$d->add_connection_for_classes(
		array('DBRecord'), 'mysqli', $db_name, $user, $pass, $host);


	# clear DB setup vars
	unset($db_name, $user, $pass, $host);






// ===========================================================
// - ERRORS
// ===========================================================
	function saint_error_handler($errno, $errstr, $errfile, $errline) {
		global $redirect_on_error;
		
		$e = new SaintException($errstr, $errno, $errfile, $errline);
		if ($redirect_on_error === false) {
			die($e->log());
		} else {
			redirect_to($redirect_on_error);
			die();
		}
	}
	set_error_handler('saint_error_handler');




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
		$class_name = ucfirst($class_name);

		# if it's a controller, make sure it exists
		# this is handy if people ask for a non-existent controller
		if (strpos($class_name, 'Controller') !== false) {
			$ok = file_exists(PROJECT_ROOT."/app/controllers/$class_name.php");
			if(!$ok) {			
				trigger_error("No class with the name $class_name could be found.");
			}
		}
				
		require_once("$class_name.php");
	}

?>