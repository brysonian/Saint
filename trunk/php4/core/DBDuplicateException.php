<?php


class DBDuplicateException extends DBException
{
	
	var $query = false;

	function DBDuplicateException($message, $errorcode, $query) {
		parent::__construct($message, $errorcode, $query);
	}
	
}



?>