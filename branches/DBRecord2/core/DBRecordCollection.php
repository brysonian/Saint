<?php


class DBRecordCollection implements Iterator, Countable, ArrayAccess
{

	protected $loaded = false;
	public $query;
	public $limit;
	protected $result;

	protected $current;
	protected $max;

	protected $key;
	protected $valid;
	protected $row;
	protected $nextRow;
	
	protected $first;
	protected $last;
	
	protected $num_objects = 0;
		
	
// ===========================================================
// - CONSTRUCTOR
// ===========================================================
	function __construct($model, $query, $db) {
		$this->key = 0;
		$this->model = $model;
		$this->query = $query;		
		$this->limit = '';

		# clear props
		$this->row = false;
		
		# get a ref to the dbconnection
		$this->db = $db;
	}

// ===========================================================
// - ACCESSORS
// ===========================================================
	function  get_model()		{ return $this->model; }
	function  get_num_objects()		{ return $this->num_objects; }
	function  set_limit($min, $max=false) {
		$this->loaded = false;
		$this->max = $max;
		if ($min === false) {
			$this->limit = '';
		} else {
			if ($max) {
				$this->limit = " LIMIT $min".($max?', '.($max*200):'');
			}
		}
	}
	
	function paginate($per_page=10) {
		$c = clone $this;
		$p = new DBRecordCollectionPaginator($c, $per_page);
		return array($p, $p->get_page());
	}




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
		$this->current = 0;
	}
	
	function valid() {
		if (!$this->row) $this->row = $this->result->fetch_assoc();
		if (!empty($this->row)) {
			$this->nextRow = $this->result->fetch_assoc();
			$this->valid = true;
		} else {
			$this->valid = false;
		}
		
		# check for limits
		if ($this->max) {
			if ($this->current >= $this->max) {$this->valid = false;}
			$this->num_objects = $this->current;
		}
		return $this->valid;
	}
	
	function  current() {
		# reset the model
		$themodel = clone $this->get_model();
		do {
			$themodel->process_row($this->row);
			if ($this->nextRow && ($this->nextRow['id'] == $this->row['id'])) {
				$this->next();
				$this->valid();
			} else {
				break;
			}
		} while(true);
		$this->current++;
		return $themodel;
	}

	function key() {
		return $this->key;
	}
	
	function next() {
		$this->key++;
		$this->row = $this->nextRow;
	}



// ===========================================================
// - COLLECTION ACCESS
// ===========================================================
	function first() {
		if (!$this->first) {
			$this->rewind();
			if (count($this->result) === false) return false;
			if ($this->valid()) {
				$this->first = $this->current();
			} else {
				return false;
			}
		}
		return $this->first;
	}
	
	function item($num) {
		$c = 0;
		foreach($this as $k => $v) {
			if ($c == $num) return $v;
			$c++;
		}
		return false;
	}

	function last() {
		if (!$this->last) {
			foreach($this as $k => $v) {}
			$this->last = $v;
		}
		return $this->last;
	}
	
	function is_empty() {
		return !$this->first();
	}

// ===========================================================
// - ARRAYACCESS INTERFACE
// ===========================================================
	public function offsetExists($offset) {
		if ($this->item($offset) !== false) return true;
		return false;
	}
	
	public function offsetGet($offset) {
		return $this->item($offset);
	}

	public function offsetSet($offset, $value) {
		throw new ReadOnlyAccess('DBRecordCollection items are read only.');
	}

	public function offsetUnset($offset) {
		throw new ReadOnlyAccess('DBRecordCollection items are read only.');
	}





// ===========================================================
// - DB RELATED
// ===========================================================
	function reload() {
		$this->loaded = false;
		$this->result = false;
		$this->load();
	}
	
	// load all entries from the DB
	function load() {
		if ($this->loaded && $this->result) {
			$this->result->data_seek(0);
		} else {
			$this->result = $this->db->query($this->query.$this->limit);

			# check result
			if ($this->result === false) {
				throw new DBRecordError("Error loading ".get_class($this->model).".\n".$this->db->error(), 0, $this->query.$this->limit);
			}
			$this->loaded = true;
		}
	}

	function free() {
		if($this->result) $this->result->free();
	}
	


// ===========================================================
// - COUNTABLE INTERFACE
// ===========================================================
	public function count() {
		$where = $this->model->get_where()?' WHERE '.$this->model->get_where():'';
		$unique_result = $this->db->query('SELECT uid from '.$this->model->get_table().$where.' GROUP BY uid '.$this->limit);
		return count($unique_result);
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
	function to_a($deep=false) { return $this->to_array(); }
	function to_array($deep=false) {
		$out = array();
		foreach($this as $obj) {
#(var_export($obj->to_array()));
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