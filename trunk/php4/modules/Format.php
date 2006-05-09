<?php
/**
	Provides access to common formatters.
	Includes a bunch of handy date formatting methods

	@author Chandler McWilliams
	@version 2005-05-23
*/

class Format {
	
	function Format() {}
		
	# format a date string into something like: April 1, 1976 11:05am
	function prettyDatetime($value) {
		if (strlen($value) == 14) $value = Format::parseMySQLTime($value);
		return date('F j, Y h:i A', strtotime($value));
	}

	# format a date string into something like: 11:05am
	function prettyTime($value) {
		if (strlen($value) == 14) $value = Format::parseMySQLTime($value);
		return date('h:i A', strtotime($value));
	}

	# format a date string into something like: April 1, 1976
	function prettyDate($value) {
		if (strlen($value) == 14) $value = Format::parseMySQLTime($value);
		return date('F j, Y', strtotime($value));
	}

	# format a date string into something like: Apr 1, 1976
	function prettyShortDate($value) {
		if (strlen($value) == 14) $value = Format::parseMySQLTime($value);
		return date('M j, Y', strtotime($value));
	}

	function prettyShortDateTime($value) {
		if (strlen($value) == 14) $value = Format::parseMySQLTime($value);
		return date('M j, Y h:i A', strtotime($value));
	}
	
	function simpleShortDate($value) {
		if (strlen($value) == 14) $value = Format::parseMySQLTime($value);
		return date('m/d/Y', strtotime($value));
	}
	
	# formats a string as a date
	# see http://us4.php.net/manual/en/function.date.php
	# for format options
	function dateAs($value, $format) {
		if (strlen($value) == 14) $value = Format::parseMySQLTime($value);
		return date($format, strtotime($value));
	}
	
	# parse a mysql date into a string
	function parseMySQLTime($str) {
		
		$out =  substr($str, 0, 4).'-';
		$out .=  substr($str, 4, 2).'-';
		$out .=  substr($str, 6, 2).' ';

		$out .=  substr($str, 8, 2).':';
		$out .=  substr($str, 10, 2).':';
		$out .=  substr($str, 12, 2);
		return $out;

	}

}
	
	function dt($value) {
		return Format::prettyDatetime($value);
	}
	
	function d($value) {
		return Format::prettyDate($value);
	}
	
	function t($value) {
		return Format::prettyTime($value);
	}
	
	function dateAs($value, $format) {
		return Format::dateAs($value, $format);
	}
?>