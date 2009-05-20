<?php

	# if it is in the lib, use that one, otherwise just include away
	$ok = @include_once('../lib/saint/saint.php');
	if (!$ok) require_once('saint.php');

	##################################

	Usher::handle_url($_SERVER['REQUEST_URI']);
	
?>
