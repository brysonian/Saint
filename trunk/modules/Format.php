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
	public static function prettyDatetime($value) {
		if (empty($value)) return $value;
		$value = strtotime($value);
		if (($value === false) || ($value == -1)) return $value;
		return date('F j, Y h:i A', $value);
	}

	# format a date string into something like: 11:05am
	public static function prettyTime($value) {
		if (empty($value)) return $value;
		$value = strtotime($value);
		if (($value === false) || ($value == -1)) return $value;

		return date('h:i A', $value);
	}

	# format a date string into something like: April 1, 1976
	public static function prettyDate($value) {
		if (empty($value)) return $value;
		$value = strtotime($value);
		if (($value === false) || ($value == -1)) return $value;

		return date('F j, Y', $value);
	}

	public static function mysqlDate($value) {
		if (empty($value)) return $value;
		$value = strtotime($value);
		if (($value === false) || ($value == -1)) return $value;

		return date('Y-m-d', $value);
	}

	public static function mysqlDateTime($value) {
		if (empty($value)) return $value;
		$value = strtotime($value);
		if (($value === false) || ($value == -1)) return $value;

		return date('Y-m-d h:i:s', $value);
	}

	public static function mysqlTime($value) {
		if (empty($value)) return $value;
		$value = strtotime($value);
		if (($value === false) || ($value == -1)) return $value;

		return date('h:i:s', $value);
	}

	# format a date string into something like: Apr 1, 1976
	public static function prettyShortDate($value) {
		if (empty($value)) return $value;
		$value = strtotime($value);
		if (($value === false) || ($value == -1)) return $value;

		return date('M j, Y', $value);
	}

	public static function prettyShortDateTime($value) {
		if (empty($value)) return $value;
		$value = strtotime($value);
		if (($value === false) || ($value == -1)) return $value;

		return date('M j, Y h:i A', $value);
	}
	
	public static function simpleShortDate($value) {
		if (empty($value)) return $value;
		$value = strtotime($value);
		if (($value === false) || ($value == -1)) return $value;

		return date('m/d/Y', $value);
	}
	
	# formats a string as a date
	# see http://us4.php.net/manual/en/function.date.php
	# for format options
	public static function dateAs($value, $format) {
		if (empty($value)) return $value;
		$value = strtotime($value);
		if (($value === false) || ($value == -1)) return $value;

		return date($format, $value);
	}
	
	# parse a mysql date into a string
	public static function parseMySQLTime($str) {
		$out = strtotime($value);
		if (($out != -1) || ($out !== false)) return $out;
		
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