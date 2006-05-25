<?php


class DBRecord implements Iterator, Serviceable
{
	protected $data = array();
	
	public $id;
	public $uid;
	
	protected $table;
	protected $db;

	protected $to_one;
	protected $to_one_obj;
	protected $to_many;
	protected $to_many_obj;
	protected $to_many_class;
	protected $habtm;

	protected $order;
	protected $where;
	protected $group;
	protected $limit;
	protected $valid;
	protected $key;
	protected $current;
	protected $fields;

	protected $loaded = false;

	protected $validator = array();

// ===========================================================
// - CONSTRUCTOR
// ===========================================================
	function DBRecord($props=false) {
		# set table name
		$table = get_class($this);
		
		# turn camelback into underscore and lowercase
		$table = $this->get_table_from_classname($table);
		$this->set_table($table);
	
		if (method_exists($this, 'init')) $this->init();
		
		# if props, add them all
		if (is_array($props)) {
			foreach($props as $k => $v) {
				$this->$k = $v;
			}
		}
	}





// ===========================================================
// - ACCESSORS
// ===========================================================
	// for id
	function get_id()		{ return isset($this->id)?$this->id:false; }
	function set_id($anId)	{ 
		if (!is_numeric($anId)  && $anId !== false) throw(new SaintException("Invalid ID.", 0));
		$this->id = $anId; 
	}

	// for uid
	function get_uid()		{ return isset($this->uid)?$this->uid:false; }
	function set_uid($aUid)	{
		if (strlen($aUid) != 32 && $aUid !== false) throw(new SaintException("Invalid UID.", 0));
		$this->uid = $aUid; 
	}

	// for table
	function get_table()		{ return $this->table; }
	function set_table($t)	{ $this->table = $t; }
	
	// query params
	function get_order()		{ return $this->order; }
	function set_order($t)	{ $this->order = $t;}
            
	function get_where()		{ return $this->where; }
	function set_where($t)	{ $this->where = $t; }
	         
	function get_group()		{ return $this->group; }
	function set_group($t)	{ $this->group = $t; }
	         
	function get_limit()		{ return $this->limit; }
	function set_limit($t)	{ $this->limit = $t; }
	
	function get_db() {
		# get a ref to the dbconnection
		if (!$this->get_db()) $this->db = DBService::get_connection();
		return $this->db;
	}
	
	// get
	function get($prop) { return $this->__get($prop); }	
	function __get($prop) {
		# first check in data
		if (isset($this->data[$prop])) return $this->data[$prop];
		
		# then the to_one's
		# if the connection exists
		if (!empty($this->to_one) && array_key_exists($prop, $this->to_one)) {
			# if the obj exists return it
			$prop = $this->to_one[$prop];
			if (isset($this->to_one_obj[$prop])) return $this->to_one_obj[$prop];
			
			# no object exists for this, so if we have a uid for one, load it up
			if (!array_key_exists($prop.'_uid', $this->data)) {
				$this->load();
				return $this->to_one_obj[$prop];
			}

			if ($this->data[$prop.'_uid']) {
				$cname = $this->get_classname_from_table($prop);
				$this->to_one_obj[$prop] = new $cname;
				$this->to_one_obj[$prop]->set_uid($this->data[$prop.'_uid']);
				$this->to_one_obj[$prop]->load();
				return $this->to_one_obj[$prop];
			}
		}

		# then try the to_manys
		# if the connection exists
		if (!empty($this->to_many) && array_key_exists($prop, $this->to_many)) {
 			$tm = $this->get_to_many_objects($this->to_many[$prop]);
				
			# if there are some, see if they have to_many and to_one's of their own
			# and if so load them up
			if (!$tm && !$this->loaded) {
				$this->load();
				$tm = $this->get_to_many_objects($this->to_many[$prop]);
			}
			
			# TODO: Maybe this can be replaced by the to_many collection class
			if ($tm) {
				$out = array();
				foreach ($tm as $obj) {
					$out[] = $obj;
				}
				return $out;
			}
		}
		
		return false;
	}
	
