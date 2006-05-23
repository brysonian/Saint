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

	protected $validate_presence_of			= array();
	protected $validate_numericality_of	= array();
	protected $validate_date_of					= array();
	protected $validate_uniqueness_of		= array();
	protected $validate_format_of				= array();
	protected $validate_politeness_of		= array();
	
	protected $errors = array();

// ===========================================================
// - CONSTRUCTOR
// ===========================================================
	function DBRecord($props=false) {
		# set table name
		$table = get_class($this);
		
		# turn camelback into underscore and lowercase
		$table = $this->get_table_from_classname($table);
		$this->set_table($table);
		
		# get a ref to the dbconnection
		$this->db = DBService::get_connection();

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
	
	// get
	function get($prop) { return $this->__get($prop); }	
	function __get($prop) {
		# first check in data
		if (isset($this->data[$prop])) return $this->data[$prop];
		
		# then the to_one's
		if (isset($this->to_one_obj[$prop])) {
			if ($this->to_one_obj[$prop]->does_have_one() || $this->to_one_obj[$prop]->does_have_many()) {
				if ($this->to_one_obj[$prop]->get_uid()) $this->to_one_obj[$prop]->load();
			}
			return $this->to_one_obj[$prop];
		}

		# then try the to_manys
		$tm = $this->get_to_many_objects($prop);

		# if their are some, see if they have to_many and to_one's of their own
		# and if so load them up
		if ($tm) {
			foreach ($tm as $obj) {
				if ($obj->does_have_one() || $obj->does_have_many()) {
					if ($obj->get_uid()) $obj->load();
				}
			}
			return $tm;
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
	function does_have_many() {
		if (is_array($this->to_many) && !empty($this->to_many)) {
			return true;
		}
		return false;
	}

	function does_have_one() {
		if (is_array($this->to_one) && !empty($this->to_one)) {
			return true;
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
		if (!empty($this->errors)) {
			$db = debug_backtrace();
			throw new ValidationException($this->errors, get_class($this), VALIDATION_ERROR, $db[1]['file'], $db[1]['line']);
		}


		$sql = "INSERT INTO `".$this->get_table()."` ";

		# generate values statement
		$values = array();
		$values['id'] = 'NULL';
		$values['uid'] = $this->gen_uid();
				
		# clear the errors
		$this->errors = array();
		
		# add each key/val to the sql
		foreach ($this->data as $k=>$v) {
			$values[$k] = $this->escape_string($v);
		}
		
		$keys = array_keys($values);
		$sql .= "(`".join("`,`", $keys)."`) VALUES ('".join("','", $values)."')";

		$result = $this->db->query($sql);
		if ($result) {
			$this->set_id($this->db->insert_id());
		} else {
			if ($this->db->errno() == DUPLICATE_ENTRY) {
				throw(new DBDuplicateException($this->db->error(), $this->db->errno(), $sql));
			} else {
				throw(new DBException("Database error while attempting to create record.\n".$this->db->error(), $this->db->errno(), $sql));
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
		if (!empty($this->errors)) {
			$db = debug_backtrace();
			throw new ValidationException($this->errors, get_class($this), VALIDATION_ERROR, $db[1]['file'], $db[1]['line']);
		}

		$sql = "UPDATE `".$this->get_table()."` SET ";
		$props = array();
		foreach ($this->data as $k=>$v) {
			if (!empty($v)) {
				$props[] = "$k='".$this->escape_string($v)."'";
			}
		}	
		
		$sql .= join(',',$props)." WHERE id=".$this->escape_string($this->get_id());		

		$result = $this->db->query($sql);
		if (!$result) {
			throw(new DBException("Database error while attempting to update record.\n".$this->db->error(), $this->db->errno(), $sql));
		}
	}



	function delete() {
		if (!($this->get_id() || $this->get_uid())) {
			throw(new SaintException("You must define an ID or UID to delete an item", 666));			
		}
			
		$sql = "DELETE FROM `".$this->get_table()."` WHERE ";
		$sql .= $this->get_id()?"id=".$this->get_id():"uid = '".$this->get_uid()."'";
		
		$result = $this->db->query($sql);
		if (!$result) {
			throw(new DBException("Error deleting ".__CLASS__.".\n".$this->db->error(), $this->db->errno(), $sql));
		}
		
	}

// ===========================================================
// - VALIDATION OPERATIONS
// ===========================================================
	# the general form of the validation adding methods,
	# shared by most of them
	public function validates_x_of(&$type, $args, $code) {
		if ($args === false) {
			$type = array();
			return;
		}
		
		# add the error message if there isn't one
		if (!array_key_exists('message', $args)) {
			$args['message'] = get_error_message($code, '');
		}
		
		# add each prop as a key to the array
		foreach($args as $k => $v) {
			if (is_numeric($k)) {
				$type[$v] = $args['message'];
			}
		}
	}

	public function validates_presence_of($args) {
		if (!is_array($args) && $args !== false) $args = func_get_args();
		$this->validates_x_of($this->validate_presence_of, $args, VALIDATION_EMPTY);
	}

	protected function test_presence_of($prop, $msg) {
		if (array_key_exists($prop, $this->data) && !empty($this->data[$prop]))
			return true;

		$this->add_error($prop, VALIDATION_EMPTY, $msg);
		return false;
	}
	

	
	public function validates_numericality_of($args) {
		if (!is_array($args) && $args !== false) $args = func_get_args();
		$this->validates_x_of($this->validate_numericality_of, $args, VALIDATION_NUMERIC);
	}
	
	protected function test_numericality_of($prop, $msg) {
		if (!array_key_exists($prop, $this->data)) return true;

		if (is_numeric($this->data[$prop]))
			return true;

		$this->add_error($prop, VALIDATION_NUMERIC, $msg);
		return false;
	}
	

	

	public function validates_date_of($args) {
		if (!is_array($args) && $args !== false) $args = func_get_args();
		$this->validates_x_of($this->validate_date_of, $args, VALIDATION_DATE);
	}

	protected function test_date_of($prop, $msg) {
		if (!array_key_exists($prop, $this->data)) return true;
		if (empty($this->data[$prop])) return true;
		$d = strtotime($this->data[$prop]);
		if ($d === false || $d == -1) {
			$this->add_error($prop, VALIDATION_DATE, $msg);
			return false;
		}
		$this->data[$prop] = Format::mysqlDateTime($this->data[$prop]);
		return true;
	}


	
	public function validates_uniqueness_of($args) {
		if (!is_array($args) && $args !== false) $args = func_get_args();
		$this->validates_x_of($this->validate_uniqueness_of, $args, VALIDATION_UNIQUE);
	}

	protected function test_uniqueness_of($prop, $msg) {
		if (!array_key_exists($prop, $this->data)) return true;

		$c = get_class($this);
		$where = "$prop = '".$this->escape_string($this->data[$prop])."'";
		if ($this->get_id()) $where .= " AND ".$this->get_table().".id <> ".$this->get_id();
		
		$r = self::find_where($where, $c);
		if ($r->num_rows() > 0) {
			$this->add_error($prop, VALIDATION_UNIQUE, $msg);
			return false;
		}
		return true;
	}



	public function validates_email_of($prop, $message=false) {
		$this->validates_format_of($prop, '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/', get_error_message(VALIDATION_EMAIL, $prop));
	}
	
	public function validates_url_of($prop, $message=false) {
		$this->validates_format_of($prop, 
			'/(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/xis',
			get_error_message(VALIDATION_URL, $prop));
	}
	
	public function validates_format_of($prop, $format, $message=false) {
		if ($prop === false) {
			$this->validate_format_of = array();
		} else {
			# add the error message if there isn't one
			$message = $message?$message:get_error_message(VALIDATION_FORMAT, $prop);

			$this->validate_format_of[$prop] = array($format, $message);
		}
	}

	protected function test_format_of($prop, $args) {
		if (!array_key_exists($prop, $this->data)) return true;
		if (empty($this->data[$prop])) return true;
		if (preg_match($args[0], $this->data[$prop]) == 0) {
			$this->add_error($prop, VALIDATION_FORMAT, $args[1]);
			return false;
		}
		return true;
	}


	public function validates_politeness_of($args) {
		if (!is_array($args) && $args !== false) $args = func_get_args();
		$this->validates_x_of($this->validate_politeness_of, $args, VALIDATION_CURSE);
	}

	protected function test_politeness_of($prop, $msg) {
		if (!array_key_exists($prop, $this->data)) return true;
		$badwords = array("pis","piss","breasts","bastard","bastard","b*stard","f*cking","phuck","kike","fuck","f*ck","fuc","fu*k","fuc*","f**k","f_ck","f__k","f@ck","fu??","fa??","f*??","f'cking","fuckin'","shit","sh|t","sh!t","sh*t","shit's","shitty","nigger","n*gger","n-gger","n_gger","darky","darkies","asshole","cunt","c*nt","c_nt","c/nt,","c-nt","pussy","p*ssy","fucker","slut","sl*t","dickhead","d*ck","f*cker","n*gg*r","clit","prick","faggot","f*gg*t","b*tch","wh*re","f@ggot","tw@t","goddamn","godamn","d@mn","gnikcuf","motherfucker","dickhead","blowjob","cocksucker","c*ck","c*sucker","c*cks*cker","asswipe","assmunch","fucking","fucked","feck","whatafucking","fags","fag","fag","fag","fags","fags","shitting","shits","chink","buttsex","shithole","bunghole","butthole","bullshit","bullshitter","bullshiter","bullshiters","assman","shit","bullshit","assfucker","tit","tits","cuntrag","bitch","bitchie","bitchy","whore","motherfucker","vagina");
		foreach ($badwords as $word) {
			if (stripos(strtolower($this->data[$prop]), $word) !== false) {
				$this->add_error($prop, VALIDATION_CURSE, $msg);
				return false;
			}
		}
		return true;
	}
	

	
// ===========================================================
// - EXECUTE THE VALIDATION
// ===========================================================
	public function errors() {
		if (!empty($this->errors)) 
			return new ValidationException($this->errors, get_class($this));
	}

	protected function add_error($name, $code, $message) {
		$this->errors[] = array($name, $code, $message);
	}
	
	# does the actual validation
	protected function validate_builtins() {
		# check all the validation lists and make sure each one is ok
		$noErr = true;
		
		# presence
		foreach($this->validate_presence_of as $prop => $msg) {
			$noErr = $this->test_presence_of($prop, $msg);
		}
		
		# numericality
		foreach($this->validate_numericality_of as $prop => $msg) {
			$noErr = $this->test_numericality_of($prop, $msg);
		}

		# date
		foreach($this->validate_date_of as $prop => $msg) {
			$noErr = $this->test_date_of($prop, $msg);
		}
		
		# uniqueness
		foreach($this->validate_uniqueness_of as $prop => $msg) {
			$noErr = $this->test_uniqueness_of($prop, $msg);
		}
		
		# format
		foreach($this->validate_format_of as $prop => $args) {
			$noErr = $this->test_format_of($prop, $args);
		}
		
		# curse words
		foreach($this->validate_politeness_of as $prop => $args) {
			$noErr = $this->test_politeness_of($prop, $args);
		}
		

		return $noErr;
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
		$c = array();
		preg_match('|([a-zA-Z0-9_]+)'.$db[$i]['type'].$db[$i]['function'].'.*|', $file[($db[$i]['line']-1)], $c);
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
		$m->set_where("`$field` = '".$this->escape_string($value)."'");
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
		$result = $this->db->query($sql);

		# process results
		if (!$result) {
			throw(new DBException("Error loading ".__CLASS__.".\n".$this->db->error(), 0, $sql));
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
		$result = $this->db->query($sql);

		# process results
		if (!$result) {
			throw(new DBException("Query Failed .\n".$this->db->error(), $this->db->errno(), $sql));
		} else if ($result !== true) {
			$result->free();
		}
		return true;
	}
	
	function table_info() {
		return $this->db->table_info($this->get_table(), true);
	}

	// get the query for this obj
	function get_query() {
		# make query
		$sql = "SELECT `".$this->get_table()."`.*";

		# add to_one
		if (!empty($this->to_one)) {
			# loop through to_one's
			foreach ($this->to_one as $v) {
				$info = $this->db->table_info($v);
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
				$info = $this->db->table_info($v);
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
	function has_one($class, $table=false) {
		# if no table, try to get the tablename
		if ($table == false) $table = $this->get_table_from_classname($class);

		# if to_ones are empty make an array
		if (empty($this->to_one)) {
			$this->to_one = array();
			$this->to_one_obj = array();
		}
		$this->to_one[] = $table;
		
		# create the obj
		$cname = ucfirst($table);
		$this->to_one_obj[$table] = new $cname;
	}

	function has_many($class, $table=false) {
		# if no table, try to get the tablename
		if ($table == false) $table = $this->get_table_from_classname($class);

		# if to_many are empty make an array
		if (empty($this->to_many)) {
			$this->to_many = array();
			$this->to_many_obj	= array();
			$this->to_many_class	= array();
		}
		$this->to_many[] = $table;

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
			$result = $this->db->query($sql);

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
		return $this->db->escape_string($this->utf8_to_entities($v));
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
}

?>