<?php
	
	$u = Usher::get_instance();
	
	# default
	$u->map('/:controller/:action/:uid', array('uid' => NULL));

	


?>
