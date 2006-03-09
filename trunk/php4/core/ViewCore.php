<?php


class ViewCore
{
	
/**
* Get the root of the current site
*/
	function root() {
		return Usher::get_root();
	}

/**
* Get a link to the specified resource
*/
	function link_to($name, $controller=false, $action=false, $params=false) {
		return "<a href='".$this->url_for($controller, $action, $params)."'>$name</a>";
	}

/**
* Get the url for the specified resource
*/
	function url_for($controller=false, $action=false, $params=false) {
		
	}

}


?>