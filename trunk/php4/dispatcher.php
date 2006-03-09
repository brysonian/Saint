<?php
	# setup with init
	require_once('saint.php');
	##################################


	# set default values
	# usersec can be overridden in local indexes
	#if (!isset($usersec)) {
	#	$usersec = basename(dirname($_SERVER['SCRIPT_NAME']));
	#}

	Usher::handle_url($_SERVER['REQUEST_URI']);
	
?>
