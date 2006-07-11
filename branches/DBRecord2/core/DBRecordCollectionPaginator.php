<?php

/**
	Pages a DBRecordCollection.
	Returned by dbrcollection->paginate()

	@author Chandler McWilliams
	@version 2006-06-03
*/
class DBRecordCollectionPaginator implements Iterator, Countable, ArrayAccess
{

	protected $per_page = 10;
	protected $page_count = false;
	protected $current_page = 1;
	protected $page_param = 'page';
	
	protected $iterator;
	
// ===========================================================
// - CONSTRUCTOR
// ===========================================================
	public function __construct($iterator, $per_page=10) {
		$this->iterator = $iterator;		
		$this->per_page = $per_page;		
	}

// ===========================================================
// - ACCESSORS
// ===========================================================
	function set_page_param($val) {
		$this->page_param = $val;
	}
	function is_last_page() { return $this->iterator->get_num_objects() < $this->per_page; }

	function get_page($num=false) {
		$this->current_page = $num;
		
		# if num is false, grab from the params
		$num = ($num===false)?params($this->page_param):$num;
		
		# still might be, so default to zero
		$num = $num?(($num-1)*$this->per_page):0;
		$this->iterator->set_limit($num, $this->per_page);
		return $this->iterator;
	}
	

// ===========================================================
// - COUNTABLE INTERFACE
// ===========================================================
	function count() {
		if ($this->page_count === false) {
			# load them all
			$dupe = clone $this->iterator;
			$dupe->set_limit(false);
			
			$c = count($dupe);
			$this->page_count = ceil($c/$this->per_page);
		}
		return $this->page_count;
	}


// ===========================================================
// - ITERATOR INTERFACE
// ===========================================================
	function rewind() {
		$c = count($this);
		$this->current_page = 1;
	}
	
	function valid() {
		return ($this->current_page <= $this->page_count);
	}
	
	function  current() {
		return $this->get_page($this->current_page);
	}

	function key() {
		return $this->current_page;
	}
	
	function next() {
		$this->current_page++;
	}


// ===========================================================
// - ARRAYACCESS INTERFACE
// ===========================================================
	public function offsetExists($offset) {
		if ($this->get_page($offset) !== false) return true;
		return false;
	}
	
	public function offsetGet($offset) {
		return $this->get_page($offset);
	}

	public function offsetSet($offset, $value) {
		throw new ReadOnlyAccess('DBRecordCollectionPaginator pages are read only.');
	}

	public function offsetUnset($offset) {
		throw new ReadOnlyAccess('DBRecordCollectionPaginator pages are read only.');
	}


}

?>