	// set
	function set($prop, $val) { $this->__set($prop, $val); }
	function __set($prop, $val) {
		if (is_null($val)) {
			unset($this->data[$prop]);
		} else {
			$this->data[$prop] = $val;
		}
		
		# reset loaded because things have changed
		$this->loaded = false;
	}

	// get a list of to_many object
	function get_to_many_objects($prop) {
		# check if it's there
		if (isset($this->to_many_obj[$prop])) {
			return $this->to_many_obj[$prop];
		}
		return false;
	}

	// see if this object has to many or to one associations
	// exclude the passed class
	function does_have_many($not_class=false) {
		if (is_array($this->to_many) && !empty($this->to_many)) {
			$c = 0;
			foreach($this->to_many as $k => $v) {
				if ($v != $not_class) $c++;
			}
			if ($c > 0)	return true;
		}
		return false;
	}

	function does_have_one($not_class=false) {
		if (is_array($this->to_one) && !empty($this->to_one)) {
			$c = 0;
			foreach($this->to_one as $k => $v) {
				if (strtolower($v) != strtolower($not_class)) $c++;
			}
			if ($c > 0)	return true;
		}
		return false;
	}







// ===========================================================
// - CRUD
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
		# validate the data
		$this->validate_builtins();
		$this->validate();
		if ($this->errors()) {
			$db = debug_backtrace();
			throw new ValidationException($this->errors()->errors, get_class($this), VALIDATION_ERROR, $db[1]['file'], $db[1]['line']);
		}


		$sql = "INSERT INTO `".$this->get_table()."` ";

		# generate values statement
		$values = array();
		$values['id'] = 'NULL';
		$values['uid'] = $this->gen_uid();
				
		# add each key/val to the sql
		foreach ($this->data as $k=>$v) {
			$values[$k] = $this->escape_string($v);
		}
		
		$keys = array_keys($values);
		$sql .= "(`".join("`,`", $keys)."`) VALUES ('".join("','", $values)."')";

		$result = $this->get_db()->query($sql);
		if ($result) {
			$this->set_id($this->get_db()->insert_id());
		} else {
			if ($this->get_db()->errno() == DUPLICATE_ENTRY) {
				throw(new DBDuplicateException($this->get_db()->error(), $this->get_db()->errno(), $sql));
			} else {
				throw(new DBException("Database error while attempting to create record.\n".$this->get_db()->error(), $this->get_db()->errno(), $sql));
			}
		}
	}
	
	
	function update($args) {
		foreach($args as $k => $v) {
			$this->$k = $v;
		}
		
		# validate the data
		$this->validate_builtins();
		$this->validate();
		if ($this->errors()) {
			$db = debug_backtrace();
			throw new ValidationException($this->errors()->errors, get_class($this), VALIDATION_ERROR, $db[1]['file'], $db[1]['line']);
		}

		$sql = "UPDATE `".$this->get_table()."` SET ";
		$props = array();
		foreach ($this->data as $k=>$v) {
			if ((strpos($k, '_uid') !== false) && empty($v)) continue;
			$props[] = "$k='".$this->escape_string($v)."'";
		}	
		
		$sql .= join(',',$props)." WHERE id=".$this->escape_string($this->get_id());		

		$result = $this->get_db()->query($sql);
		if (!$result) {
			throw(new DBException("Database error while attempting to update record.\n".$this->get_db()->error(), $this->get_db()->errno(), $sql));
		}
	}



	function delete() {
		if (!($this->get_id() || $this->get_uid())) {
			throw(new SaintException("You must define an ID or UID to delete an item", 666));			
		}
			
		$sql = "DELETE FROM `".$this->get_table()."` WHERE ";
		$sql .= $this->get_id()?"id=".$this->get_id():"uid = '".$this->get_uid()."'";
		
		$result = $this->get_db()->query($sql);
		if (!$result) {
			throw(new DBException("Error deleting ".__CLASS__.".\n".$this->get_db()->error(), $this->get_db()->errno(), $sql));
		}
		
	}

