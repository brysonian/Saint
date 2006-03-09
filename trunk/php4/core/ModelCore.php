<?php


class ModelCore
{
	var $data			= array();
	var $table;
	var $db;
	var $toMany;
	var $toOne;
	var $toManyObj;
	var $toOneObj;
	var $toManyClass;
	var $order;
	var $where;
	var $group;
	var $limit;


// ===========================================================
// - CONSTRUCTOR
// ===========================================================
	function ModelCore($table=false) {
		# set table name if none was given
		if (!$table) {
			$table = strtolower(get_class($this));
		}
		$this->setTable($table);
		
		# get a ref to the dbconnection
		global $dbconnection;
		$this->db =& $dbconnection;

		if (method_exists($this, 'init')) $this->init();
	}



// ===========================================================
// - ACCESSORS
// ===========================================================
	// for id
	function get_id()		{ return isset($this->data['id'])?$this->data['id']:false; }
	function set_id($id)	{ 
		if (!is_numeric($id)  && $id !== false) throw(new Exception("Invalid ID.", 0));
		$this->data['id'] = $id; 
	}

	// for uid
	function get_uid()		{ return isset($this->data['uid'])?$this->data['uid']:false; }
	function set_uid($uid)	{
		if (strlen($uid) != 32 && $uid !== false) throw(new Exception("Invalid UID.", 0));
		$this->data['uid'] = $uid; 
	}

	// for table
	function getTable()		{ return $this->table; }
	function setTable($t)	{ $this->table = $t; }
	
	// query params
	function		getOrder()		{ return $this->order; }
	function		setOrder($t)	{ $this->order = $t;}

	function		getWhere()		{ return $this->where; }
	function		setWhere($t)	{ $this->where = $t; }
	
	function		getGroup()		{ return $this->group; }
	function		setGroup($t)	{ $this->group = $t; }
	
	function		getLimit()		{ return $this->limit; }
	function		setLimit($t)	{ $this->limit = $t; }
	
	// get
	function get($prop) {
		# first check in data
		if (isset($this->data[$prop])) return $this->data[$prop];
		
		# then the toOne's
		if (isset($this->toOneObj[$prop])) return $this->toOneObj[$prop];

		# then try the toManys
		$tm = $this->getToManyObjects($prop);
		
		# if they're are some, return each as an array
		if ($tm) {
			$out = array();
			foreach ($tm as $obj) {
				$out[] = $obj->to_array();
			}
			return $out;			
		}
		
		return false;
	}
	
	// set
	function set($prop, $val) {
		if (is_null($val)) {
			unset($this->data[$prop]);
		} else {
			$this->data[$prop] = $val;
		}
	}

	// get a list of toMany object
	function getToManyObjects($prop) {
		# check if it's there
		if (isset($this->toManyObj[$prop])) {
			#$ao = new ArrayObject($this->toManyObj[$prop]);
			#return $ao->getIterator();
			return $this->toManyObj[$prop];
		}
		return false;
	}


// ===========================================================
// - SAVE TO DB
// ===========================================================
	// save to the db
	function save() {
		# if no id or uid, then insert a new post, otherwise update
		if ($this->get_id() || $this->get_uid()) {
			$this->update();
		} else {
			$this->create();
		}
	}

	function create() {
		$sql = "INSERT INTO ".$this->getTable()." ";

		# generate values statement
		$name = array();
		$value = array();
		
		$name[] = 'id';
		$value[] = 'NULL';
		$name[] = 'uid';
		$value[] = '"'.$this->gen_uid().'"';
		
		foreach ($this->data as $k=>$v) {
			if ($k == 'id' || $k == 'uid') continue;
			$name[] = $k;

			# if add slashes is on, strip them
			if (get_magic_quotes_gpc() == 1) $v = stripslashes($v);
			$value[] = "'".$this->escape_string($v)."'";

		}	

		$sql .= '('.join(',', $name).')'.' VALUES '.'('.join(',', $value).')';
		$result = $this->db->query($sql);
		if ($result->valid()) {
			$this->set_id($this->db->insert_id());
		} else {
			if ($this->db->errno() == DUPLICATE_ENTRY) {
				throw(new DBDuplicateException($this->db->error(), $this->db->errno(), $sql));
			} else {
				throw(new DBException("Error loading ".__CLASS__.".\n".$this->db->error(), $this->db->errno(), $sql));
			}
		}
	}
	
	
	function update() {
		$sql = "UPDATE ".$this->getTable()." SET ";
		$props = array();
		foreach ($this->data as $k=>$v) {
			if ($k == 'id' || $k == 'uid') continue;
			# if add slashes is on, strip them
			if (get_magic_quotes_gpc() == 1) $v = stripslashes($v);
			$props[] = "$k='".$this->escape_string($v)."'";
		}	
		
		$sql .= join(',',$props)." WHERE id=".$this->escape_string($this->get_id());
		

		$result = $this->db->query($sql);
		if (!$result) {
			throw(new DBException("Error loading ".__CLASS__.".\n".$this->db->error(), $this->db->errno(), $sql));
		}
	}



