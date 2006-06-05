<?php

# start session
session_start();

class LoginSystem extends DBRecord
{

	static private	$instance = false;
	static private	$user			= false;

	
// ===========================================================
// - VERIFY FROM DB
// ===========================================================
	static function authenticate($username, $password) {
		# get a model to use
		$d = new DBRecord;
		$result = User::find_where("password='".md5($password)."' AND username='".$d->escape_string($username)."'");

		# process results
		if ($result) {
			LoginSystem::start_login($result);
			return true;
		} else {
			return false;
		}

	}

	
// ===========================================================
// - DOES THE ACTUAL LOGING IN
// ===========================================================
	static function start_login($auser) {
		session_regenerate_id();
		$_SESSION['uid'] = $auser->get_uid();
		$_SESSION['time'] = time();
		
		LoginSystem::$user = $auser;
		
	}

	static function get_user() {
		return LoginSystem::$user;
	}
	
	function expire($newtime) {
		setcookie(session_name(),session_id(),$newtime, '/');
	}

// ===========================================================
// - GET USER DATA FROM THE SESSION
// ===========================================================	
	# singleton
	static function get_instance() {
		if(!self::$instance) {
			self::$instance =  = new User;
			self::$instance = ->set_uid($_SESSION['uid']);
			self::$instance = ->load();
		}
		return self::$instance;
	}

	
	// sets the password
	function set_password($val) {
		$this->set('password', md5($val));
	}

// ===========================================================
// - CHECKS IF USER IS LOGGED IN
// ===========================================================
	function is_logged_in() {
		if(!empty($_SESSION['uid']) && !empty($_SESSION['time'])) {
			return true;
		} else {
			return false;
		}
	
	}

// ===========================================================
// - LOGS USER OUT
// ===========================================================
	function logout() {
		# kill the cookie
		if (isset($_COOKIE[session_name()])) {
			setcookie(session_name(), '', time()-42000, '/');
		}
		
		# delete the session
		unset($_SESSION['time']);
		unset($_SESSION['uid']);
		session_destroy();
	}
}



?>