// ===========================================================
// - MIXINS
// ===========================================================
	function __call($method, $args) {
		# validation
		if (strpos($method, 'validates_') !== false) {
			if (!$this->validator) $this->validator = new DBRecordValidator($this);
			call_user_func_array(array($this->validator, $method), $args);
		}
	}

	
// ===========================================================
// - EXECUTE THE VALIDATION
// ===========================================================
	public function errors() {
		if ($this->validator) return $this->validator->errors();
		return false;
	}

	protected function add_error($name, $code, $message) {
		return $this->validator->add_error($name, $code, $message);
	}
	
	# does the actual validation
	protected function validate_builtins() {
		if ($this->validator) $this->validator->validate($this->data);
		return true;
	}
	
	protected function validate() {
		
	}
	
	

// ===========================================================
// - PHP is retarded
// ===========================================================
// this is one way to find the class name
	static function get_class_from_backtrace() {
		$db = debug_backtrace();
		$i = 1;
		$file = file($db[$i]['file']);
		$line = $db[$i]['line']-1;
		
		# if the args are on multiple lines, we need to account for that
		if (array_key_exists('args', $db[$i]) && is_array($db[$i]['args'])) {
			foreach($db[$i]['args'] as $k => $v) {
				$line -= substr_count($v, "\n");
			}
		}
		
		$c = array();
		preg_match('|([a-zA-Z0-9_]+)'.$db[$i]['type'].$db[$i]['function'].'.*|', $file[$line], $c);
		if (empty($c)) {
			throw new SaintException("DBRecord couldn't figure out the correct class for this static call.\nTry specifying it as a string for the last argument of the method.", 0);
		}
		return $c[1];
	}


// ===========================================================
// - FIND
// ===========================================================
	// return an a single item by uid
	static function find($uid, $options=array(), $class=false) {
		$class = $class?$class:self::get_class_from_backtrace();
		$m = new $class;
		$m->set_uid($uid);
		$m->load();
		return $m;
	}

	// return an array of all objects of this type
	static function find_all($options=array(), $class=false) {
		$class = $class?$class:self::get_class_from_backtrace();
		$m = new $class;
		if (array_key_exists('order', $options)) $m->set_order($options['order']);
		$sibs = new DBRecordIterator($m, $m->get_query(), $m->db);
		return $sibs;
	}
	
	// return an array of all objects using this where clause
	static function find_where($where, $options=array(), $class=false) {
		$class = $class?$class:self::get_class_from_backtrace();
		$m = new $class;
		if (array_key_exists('order', $options)) $m->set_order($options['order']);
		$m->set_where($where);
		$sibs = new DBRecordIterator($m, $m->get_query(), $m->db);
		return $sibs;
	}
	
	// handy shortcut to find_where for use on a specific field
	static function find_by($field, $value, $options=array(), $class=false) {
		$class = $class?$class:self::get_class_from_backtrace();
		$m = new $class;
		if (array_key_exists('order', $options)) $m->set_order($options['order']);
		$m->set_where("`$field` = '".$m->escape_string($value)."'");
		$sibs = new DBRecordIterator($m, $m->get_query(), $m->db);
		return $sibs;
	}
		
	// find an item by id
	static function find_id($id, $options=array(), $class=false) {
		$class = $class?$class:self::get_class_from_backtrace();
		$m = new $class;
		$m->set_id($id);
		$m->load();
		return $m;
	}

	// return an array of all objects using this query
	static function find_sql($sql, $class=false) {
		$class = $class?$class:self::get_class_from_backtrace();
		$m = new $class;
		$sibs = new DBRecordIterator($m, $sql, $m->db);
		return $sibs;
	}






