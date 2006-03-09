<?php


class DBDuplicateException extends DBException
{
	
	var $query = false;

	function DBDuplicateException($message, $errorcode, $query) {
		parent::DBException($message, $errorcode, $query);
	}
	
}



?>