<?php

/**
	Pages a DBRecordCollection.
	Returned by dbrcollection->paginate()

	@author Chandler McWilliams
	@version 2006-06-03
*/
class DBRecordCollectionPaginator implements Iterator
{

	protected $per_page = 10;
	protected $page_count = false;
	protected $current_page = 1;
	
	protected $iterator;
	
// ===========================================================
// - CONSTRUCTOR
// ===========================================================
	public function __construct($iterator, $per_page=10) {
		$this->iterator = $iterator;		
		$this->per_page = $per_page;		
		$this->iterator->set_limit(params('page'), $this->per_page);
	}

// ===========================================================
// - ACCESSORS
// ===========================================================
	function get_page($num=false) {
		$this->current_page = $num;
		
		# if num is false, grab from the params
		$num = ($num===false)?params('page'):$num;
		
		# still might be, so default to zero
		$num = $num?(($num-1)*$this->per_page):0;
		
		$this->iterator->set_limit($num, $this->per_page);
		return $this->iterator;
	}
	
	function num_pages() {
		if ($this->page_count === false) {
			# load them all
			$dupe = clone $this->iterator;
			$dupe->set_limit(false);
			
			$c = 0;
			foreach($dupe as $v) $c++;
			$this->page_count = ceil($c/$this->per_page);
		}
		return $this->page_count;
	}


// ===========================================================
// - ITERATOR INTERFACE
// ===========================================================
	function rewind() {
		$c = $this->num_pages();
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

}

?>