// ===========================================================
// - LOAD FROM DB
// ===========================================================
	// load item from the db using id
	function load() {
		if ($this->loaded) return;
		
		# start where clause if there isn't one
		$where = $this->get_where()?' AND ':' WHERE ';
		
		# if ID, use that in where, otherwise try UID
		# if neither one, error
		if ($this->get_id()) {
			$where .= '`'.$this->get_table().'`.id='.$this->get_id();
		} else if ($this->get_uid()) {
			$where .= '`'.$this->get_table()."`.uid='".$this->get_uid()."'";
		} else {
			throw(new SaintException("You must define a ID or UID to load an object.", 0));
		}
		
		# get the query
		$sql = $this->get_query();
		
		
		# add the where clause
		$sql .= $where;
		
		# run the query
		$result = $this->get_db()->query($sql);

		# process results
		if (!$result) {
			throw(new DBException("Error loading ".__CLASS__.".\n".$this->get_db()->error(), 0, $sql));
		} else {
			if ($row = $result->fetch_assoc()) {				
				do {
					$this->process_row($row);
				} while ($row = $result->fetch_assoc());
				$result->free();
			} else {
				throw(new SaintException("Nothing found with id: ".$this->get_id()." or uid: ".$this->get_uid().".\n", 0));
			}
		}
		$this->loaded = true;
	}
	
	// ===========================================================
	// - RESET ALL DATA PROPS
	// ===========================================================
	function reset() {
		if (is_array($this->to_one_obj)) {
			foreach($this->to_one_obj as $k=>$v) {
				$this->to_one_obj[$k]->reset();	
			}
		}
		
		if (is_array($this->to_many_obj)) {
			foreach($this->to_many_obj as $k=>$v) {
				$this->to_many_obj[$k] = array();
			}
		}
		$this->data = array();
		$this->fields = array();
	}
	
	
	// run arbitrary sql without processing
	function exec($sql) {
		# run the query
		$result = $this->get_db()->query($sql);

		# process results
		if (!$result) {
			throw(new DBException("Query Failed .\n".$this->get_db()->error(), $this->get_db()->errno(), $sql));
		} else if ($result !== true) {
			$result->free();
		}
		return true;
	}
	
	function table_info() {
		return $this->get_db()->table_info($this->get_table(), true);
	}

	// get the query for this obj
	function get_query() {
		# make query
		$sql = "SELECT `".$this->get_table()."`.*";

		# add to_one
		if (!empty($this->to_one)) {
			# loop through to_one's
			foreach ($this->to_one as $v) {
				$info = $this->get_db()->table_info($v);
				foreach ($info['order'] as $col => $order) {
					# skip columns that have the table name in them
					if (strpos($col, $this->get_table().'_') !== false) continue;
					$sql .= ','.$v.'.'.$col. ' as '.$v.'_'.$col;					
				}
			}
		}
		# add to_many
		if (!empty($this->to_many)) {
			# loop through to_many's
			foreach ($this->to_many as $v) {
				$info = $this->get_db()->table_info($v);
				foreach ($info['order'] as $col => $order) {
					# skip columns that have the table name in them
					if (strpos($col, $this->get_table().'_') !== false) continue;
					$sql .= ','.$v.'.'.$col. ' as '.$v.'_'.$col;					
				}
			}
		}

		# add from
		$sql .= " FROM `".$this->get_table()."` ";

		# join to_one
		if (!empty($this->to_one)) {
			foreach ($this->to_one as $v) {
				$sql .= " LEFT JOIN `{$v}` ON {$v}.uid = `".$this->get_table()."`.{$v}_uid ";				
			}
		}

		# join to_many
		if (!empty($this->to_many)) {
			# loop through to_many's
			foreach ($this->to_many as $v) {
				# see if this is actually a habtm, then add the extra join
				if (is_array($this->habtm) && array_key_exists($v, $this->habtm)){
					$sql .= " LEFT JOIN `".$this->habtm[$v]."` ON `".$this->habtm[$v]."`.".$this->get_table()."_uid = `".$this->get_table()."`.uid ";
					$sql .= " LEFT JOIN `$v` ON `".$this->habtm[$v]."`.".$v."_uid = `$v`.uid ";
				} else {
					$sql .= " LEFT JOIN `$v` ON `$v`.".$this->get_table()."_uid = `".$this->get_table()."`.uid ";
				}
			}
		}

		# add WHERE clause
		if ($this->get_where()) $sql .= " WHERE ".$this->get_where();
		
		# add group by if there is one
		if ($this->get_group()) $sql .= " GROUP BY ".$this->get_group();

		# add order by if there is one
		if ($this->get_order()) $sql .= " ORDER BY ".$this->get_order();

		# add order by if there is one
		if ($this->get_limit()) $sql .= " LIMIT ".$this->get_limit();

		return $sql;
	}


	// process a row
	function process_row($row) {
		# skip cols with tablename_id
		$skipme = $this->get_table().'_id';

		# set props (loop columns)
		foreach ($row as $k=>$v) {
			if ($k == $skipme) continue;
			
			# currently it's all eager loading,
			# figure out if a particular result belongs to a to-one or to-many
			# the key will have to have a _ in it for this to be the case, so
			# skip keys without one straight out
			$to = false;
			$tm = false;

			# see if there is a t-o or t-m
			if (strpos($k, '_') !== false) {

				# check to-one
				if (is_array($this->to_one)) {
					foreach($this->to_one as $tname) {
						if (strpos($k, $tname) !== false) $to = $tname;
					}
				}

				# check to-many
				if (!$to && is_array($this->to_many)) {
					foreach($this->to_many as $tname) {
						if (strpos($k, $tname) !== false) {
							$tm = $tname;
							break;
						}						
					}
				}
			}
			
			# to_one
			if ($to !== false) {
				# remove the prefix from the prop names
				$prop = str_replace($to.'_', '', $k);
				# if the object doesn't exist, make it
				if (!array_key_exists($to, $this->to_one_obj)) {
					$cname = $this->get_classname_from_table($to);
					$this->to_one_obj[$to] = new $cname;
				}
				if ($prop == 'id') {
					if (!empty($v)) $this->to_one_obj[$to]->set_id($v);
				} else if ($prop == 'uid') {
					if (!empty($v)) $this->to_one_obj[$to]->set_uid($v);
				} else {
					$this->to_one_obj[$to]->$prop = stripslashes($v);
				}
	
			# to_many
			} else if ($tm !== false) {

				# skip ones without a uid
				if (empty($row[$tm.'_uid'])) continue;
				$tm_index = $row[$tm.'_uid'];

				# if the obj doesn't exist yet, make it
				# objs are in the to_many_obj[name] array indexed by uid
				if (!isset($this->to_many_obj[$tm][$tm_index])) {
					$cname = $this->to_many_class[$tm];
					$this->to_many_obj[$tm][$tm_index] = new $cname;
				}
				
				# remove the prefix from the prop names
				$prop = str_replace($tm.'_', '', $k);

				if ($prop == 'id') {
					$this->to_many_obj[$tm][$tm_index]->set_id($v);
				} else if ($prop == 'uid') {
					$this->to_many_obj[$tm][$tm_index]->set_uid($v);
				} else {
					$this->to_many_obj[$tm][$tm_index]->$prop = stripslashes($v);
				}
			# normal
			} else {
				$this->$k = stripslashes($v);
			}
		}
		# save all the fields for this model
		$this->fields = array_keys($this->data);
		if (is_array($this->to_one_obj)) $this->fields = array_merge($this->fields, array_keys($this->to_one_obj));
		if (is_array($this->to_many_obj)) $this->fields = array_merge($this->fields, array_keys($this->to_many_obj));
	}


