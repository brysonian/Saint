<?php

# start session
session_start();

# if the user class didn't happen, tell them they need it
if (!class_exists('User')) throw(new SaintException('Login system requires a user defined subclass of LoginUser'));

# make an instance to start it off
# this makes sure the associated classes are loaded.
# in PHP5 this can go away
new User;

class LoginUser extends DBRecord
{
	
// ===========================================================
// - VERIFY FROM DB
// ===========================================================
	function authenticate($username, $password) {
		# get a model to use
		$user = new User('user');
		$result = $user->find_where("password='".md5($password)."' AND username='".mysql_escape_string($username)."'");

		# process results
		if ($result) {
			User::startLogin($result[0]['uid']);
			return true;
		} else {
			return false;
		}

	}

	
// ===========================================================
// - DOES THE ACTUAL LOGING IN
// ===========================================================
	function startLogin($uid) {
		session_regenerate_id();
		$_SESSION['uid'] = $uid;
		$_SESSION['time'] = time();
	}
	
	function expire($newtime) {
		setcookie(session_name(),session_id(),$newtime, '/');
	}

// ===========================================================
// - GET USER DATA FROM THE SESSION
// ===========================================================
	function &get_instance() {
		static $u;
		if (!isset($u)) {
			$u = new User;
			$u->setUID($_SESSION['uid']);
			$u->load();
		}
		return $u;
	}
	
	// sets the password
	function setPassword($val) {
		$this->set('password', md5($val));
	}

// ===========================================================
// - CHECKS IF USER IS LOGGED IN
// ===========================================================
	function isLoggedIn() {
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