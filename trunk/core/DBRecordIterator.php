<?php


class DBRecordIterator implements Iterator
{

	var $result;
	var $key;
	var $valid;
	var $row;
	var $nextRow;
	var $query;
	
// ===========================================================
// - CONSTRUCTOR
// ===========================================================
	function DBRecordIterator($model, $query, $db) {
		$this->key = 0;
		$this->model = $model;
		$this->query = $query;

		# clear props
		$this->row = false;
		
		# get a ref to the dbconnection
		$this->db = $db;
	}

// ===========================================================
// - ACCESSORS
// ===========================================================
	function  get_model()		{ return $this->model; }


// ===========================================================
// - ITERATOR INTERFACE
// ===========================================================
	function rewind() {
		if ($this->result) $this->result->free();
		$this->key = 0;
		$this->valid = false;
		$this->result = false;
		$this->load();
		$this->row = $this->result->fetch_assoc();
	}
	
	function valid() {
		if (!$this->row) $this->row = $this->result->fetch_assoc();
		if (!empty($this->row)) {
			$this->nextRow = $this->result->fetch_assoc();
			$this->valid = true;
		} else {
			$this->valid = false;
		}
		return $this->valid;
	}

	function key() {
		return $this->key;
	}
	
	function  current() {
		# reset the model
		$themodel = $this->get_model();
		$themodel->reset();
		do {
			$themodel->process_row($this->row);
			if ($this->nextRow && ($this->nextRow['id'] == $this->row['id'])) {
				$this->next();
				$this->valid();
			} else {
				break;
			}
		} while(true);
		
		return $this->get_model();
	}
	
	function next() {
		$this->key++;
		$this->row = $this->nextRow;
	}


// ===========================================================
// - HELPER
// ===========================================================
	// load all entries from the DB
	function load() {
		$this->result = $this->db->query($this->query);

		# check result
		if ($this->result === false) {
			throw(new DBException("Error loading ".__CLASS__.".\n".$this->db->error(), 0, $this->query));
		}
	}

	function free() {
		if($this->result) $this->result->free();
	}
	
	function num_rows() {
		if (!$this->result) $this->load();
		return $this->result->num_rows();
	}

	
// ===========================================================
// - REPRESENTATIONS
// ===========================================================
	// get xml rep of this object
	function to_xml($str=false) {
		# make doc and root
		$dom = new DomDocument;
		$root = $dom->createElement($this->get_model()->get_table().'s');
		$root = $dom->appendChild($root);
		
		# get all objects in this list
		foreach ($this as $obj) {
			$obj_xml = $obj->to_xml();
			$obj_xml = $dom->importNode($obj_xml->documentElement, true);
			$root->appendChild($obj_xml);
		}
		return ($str)?$dom->saveXML():$dom;
	}

	// get array rep of this object
	function to_array($deep=false) {
		$out = array();
		foreach($this as $obj) {
			if ($deep) {
				$out[] = $obj->to_array();
			} else {
				$out[] = $obj;
			}
		}
		return $out;
	}
}



?>