// ===========================================================
// - ADD TO-MANY AND TO-ONE RELATIONSHIPS
// ===========================================================
	function has_one($class, $propname=false, $table=false) {
		# if no table, try to get the tablename
		if ($table == false) $table = $this->get_table_from_classname($class);

		# if to_ones are empty make an array
		if (empty($this->to_one)) {
			$this->to_one = array();
			$this->to_one_obj = array();
		}
		if ($propname === false) {
			$this->to_one[$table] = $table;
		} else {
			$this->to_one[$propname] = $table;
		}
	}

	function has_many($class, $propname=false, $table=false) {
		# if no table, try to get the tablename
		if ($table == false) $table = $this->get_table_from_classname($class);

		# if to_many are empty make an array
		if (empty($this->to_many)) {
			$this->to_many = array();
			$this->to_many_obj	= array();
			$this->to_many_class	= array();
		}
		if ($propname === false) {
			$this->to_many[$table] = $table;
		} else {
			$this->to_many[$propname] = $table;
		}

		# create the obj
		$this->to_many_obj[$table] = array();
		$this->to_many_class[$table] = $class;		
	}

	function has_and_belongs_to_many($class, $table=false) {
		# if no table, try to get the tablename
		if ($table == false) {
			$tables = array(
				$this->get_table_from_classname($class),
				$this->get_table()
			);
			sort($tables);
		}

		if (empty($this->habtm)) $this->habtm = array();
		$this->habtm[$this->get_table_from_classname($class)] = $tables[0].'_'.$tables[1];
		$this->has_many($class);		
	}




