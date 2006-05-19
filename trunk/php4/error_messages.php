<?php

// holds all the messages for the various error codes. Can be localized in the future.
$SAINT_ERROR_MESSAGES = array();



// ===========================================================
// - GENERAL
// ===========================================================


// ===========================================================
// - DBRECORD
// ===========================================================
$SAINT_ERROR_MESSAGES[VALIDATION_EMPTY]		= ':property cannot be empty.';
$SAINT_ERROR_MESSAGES[VALIDATION_NUMERIC] = ':property is not a number.';
$SAINT_ERROR_MESSAGES[VALIDATION_DATE]		= ':property cannot be understood as a date.';
$SAINT_ERROR_MESSAGES[VALIDATION_UNIQUE]	= ':property must be unique.';
$SAINT_ERROR_MESSAGES[VALIDATION_FORMAT]	= ':property is invalid.';
$SAINT_ERROR_MESSAGES[VALIDATION_EMAIL]		= ':property is not a valid email address.';
$SAINT_ERROR_MESSAGES[VALIDATION_CURSE]		= ':property cannot contain curse words.';



// ===========================================================
// - ERROR MESSAGES
// ===========================================================
function get_error_message($code, $property='') {
	global $SAINT_ERROR_MESSAGES;
	$property = str_replace('_', ' ', $property);
	
	if (array_key_exists($code, $SAINT_ERROR_MESSAGES)) {
		$msg = $SAINT_ERROR_MESSAGES[$code];
	} else {
		$msg = $code;
	}
	return $msg;
}



?>