	function delete() {
		if (!($this->get_id() || $this->get_uid())) {
			throw(new Exception("You must define an ID or UID to delete an item", 666));			
		}
			
		$sql = "DELETE FROM ".$this->getTable()." WHERE ";
		$sql .= $this->get_id()?"id=".$this->get_id():"uid = '".$this->get_uid()."'";
		
		$result = $this->db->query($sql);
		if (!$result) {
			throw(new DBException("Error deleting ".__CLASS__.".\n".$this->db->error(), $this->db->errno(), $sql));
		}
	}





// ===========================================================
// - LOAD FROM DB
// ===========================================================
	// load item from the db using id
	function load() {
		
		# start where clause if there isn't one
		$where = $this->getWhere()?' AND ':' WHERE ';
		
		# if ID, use that in where, otherwise try UID
		# if neither one, error
		if ($this->get_id()) {
			$where .= $this->getTable().'.id='.$this->get_id();
		} else if ($this->get_uid()) {
			$where .= $this->getTable().".uid='".$this->get_uid()."'";
		} else {
			throw(new Exception("You must define a ID or UID to load an object.", 0));
		}
		
		# get the query
		$sql = $this->getQuery();

		
		# add the where clause
		$sql .= $where;
		
		# run the query
		$result = $this->db->query($sql);

		# process results
		if (!$result) {
			throw(new DBException("Error loading ".__CLASS__.".\n".$this->db->error(), 0, $sql));
		} else {
			if ($row = $result->fetch_assoc()) {				
				do {
					$this->processRow($row);
				} while ($row = $result->fetch_assoc());
				
			} else {
				throw(new Exception("Nothing found with id: ".$this->get_id()." or uid: ".$this->get_uid().".\n", 0));
			}
		}
	}
	
	// ===========================================================
	// - RESET ALL DATA PROPS
	// ===========================================================
	function reset() {
		if (is_array($this->toOneObj)) {
			foreach($this->toOneObj as $k=>$v) {
				$this->toOneObj[$k]->reset();	
			}
		}
		
		if (is_array($this->toManyObj)) {
			foreach($this->toManyObj as $k=>$v) {
				$this->toManyObj[$k] = array();
			}
		}
		$this->data			= array();
	}
	
	// return an a single item by id
	function find($id) {
		$this->set_uid($id);
		$this->load();
		return $this->to_array();
	}

	// return an array of all objects of this type
	function find_all($mode=false) {
		$sibs = new DBOIterator($this, $this->getQuery());
		if ($mode) return $sibs;		
		return $sibs;
	}
	
	// return an array of all objects using this where clause
	function find_where($where, $mode=false) {
		$this->setWhere($where);
		$sibs = new DBOIterator($this, $this->getQuery());
		if ($mode) return $sibs;		
		return $sibs->to_array();
	}
	
	// find an item by uid
	function find_id($id) {
		$this->set_id($id);
		$this->load();
		return $this->to_array();
	}

	// return an array of all objects using this query
	function find_sql($sql, $mode=false) {
		$sibs = new DBOIterator($this, $sql);
		if ($mode) return $sibs;		
		return $sibs->to_array();
	}
	
	// run arbitrary sql without processing
	function exec($sql) {
		# run the query
		$result = $this->db->query($sql);

		# process results
		if (!$result) {
			throw(new DBException("Query Failed .\n".$this->db->error(), $this->db->errno(), $sql));
		}
		return true;
	}
	
	