// ===========================================================
// - UTILITIES
// ===========================================================
	// initalize UID
	function gen_uid() {
		//make sure this UID isn't taken already
		do {
			$uid = md5(uniqid(rand(), true));
			$sql = "SELECT uid from `".$this->get_table()."` WHERE uid='$uid'";
			$result = $this->get_db()->query($sql);

			# if nothing is found, break the loop
			if ($result->num_rows() == 0) break;
		} while(true);
		$this->set_uid($uid);
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
// - ITERATOR INTERFACE
// ===========================================================
	function rewind() {
		reset($this->fields);
		$k = $this->key = current($this->fields);
		$this->current = $this->$k;
		$this->valid = true;
	}
	
	function valid() {
		return $this->valid;
	}

	function key() {
		return $this->key;
	}
	
	function current() {
		return $this->current;
	}
	
	function next() {
		$ok = next($this->fields);
		$k = $this->key = current($this->fields);
		$this->current = $this->$k;
		if ($ok === false) $this->valid = false;
	}



// ===========================================================
// - REQUIRED FOR THE SERVICEABLE INTERFACE
// ===========================================================
	static public function get_service_id() {
		return 'DBRecord';
	}
	
	
// ===========================================================
// - ESCAPE FOR DB
// ===========================================================
	function escape_string($v) {
		return $this->get_db()->escape_string($this->utf8_to_entities($v));
	}

	function utf8_to_entities($str) {
		$unicode = array();
		$values = array();
		$looking_for = 1;
		for ($i = 0; $i < strlen( $str ); $i++ ) {
			$this_value = ord( $str[ $i ] );
			if ( $this_value < 128 ) $unicode[] = $this_value;
			else {
				if ( count( $values ) == 0 ) $looking_for = ( $this_value < 224 ) ? 2 : 3;
				$values[] = $this_value;
				if ( count( $values ) == $looking_for ) {
					$number = ( $looking_for == 3 ) ? ( ( $values[0] % 16 ) * 4096 ) + ( ( $values[1] % 64 ) * 64 ) + ( $values[2] % 64 ):( ( $values[0] % 32 ) * 64 ) + ( $values[1] % 64 );
					$unicode[] = $number;
					$values = array();
					$looking_for = 1;
				}
			}
		}
		$entities = '';
		foreach( $unicode as $value ) $entities .= ( $value > 127 ) ? '&#' . $value . ';' : chr( $value );
		return $entities;
	}


// ===========================================================
// - REPRESENTATIONS
// ===========================================================
	// get xml rep of this object
	function to_xml($str=false) {
		# make doc and root
		$dom = new DomDocument;
		$root = $dom->createElement($this->get_table());
		$root = $dom->appendChild($root);
		
		# add id and uid
		if ($this->get_id()) $root->setAttribute('id', $this->get_id());
		if ($this->get_uid()) $root->setAttribute('uid', $this->get_uid());

		# add node for each prop
		foreach ($this->data as $k=>$v) {
			$node = $dom->createElement($k);
			if (is_numeric($v)) {
				$cdata = $dom->createTextNode($v);
			} else {
				$cdata = $dom->createCDATASection($v);
			}
			$node->appendChild($cdata);

			$node = $root->appendChild($node);
		}
					
		# add nodes for each to_one
		if (!empty($this->to_one_obj)) {
			foreach ($this->to_one_obj as $k=>$v) {
				$node = $dom->importNode($v->to_xml()->documentElement, true);
				$node = $root->appendChild($node);
			}
		}
					
		# add nodes for each to_many
		if (!empty($this->to_many_obj)) {
			foreach ($this->to_many_obj as $k=>$v) {
				# add node for chirren
				$list = $dom->createElement('to-many');
				$list->setAttribute('name', $k);
				$list = $root->appendChild($list);

				# add items				
				foreach ($this->get_to_many_objects($k) as $obj) {
					$node = $dom->importNode($obj->to_xml()->documentElement, true);
					$node = $list->appendChild($node);
				}
			}
		}
		return ($str)?$dom->saveXML():$dom;
	}


	// get array rep of this object
	function to_array($deep=false) {
		$out = array();

		# add id and uid
		$out['id'] = $this->get_id();
		$out['uid'] = $this->get_uid();
		
		if ($deep) {
			foreach ($this as $k=>$v) {
				if (is_array($v) && $deep) {
					$out[$k] = array();
					foreach ($v as $k2=>$v2) {
						$out[$k][$k2] = $v2->to_array(false);
					}
				} else if (is_object($v) && $deep) {
					$out[$k] = $v->to_array(true);
				} else {
					$out[$k] = $v;
				}
			}

		} else {
			# add each prop
			foreach ($this->data as $k=>$v) {
				$out[$k] = $v;
			}

			# add each to_one
			if (!empty($this->to_one_obj)) {
				foreach ($this->to_one_obj as $k=>$v) {
					# get the array rep and loop through it, adding each prop
					# and prepending the table name
					$a = $v->to_array(true);
					foreach ($a as $a_k => $a_v) {
						$out[$k."_$a_k"] = $a_v;
					}
				}
			}

			# add each to_many
			if (!empty($this->to_many_obj)) {
				foreach ($this->to_many_obj as $k=>$v) {
					# add array for chirren
					$out[$k] = array();

					# add items				
					foreach ($this->get_to_many_objects($k) as $obj) {
						$out[$k][] = $obj->to_array(false);
					}
				}
			}
		}
		
		return $out;
	}
	
	function to_string() { return $this->__toString(); }
	function __toString() {
		if ($this->title) return $this->title;
		if ($this->name) return $this->name;
		if ($this->label) return $this->label;
		if ($this->get_uid()) return $this->get_uid();
		
		# if there isn't any data for this object, return an empty string
		if (empty($this->fields)) return '';
		
		# otherwise return something somewhat useful
		return str_replace('Object id ', '', 'Instance '.$this.' of class '.get_class($this));
	}

	function to_url() {
		return url_for(array(
			'controller' => strtolower(get_class($this)),
			'action' => 'show',
			'uid' => $this->get_uid()
		));
	}

}

?>