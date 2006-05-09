<?php
	# setup with init
	require_once('saint.php');
	##################################


	try {
		Usher::handle_url($_SERVER['REQUEST_URI']);
	} catch (Exception $e) {
		echo $e->log();
	}
	
?>