	// get the query for this obj
	function getQuery() {
		# make query
		$sql = "SELECT ".$this->getTable().".*";

		# add toOne
		if (!empty($this->toOne)) {
			# loop through toOne's
			foreach ($this->toOne as $v) {
				$info = $this->db->tableInfo($v);
				foreach ($info['order'] as $col => $order) {
					# skip columns that have the table name in them
					if (strpos($col, $this->getTable().'_') !== false) continue;
					$sql .= ','.$v.'.'.$col. ' as '.$v.'_'.$col;					
				}
			}
		}
		# add toMany
		if (!empty($this->toMany)) {
			# loop through toMany's
			foreach ($this->toMany as $v) {
#				$info = $this->db->tableInfo($this->getTable()."_".$v);
				$info = $this->db->tableInfo($v);
				foreach ($info['order'] as $col => $order) {
					# skip columns that have the table name in them
					if (strpos($col, $this->getTable().'_') !== false) continue;
#					$sql .= ','.$this->getTable()."_".$v.'.'.$col. ' as '.$v.'_'.$col;					
					$sql .= ','.$v.'.'.$col. ' as '.$v.'_'.$col;					
				}
			}
		}
		
		# add from
		$sql .= " FROM ".$this->getTable()." ";

		# join toOne
		if (!empty($this->toOne)) {
			foreach ($this->toOne as $v) {
				$sql .= " LEFT JOIN {$v} ON {$v}.id = ".$this->getTable().".{$v}_id ";
				
			}
		}

		# join toMany
		if (!empty($this->toMany)) {
			# loop through toMany's
			foreach ($this->toMany as $v) {
#				$sql .= " LEFT JOIN ".$this->getTable()."_$v ON ".$this->getTable()."_$v.".$this->getTable()."_id = ".$this->getTable().".id ";
				$sql .= " LEFT JOIN $v ON $v.".$this->getTable()."_id = ".$this->getTable().".id ";
			}
		}
		
		# add WHERE clause
		if ($this->getWhere()) $sql .= " WHERE ".$this->getWhere();
		
		# add group by if there is one
		if ($this->getGroup()) $sql .= " GROUP BY ".$this->getGroup();

		# add order by if there is one
		if ($this->getOrder()) $sql .= " ORDER BY ".$this->getOrder();

		# add order by if there is one
		if ($this->getLimit()) $sql .= " LIMIT ".$this->getLimit();

		return $sql;
	}


	// process a row
	function processRow($row) {
		# skip cols with tablename_id
		$skipme = $this->getTable().'_id';

		# set props (loop columns)
		foreach ($row as $k=>$v) {
			if ($k == $skipme) continue;
			# split the k at the last _ and see if we need to make a model object
			# using the names of our toMany and toOne's

			$pos = strrpos($k, '_');
			$split = ($pos!==false)?substr($k, 0, $pos):$k;

			
#			$split = explode('_', $k);
#			$split = $split[0];

			# toOne
			if (!empty($this->toOne) && in_array($split, $this->toOne)) {
				# remove the prefix from the prop names
				$prop = str_replace($split.'_', '', $k);
				if ($prop == 'id') {
					if (!empty($v)) $this->toOneObj[$split]->set_id($v);
				} else if ($prop == 'uid') {
					if (!empty($v)) $this->toOneObj[$split]->set_uid($v);
				} else {
					$this->toOneObj[$split]->set($prop, stripslashes($v));
				}
	
	
			# toMany
			} else if (!empty($this->toMany) && in_array($split, $this->toMany)) {
				# skip ones without an id
				if (empty($row[$split.'_id'])) continue;
				
				# if the obj doesn't exist yet, make it
				# objs are in the toManyObj[name] array indexed by id
				if (!isset($this->toManyObj[$split][$row[$split.'_id']])) {
					$cname = $this->toManyClass[$split];
					$this->toManyObj[$split][$row[$split.'_id']] = new $cname;
				}
				
				# remove the prefix from the prop names
				$prop = str_replace($split.'_', '', $k);
				if ($prop == 'id') {
					$this->toManyObj[$split][$row[$split.'_id']]->set_id($v);
				} else if ($prop == 'uid') {
					$this->toManyObj[$split][$row[$split.'_id']]->set_uid($v);
				} else {
					$this->toManyObj[$split][$row[$split.'_id']]->set($prop, stripslashes($v));
				}
			# normal
			} else {
				$this->set($k, stripslashes($v));
			}
		}
	}


// ===========================================================
// - ADD TO-MANY AND TO-ONE RELATIONSHIPS
// ===========================================================
	function has_one($table) {
		$table = strtolower($table);
		# if toOnes are empty make an array
		if (empty($this->toOne)) {
			$this->toOne = array();
			$this->toOneObj = array();
		}
		$this->toOne[] = $table;
		
		# include the classes
		__autoload(ucfirst($table));
		__autoload(ucfirst($table)."Controller");

		# create the obj
		$cname = ucfirst($table);
		$this->toOneObj[$table] = new $cname;
	}

	function has_many($class, $table=false) {
		# if no table, try to get the tablename
		if ($table == false) {
			$table = $this->get_table_from_classname($class);
		}


		# if toMany are empty make an array
		if (empty($this->toMany)) {
			$this->toMany = array();
			$this->toManyObj	= array();
			$this->toManyClass	= array();
		}
		$this->toMany[] = $table;

		# create the obj
		$this->toManyObj[$table] = array();
		$this->toManyClass[$table] = $class;
		
		# include the classes
		__autoload($class);
		__autoload($class."Controller");
	}




// ===========================================================
// - UTILITIES
// ===========================================================
	// initalize UID
	function gen_uid() {
		# need to make sure this UID isn't taken already
		$this->set_uid(md5(uniqid(rand(), true)));
		return $this->get_uid();
	}

	// parse a tablename into a classname
	function get_classname_from_table($table) {
		return preg_replace('/(?:^|_)([a-zA-Z])/e', "strtoupper('\\1')", $table);
	}
	// parse a classname into a tablename
	function get_table_from_classname($class) {
		return strtolower(preg_replace('/([a-zA-Z])([A-Z])/', '\\1_\\2', $class));
	}
	

	
// ===========================================================
// - REPRESENTATIONS
// ===========================================================
	// get xml rep of this object
	function xmlRep() {
		/*
		# make doc and root
		$dom = new DomDocument;
		$root = $dom->createElement($this->getTable());
		$root = $dom->appendChild($root);
		
		# add id and uid
		if ($this->get_id()) $root->setAttribute('id', $this->get_id());
		if ($this->get_uid()) $root->setAttribute('uid', $this->get_uid());

		# add node for each prop
		foreach ($this->data as $k=>$v) {
			if ($k == 'id' || $k == 'uid') continue;
			$node = $dom->createElement($k);
			$cdata = $dom->createCDATASection($v);
			$node->appendChild($cdata);

			$node = $root->appendChild($node);
		}
					
		# add nodes for each toOne
		if (!empty($this->toOneObj)) {
			foreach ($this->toOneObj as $k=>$v) {
				$node = $dom->importNode($v->xmlRep()->documentElement, true);
				$node = $root->appendChild($node);
			}
		}
					
		# add nodes for each toMany
		if (!empty($this->toManyObj)) {
			foreach ($this->toManyObj as $k=>$v) {
				# add node for chirren
				$list = $dom->createElement('to-many');
				$list->setAttribute('name', $k);
				$list = $root->appendChild($list);

				# add items				
				foreach ($this->getToManyObjects($k) as $obj) {
					$node = $dom->importNode($obj->xmlRep()->documentElement, true);
					$node = $list->appendChild($node);
				}
			}
		}
		return $dom;
		*/
		die ("not yet");
	}


	// get array rep of this object
	function to_array() {
		$out = array();
		
		# add id and uid
		$out['id'] = $this->get_id();
		$out['uid'] = $this->get_uid();
		
		# add each prop
		foreach ($this->data as $k=>$v) {
			if ($k == 'id' || $k == 'uid') continue;
			$out[$k] = $v;
		}

		# add each toOne
		if (!empty($this->toOneObj)) {
			foreach ($this->toOneObj as $k=>$v) {
				# get the array rep and loop through it, adding each prop
				# and prepending the table name
				$a = $v->to_array();
				foreach ($a as $a_k => $a_v) {
					$out[$k."_$a_k"] = $a_v;
				}
			}
		}

		# add each toMany
		if (!empty($this->toManyObj)) {
			foreach ($this->toManyObj as $k=>$v) {
				# add array for chirren
				$out[$k] = array();

				# add items				
				foreach ($this->getToManyObjects($k) as $obj) {
					$out[$k][] = $obj->to_array();
				}
			}
		}

		
		return $out;
	}
	
	function escape_string($v) {
		return $this->db->escape_string($this->uf8_to_entities($v));
	}

	function uf8_to_entities($str) {
		$unicode = array();
		$values = array();
		$lookingFor = 1;
		for ($i = 0; $i < strlen( $str ); $i++ ) {
			$thisValue = ord( $str[ $i ] );
			if ( $thisValue < 128 ) $unicode[] = $thisValue;
			else {
				if ( count( $values ) == 0 ) $lookingFor = ( $thisValue < 224 ) ? 2 : 3;
				$values[] = $thisValue;
				if ( count( $values ) == $lookingFor ) {
					$number = ( $lookingFor == 3 ) ? ( ( $values[0] % 16 ) * 4096 ) + ( ( $values[1] % 64 ) * 64 ) + ( $values[2] % 64 ):( ( $values[0] % 32 ) * 64 ) + ( $values[1] % 64 );
					$unicode[] = $number;
					$values = array();
					$lookingFor = 1;
				}
			}
		}
		$entities = '';
		foreach( $unicode as $value ) $entities .= ( $value > 127 ) ? '&#' . $value . ';' : chr( $value );
		return $entities;
	}
}


?>