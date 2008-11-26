<?php


class DBRecord implements Iterator, Serviceable, Countable
{
	protected $data = array();
	
	public $id;
	
	protected $table;
	protected $db;

	protected $to_one;
	protected $to_one_obj;
	protected $to_many;
	protected $to_many_obj;
	protected $habtm;

	protected $order;
	protected $where;
	protected $group;
	protected $limit;
	protected $valid;
	protected $key;
	protected $current;
	protected $fields = array();
	protected $include = array();
	protected $included = array();

	protected $loaded = false;
	protected $modified = true;

	protected $validator = array();
#	protected $acts_as;
#	protected	$acts_as_methods;
	
	protected static $table_info = array();

// ===========================================================
// - CONSTRUCTOR
// ===========================================================
	public function __construct($props=false) {
		# set table name
		$table = get_class($this);
		
		# turn camelback into underscore and lowercase
		$table = table_name($table);
		$this->set_table($table);
		
		#if (method_exists($this, 'init')) $this->init();
		
		# if props, add them all
		if (is_array($props)) {
			foreach($props as $k => $v) {
				$this->$k = $v;
			}
		}	else if ($props instanceof Params) {
			foreach($props as $k => $v) {
				$this->$k = $v;
			}
		}
	}





// ===========================================================
// - ACCESSORS
// ===========================================================
	// for id
	public function get_id()		{ return isset($this->id)?$this->id:false; }
	public function set_id($anId)	{
		if (!is_numeric($anId)  && $anId !== false) throw new InvalidId("$anId is not a valid id.");
		$this->id = $anId; 
	}

	public function get_fields()		{ return $this->fields; }

	// db
	public function db() {
		if (!$this->db) 
			$this->db = DBService::get_connection($this->get_service_id());
		return $this->db;
	}
	

	// for table
	public function get_table()		{ return $this->table; }
	public function set_table($t)	{ $this->table = $t; }
	
		
	public function is_empty($idcheck=false) {
		# see if there is anything in data
		$v = join('', $this->data);
		$v = empty($v);
		if ($idcheck) $v &= !$this->get_id();
		return $v;
	}
	
	// get
	public function get($prop) { return $this->__get($prop); }	
	public function __get($prop) {
		# allow id
		if ($prop == 'id') return $this->get_id();

		# first check in data
		if (isset($this->data[$prop])) {
			$val = $this->data[$prop];
			
			return $this->getters($prop, $val);
		}

		# then the to_one's
		# if the connection exists
		if ($this->has_relationship($prop, 'to-one')) {
			if (isset($this->to_one_obj[$prop])) {
				$this->to_one_obj[$prop]->include = $this->include;
				$this->to_one_obj[$prop]->load();
				return $this->getters($prop, $this->to_one_obj[$prop]);
			} else if (array_key_exists($prop.'_id', $this->data) && !empty($this->data[$prop.'_id'])) {
				$this->to_one_obj[$prop] = new $this->to_one[$prop]['class'];
				$this->to_one_obj[$prop]->set_id($this->data[$prop.'_id']);
				$this->to_one_obj[$prop]->include = $this->include;
				$this->to_one_obj[$prop]->load();
				return $this->getters($prop, $this->to_one_obj[$prop]);
			}
		}
		
		

		# then try the to_manys
		# if the connection exists
		if ($this->has_relationship($prop, 'to-many')) {
			# TODO: Maybe this array making can be replaced by a to_many collection class
			if (array_key_exists($prop, $this->to_many_obj) && !empty($this->to_many_obj[$prop])) {
				$out = array();
				foreach ($this->to_many_obj[$prop] as $obj) {
					$out[] = $obj;
				}
				return $this->getters($prop, $out);
				
			} else {
				// TODO: see about this include stuff

				# if it's actually a habtm, load accordingly
				if ($this->has_relationship($prop, 'habtm')) {
					$other_table = $this->habtm[$prop]['other_table'];
					$table = $this->habtm[$prop]['table'];
					$props = DBRecord::find_sql(
						"SELECT * from `$other_table` LEFT JOIN `$table` ON `$table`.{$other_table}_id=`$other_table`.id WHERE `$table`.".$this->get_table()."_id = '".$this->get_id()."'",
						array('class'=>$this->habtm[$prop]['class'], 'include'=>$this->include)						
					);						
				} else {
					$props = DBRecord::find_by($this->get_table().'_id', $this->get_id(), array('class'=>$this->to_many[$prop]['class'], 'include'=>$this->include));
				}
				
				# tmobj is indexed by id, until tmcollection
				// TODO: another point for a to-many collection
				$a = $props->to_a(false);
				$this->to_many_obj[$prop] = array();
				foreach($a as $k => $v) {
					$this->to_many_obj[$prop][$v->get_id()] = $v;
				}
				return $this->getters($prop, $a);
			}
		}
		
		return $this->getters($prop, false);
	}
	
	private function getters($prop, $val) {
		# if there is a get method with this name, call it and pass the value
		$meth = "get_{$prop}";
		if (method_exists($this, $meth)) $val = $this->$meth($val);
		return $val;
		/*
		if (is_array($this->getters)) {
			if (array_key_exists($prop, $this->getters)) {
				$method = "get_$prop";
				foreach($this->getters[$prop] as $k => $v) {
					$val = $this->acts_as[$v]->$method($val);
				}
			}
		
			if (array_key_exists('get', $this->getters)) {
				foreach($this->getters['get'] as $k => $v) {
					$val = $this->acts_as[$v]->get($prop, $val);
				}
			}
		}
		return $val;		
		*/
	}

	private function setters($prop, $val) {
		# if there is a set method with this name, call it and pass the value
		$meth = "set_{$prop}";
		if (method_exists($this, $meth)) $val = $this->$meth($val);
		return $val;
	}
	
	// set
	public function set($prop, $val) { $this->__set($prop, $val); }
	public function __set($prop, $val) {

		# allow id
		if ($prop == 'id') return $this->set_id($val);

		# make sure it isn't in the to-ones
		if (is_null($val)) {
			unset($this->data[$prop]);

		} else if ($this->has_relationship($prop, 'to-one') && is_object($val)) {
			if (!$val->get_id()) $val->save();
			$this->data[$prop.'_id'] = $val->get_id();
			$this->modified = true;

		} else {
			if (!array_key_exists($prop, $this->data)) {
				$this->data[$prop] = $this->setters($prop, $val);						
				$this->modified = true;


			} else if ($this->data[$prop] !== $val) {
				$this->data[$prop] = $this->setters($prop, $val);
				$this->modified = true;
			}
		}
	}







// ===========================================================
// - CRUD
// ===========================================================
	// callbacks
	protected function before_save() {}
	protected function before_create() {}
	protected function before_update() {}
	protected function before_validation() {}

	protected function after_save() {}
	protected function after_create() {}
	protected function after_update() {}
	protected function after_delete(){}

	// save to the db
	public function save($force=false) {
		if (!$this->modified && !$force) return;
		# if no id, then insert a new item, otherwise update
		if ($this->get_id()) {
			$this->update();
		} else {
			$this->create();
		}
		# save all the fields for this model
		$this->fields = array_keys($this->data);
		if (is_array($this->to_one)) $this->fields = array_merge($this->fields, array_keys($this->to_one));
		if (is_array($this->to_many)) $this->fields = array_merge($this->fields, array_keys($this->to_many));
		$this->after_save();
	}

	public function create() {
		# validate the data
		$this->validate_builtins();
		$validates = $this->validate();
		if ($this->validation_errors()) throw $this->validation_errors();
		if ($validates === false) return;

		$this->before_save();

		$this->before_create();

		$sql = "INSERT INTO `".$this->get_table()."` ";

		# generate values statement
		$values = array();
		$values['id'] = $this->get_id()?$this->get_id():'NULL';
				
		# add each key/val to the sql
		foreach ($this->data as $k=>$v) {
			if (is_null($v)) {
				$values[$k] = "NULL";
			} else if ((strpos($k, '_id') !== false) && empty($v)) {
				$values[$k] = "NULL";
			} else {
				$values[$k] = $this->escape_string($v);
			}
		}
		
		# touch updated_at/on and created_at/on
		# get table info
		$info = $this->table_info();
		foreach(array('created_on', 'created_at', 'updated_on', 'updated_at') as $v) {
			if (in_array($v, $info)) {
				$values[$v] = $this->now();
			}
		}
		
		$keys = array_keys($values);
		$sql .= "(`".join("`,`", $keys)."`) VALUES ('".join("','", $values)."')";
		$sql = str_replace("'NULL'", "NULL", $sql);

		$result = $this->db()->query($sql);
		if ($result) {
			$this->set_id($this->db()->insert_id());
		} else {
			if ($this->db()->errno() == DUPLICATE_ENTRY) {
				throw new DuplicateRecord($this->db()->error(), $this->db()->errno(), $sql);
			} else {
				throw new DBRecordError("Database error while attempting to create record.\n".$this->db()->error(), $this->db()->errno(), $sql);
			}
		}
		$this->after_create();

	}
	
	
	public function update($args=array()) {
		foreach($args as $k => $v) {
			$this->$k = $v;
		}

		# validate the data
		$this->validate_builtins();
		$validates = $this->validate();
		if ($this->validation_errors()) throw $this->validation_errors();
		if ($validates === false) return;

		$this->before_save();
		$this->before_update();

		$sql = "UPDATE `".$this->get_table()."` SET ";
		$props = array();
		/*
		$rel = false;
		foreach ($this->data as $k=>$v) {
			# see if there is a t-o or t-m
			if (strpos($k, '_') !== false && strpos($k, '_id') === false) {
				# check to-one
				if (is_array($this->to_one)) {
					foreach($this->to_one as $tkey => $tname) {
						if (strpos($k, $tkey.'_') === 0) {
							$rel = true;
							break;
						}
					}
				}
				if ($rel) continue;

				# check to-many
				if (is_array($this->to_many)) {
					foreach($this->to_many as $tkey => $tname) {
						if (strpos($k, $tkey.'_') === 0) {
							$rel = true;
							break;
						}						
					}
				}
				if ($rel) continue;
			}
			*/
		# get table info
		$info = $this->table_info();
		foreach ($this->data as $k=>$v) {

			# if the value is NULL then use that not empty string
			if (is_null($v)) {
				$props[] = "$k=NULL";
			# check of a foreign key, which needs to be NULL not ''
			} else if ((strpos($k, '_id') !== false) && empty($v)) {
				$props[] = "$k=NULL";
			} else {
				$props[] = "$k='".$this->escape_string($v)."'";
			}
		}	

		# touch updated_at/on and created_at/on
		if (in_array('updated_on', $info)) $props[] = "updated_on='".$this->now()."'";
		if (in_array('updated_at', $info)) $props[] = "updated_at='".$this->now()."'";
		
		$sql .= join(',',$props)." WHERE id=".$this->escape_string($this->get_id());		

		$result = $this->db()->query($sql);
		if (!$result) {
			throw new DBRecordError("Database error while attempting to update record.\n".$this->db()->error(), $this->db()->errno(), $sql);
		}
		$this->after_update();
	}



	public function delete() {
		if (!($this->get_id())) {
			throw new MissingIdentifier("You must define an id to delete an item.");			
		}
			
		$sql = "DELETE FROM `".$this->get_table()."` WHERE ";
		$sql .= "id=".$this->get_id();
		
		$result = $this->db()->query($sql);
		if (!$result) {
			throw new RecordDeletionError("Error deleting ".get_class($this).".\n".$this->db()->error(), $this->db()->errno(), $sql);
		}
		$this->after_delete();

	}

// ===========================================================
// - MIXINS
// ===========================================================
	public function __call($method, $args) {
		# validation
		if (strpos($method, 'validates_') !== false) {
			if (!$this->validator) $this->validator = new DBRecordValidator($this);
			// can't use variable functions since the args array needs to be expanded to args
			call_user_func_array(array($this->validator, $method), $args);

		} else if (strpos($method, 'add_') !== false) {
			$this->add_to_many_object(str_replace('add_', '', $method), $args);

		/*
		} else if (strpos($method, 'acts_as_') !== false) {
			if (!is_array($this->acts_as)) {
				$this->acts_as = array();
				$this->acts_as_methods = array();
			}
			#$cname = 'ActsAs'.class_name(str_replace('acts_as_', '', $method));
			$cname = class_name($method);
			
			# manually load the class
			if (file_exists(PROJECT_ROOT."/plugins/$cname/$cname.php")) {
				include_once PROJECT_ROOT."/plugins/$cname/$cname.php";
			} else {
				throw new PluginNotFound("Failed to load the $cname plugin. 
				Make sure the plugin is in your plugins/$cname directory and includes a $cname.php file.");
			}
			
			$class = new $cname($this);
			$this->acts_as[$cname]= &$class;

			$m = $class->method_list();
			foreach($m as $k => $v) {
				$this->acts_as_methods[$v] = $cname;
			}
			if (method_exists($class, 'getter_list')) {
				if (!is_array($this->getters)) $this->getters = array();
				$m = $class->getter_list();
				foreach($m as $k => $v) {
					if (!array_key_exists($v, $this->getters)) $this->getters[$v] = array();
					$this->getters[$v][] = $cname;
				}
			}
			
		} else if (is_array($this->acts_as_methods) && array_key_exists($method, $this->acts_as_methods)) {
			return call_user_func_array(array($this->acts_as[$this->acts_as_methods[$method]], $method), $args);
			
		*/
		} else {
			throw new UndefinedMethod(get_class($this).' does not have a method named '.$method.'().');
		}
		
	}

	
// ===========================================================
// - EXECUTE THE VALIDATION
// ===========================================================
	// validation errors is internal
	public function errors() {
		return $this->validation_errors();
	}

	protected function validation_errors() {
		if ($this->validator) return $this->validator->errors();
		return false;
	}

	public function add_error($name, $message, $code=0) {
		if (!$this->validator) $this->validator = new DBRecordValidator($this);
		return $this->validator->add_error($name, $message, $code);
	}
	
	# does the actual validation
	protected function validate_builtins() {
		$this->before_validation();
		if ($this->validator) $this->validator->validate($this->data);
		return true;
	}
	
	protected function validate() {
		
	}
	
	

// ===========================================================
// - PHP is retarded
// ===========================================================
// this is one way to find the class name
	static function get_class_name() {
		$db = debug_backtrace();
		# if the last item is a call_user_func call, then this is easy
		if ($db[2]['function'] == 'call_user_func') {
			return $db[2]['args'][0][0];
		}
		
		$i = 1;
		if (file_exists($db[$i]['file'])) {
			$file = file($db[$i]['file']);
		} else {
			throw new AmbiguousClass("Couldn't determine the correct class for this static call.\nTry specifying it as an argument.");			
		}
		$line = $db[$i]['line']-1;
		
		# if the args are on multiple lines, we need to account for that
		if (array_key_exists('args', $db[$i]) && is_array($db[$i]['args'])) {
			ob_start();
			array_walk_recursive ($db[$i]['args'], create_function('$v, $k', 'if (!is_array($v)) echo $v;'));
			$v = ob_get_clean();
			$line -= substr_count($v, "\n");
		}
		
		$c = array();
		preg_match('|([a-zA-Z0-9_]+)'.$db[$i]['type'].$db[$i]['function'].'.*|', $file[$line], $c);
		if (empty($c)) {
			throw new AmbiguousClass("Couldn't determine the correct class for this static call.\nTry specifying it as an argument.");
		}
		return $c[1];
	}


// ===========================================================
// - FIND
// ===========================================================
	// return an a single item by id
	public static function find($id, $options=array()) {
		$class = array_key_exists("class", $options)?$options['class']:self::get_class_name();
		$m = new $class;
		$m->set_id($id);
		$m->set_options($options);
		$m->load();
		return $m;

	}

	// return an array of all objects of this type
	public static function find_all($options=array()) {
		$class = array_key_exists("class", $options)?$options['class']:self::get_class_name();
		$m = new $class;
		$m->set_options($options);
		$sibs = new DBRecordCollection($m, $m->get_query(), $m->db());
		if (is_array($options) && array_key_exists('first', $options)) return $sibs->first();
		return $sibs;
	}
	
	// return an array of all objects using this where clause
	public static function find_where($where, $options=array()) {
		$class = array_key_exists("class", $options)?$options['class']:self::get_class_name();
		$m = new $class;
		$m->set_options($options);
		$m->set_where($where);
		$sibs = new DBRecordCollection($m, $m->get_query(), $m->db());
		if (is_array($options) && array_key_exists('first', $options)) return $sibs->first();
		return $sibs;
	}
	
	// handy shortcut to find_where for use on a specific field
	public static function find_by($field, $value, $options=array()) {
		$class = array_key_exists("class", $options)?$options['class']:self::get_class_name();
		$m = new $class;
		$m->set_options($options);
		
		# disambiguate the field 
		if (strpos($field, '.') === false) $field = '`'.$m->get_table()."`.$field";		
		$m->set_where("$field = '".$m->escape_string($value)."'");
		$sibs = new DBRecordCollection($m, $m->get_query(), $m->db());
		if (is_array($options) && array_key_exists('first', $options)) return $sibs->first();
		return $sibs;
	}

	// handy shortcut to find_where for use on a specific field
	public static function find_like_by($field, $value, $options=array()) {
		$class = array_key_exists("class", $options)?$options['class']:self::get_class_name();
		$m = new $class;
		$m->set_options($options);
		
		# disambiguate the field 
		if (strpos($field, '.') === false) $field = '`'.$m->get_table()."`.$field";
		$m->set_where("$field LIKE '".$m->escape_string($value)."'");

		$sibs = new DBRecordCollection($m, $m->get_query(), $m->db());
		if (is_array($options) && array_key_exists('first', $options)) return $sibs->first();
		return $sibs;
	}

	// handy shortcut to find_where for use on all searchable fields
	public static function find_by_all($value, $options=array()) {
		$class = array_key_exists("class", $options)?$options['class']:self::get_class_name();
		$m = new $class;
		$m->set_options($options);
		$o = $m->table_info();
		$sql = array();
		foreach($o as $k => $v) {
			if (strpos($v, 'id') !== false) continue;
			$sql[] = ' `'.$m->get_table().'`.'.$v." LIKE '".$m->escape_string($value)."'";
		}
		$m->set_where(join(' OR ', $sql));
		$sibs = new DBRecordCollection($m, $m->get_query(), $m->db());
		if (is_array($options) && array_key_exists('first', $options)) return $sibs->first();
		return $sibs;
	}
		

	// return an array of all objects using this query
	public static function find_sql($sql, $options=array()) {
		$class = array_key_exists("class", $options)?$options['class']:self::get_class_name();
		$m = new $class;
		$m->set_options($options);
		$sibs = new DBRecordCollection($m, $sql, $m->db());
		if (is_array($options) && array_key_exists('first', $options)) return $sibs->first();
		return $sibs;
	}


	// sets query options on an object based on options array
	public function set_options($options=array()) {
		if (is_array($options) && array_key_exists('first', $options)) $this->set_limit('1');
		if (is_array($options) && array_key_exists('order', $options)) $this->set_order($options['order']);
		if (is_array($options) && array_key_exists('group', $options)) $this->set_group($options['group']);
		if (is_array($options) && array_key_exists('limit', $options)) $this->set_limit($options['limit']);
		if (is_array($options) && array_key_exists('include', $options)) {
			if ($options['include'] == 'all') {
				$this->include = array_merge($this->include, array_keys($this->to_one));
				$this->include = array_merge($this->include, array_keys($this->to_many));
			} else if ($options['include'] == 'to-one') {
				$this->include = array_merge($this->include, array_keys($this->to_one));
			} else if ($options['include'] == 'to-many') {
				$this->include = array_merge($this->include, array_keys($this->to_many));
			} else {
				if (is_array($options['include'])) {
					foreach($options['include'] as $v) {
						$this->include[] = table_name($v);
					}
				} else {
					$this->include[]  = table_name($options['include']);
				}
				#$this->include = is_array($options['include'])?$options['include']:array($options['include']);
			}
		}
	}



// ===========================================================
// - LOAD FROM DB
// ===========================================================
	function reload() {
		$this->loaded = false;
		$this->modified = false;
		$this->load();
	}

	// load item from the db using id
	function load() {
		if ($this->loaded && !$this->modified) return;

		# start where clause if there isn't one
		$where = $this->get_where();
		$where .= $this->get_where()?' AND ':' ';
		
		# if ID, use that in where
		# if neither one, error
		if ($this->get_id()) {
			$where .= '`'.$this->get_table().'`.id='.$this->get_id();
		} else {
			throw new MissingIdentifier("You must define an id to load an object.");
		}

		# set the where clause
		$this->set_where($where);

		# get the query
		$sql = $this->get_query();
				
		# run the query
		$result = $this->db()->query($sql);

		# process results
		if (!$result) {
			throw new DBRecordError("Error loading ".get_class($this).".\n".$this->db()->error(), 0, $sql);
		} else {
			if ($row = $result->fetch_assoc()) {				
				do {
					$this->process_row($row);
				} while ($row = $result->fetch_assoc());
				$result->free();
			} else {
				throw new RecordNotFound('Nothing found with an id of '.$this->get_id().'.');
			}
		}
		$this->loaded = true;
		$this->modified = false;
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
		$result = $this->db()->query($sql);

		# process results
		if (!$result) {
			throw new DBRecordError("Query Failed .\n".$this->db()->error(), $this->db()->errno(), $sql);
		} else if ($result !== true) {
			$result->free();
		}
		return true;
	}
	
	public function table_info($table=false, $full=false) {
		if ($table === false) $table = $this->get_table();
		if ($full) return $this->db()->table_info($table, true);
		
		if (!array_key_exists($table, DBRecord::$table_info)) {
			DBRecord::$table_info[$table] = $this->db()->table_info($table, false);
		}
		return DBRecord::$table_info[$table];
	}
	
// ===========================================================
// - GET THE QUERY FOR THIS OBJECT
// ===========================================================
	// get the query for this obj
	public function get_query() {
		# make query
		$sql = 'SELECT '.$this->get_select();

		# ADD FROM
		$sql .= " FROM `".$this->get_table()."` ";

		# ADD JOINS
		$sql .= $this->get_joins();

		# add WHERE clause
		if ($this->get_where()) $sql .= " WHERE ".$this->get_where();
		
		# add group by if there is one
		if ($this->get_group()) $sql .= " GROUP BY ".$this->get_group();

		# add order by if there is one
		if ($this->get_order()) $sql .= " ORDER BY ".$this->get_order();

		# add order by if there is one
		if ($this->get_limit()) $sql .= " LIMIT ".$this->get_limit();
		
		#die ($sql);
		
		return $sql;
	}
	
	protected function get_select() {
		$sql = "`".$this->get_table()."`.*";

		# add all included props
		foreach($this->include as $k => $v) {
			# only count ones with relationships
			$type = $this->has_relationship($v, 'all', true);
			if ($type) {
				switch ($type) {
					case 'to-one':
						$table = $this->to_one[$v]['table'];
						break;

					case 'to-many':
						$table = table_name($this->to_many[$v]['class']);
						break;

					case 'habtm':
						$table = table_name($this->habtm[$v]['class']);
						break;
										
				}

				$info = $this->table_info($table);
				foreach ($info as $colorder=>$col) {
					# skip columns that have the table name in them
					if (strpos($col, $this->get_table().'_') !== false) continue;
					$sql .= ','.$table.'.'.$col. ' as '.$v.'_'.$col;					
				}
			}
		}

		return $sql;
	}
	
	protected function get_joins() {
		$sql = '';

		# add all included props
		foreach($this->include as $k => $v) {

			$type = $this->has_relationship($v, 'all', true);
			if ($type) {
				switch ($type) {	
					case 'to-one':
						$table = $this->to_one[$v]['table'];
						$sql .= " LEFT JOIN `{$table}` ON {$table}.id = `".$this->get_table()."`.{$table}_id ";
						break;

					case 'habtm':
						$table = $this->habtm[$v]['table'];
						$other_table = $this->habtm[$v]['other_table'];
						$sql .= " LEFT JOIN `$table` ON `".$table."`.".$this->get_table()."_id = `".$this->get_table()."`.id ";
						$sql .= " LEFT JOIN `".$other_table."` ON `".$table."`.".$other_table."_id = `$other_table`.id ";
						break;
					

					# to-many
					case 'to-many':
						$table = $this->to_many[$v]['table'];
						$sql .= " LEFT JOIN `$table` ON `$table`.".$this->get_table()."_id = `".$this->get_table()."`.id ";
						break;
				}
			}
		}
		return $sql;
	}

	// query params
	public function get_order()		{ return $this->order; }
	public function set_order($t)	{ $this->order = $t;}
            
	public function get_where()		{ return $this->where; }
	public function set_where($t)	{ $this->where = $t; }
	         
	public function get_group()		{ return $this->group; }
	public function set_group($t)	{ $this->group = $t; }
	         
	public function get_limit()		{ return $this->limit; }
	public function set_limit($t)	{ $this->limit = $t; }



// ===========================================================
// - PROCESS A ROW INTO OBJECTS
// ===========================================================
	public function process_row($row) {
		# skip cols with tablename_id
		$skipme = $this->get_table().'_id';

		# set props (loop columns)
		foreach ($row as $k=>$v) {
			if ($k == $skipme) continue;
			
			# figure out if a particular result belongs to a to-one or to-many
			# the key will have to have a _ in it for this to be the case, so
			# skip keys without one straight out
			$to = false;
			$tm = false;

			# see if there is a t-o or t-m
			if (strpos($k, '_') !== false) {

				# check to-one
				if (is_array($this->to_one)) {
					foreach($this->to_one as $tkey => $tname) {
						if ((strpos($k, $tkey.'_') === 0) && array_key_exists($tkey.'_id', $row) && !empty($row[$tkey.'_id'])) {
							$to = $tkey;
							break;
						}
					}
				}

				# check to-many
				if (!$to && is_array($this->to_many)) {
					foreach($this->to_many as $tkey => $tname) {
						#if (strpos($k, $tkey.'_') === 0) {
						if (strpos($k, $tkey.'_') === 0 && array_key_exists($tkey.'_id', $row) && !empty($row[$tkey.'_id'])) {
							$tm = $tkey;
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
					$this->to_one_obj[$to] = new $this->to_one[$to]['class'];
					
					# if it's included, mark as loaded
					if (in_array($to, $this->include)) $this->to_one_obj[$to]->loaded = true;
				}
				if ($prop == 'id') {
					if (!empty($v)) $this->to_one_obj[$to]->set_id($v);
				} else {
					$this->to_one_obj[$to]->$prop = stripslashes($v);
					$this->to_one_obj[$to]->modified = false;
				}
	
			# to_many
			} else if ($tm !== false) {
				if (!is_null($v) && array_key_exists("{$tm}_id", $row)) {

					$tm_index = $row[$tm.'_id'];

					# if the obj doesn't exist yet, make it
					# objs are in the to_many_obj[name] array indexed by id
					if (!isset($this->to_many_obj[$tm][$tm_index])) {
						$this->to_many_obj[$tm][$tm_index] = new $this->to_many[$tm]['class'];
					}
				
					# remove the prefix from the prop names
					$prop = str_replace($tm.'_', '', $k);
				
					if ($prop == 'id') {
						$this->to_many_obj[$tm][$tm_index]->set_id($v);
					} else {
						$this->to_many_obj[$tm][$tm_index]->$prop = stripslashes($v);
						$this->to_many_obj[$tm][$tm_index]->modified = false;
					}
				}
			# normal
			} else {
				$this->$k = stripslashes($v);
			}
		}
		# save all the fields for this model
		$this->fields = array_keys($this->data);
		if (is_array($this->to_one)) $this->fields = array_merge($this->fields, array_keys($this->to_one));
		if (is_array($this->to_many)) $this->fields = array_merge($this->fields, array_keys($this->to_many));
	}


// ===========================================================
// - ADD TO-MANY AND TO-ONE RELATIONSHIPS
// ===========================================================
	public function has_relationship($property, $type='all', $return_type=false) {
		switch ($type) {
			case 'to-one':
				return (is_array($this->to_one) && array_key_exists($property, $this->to_one));
			
			case 'habtm':
				return (is_array($this->habtm) && array_key_exists($property, $this->habtm));

			case 'to-many':
				return (is_array($this->to_many) && array_key_exists($property, $this->to_many));
						
			case 'all':
				if ($return_type) {
					if (is_array($this->to_one) && array_key_exists($property, $this->to_one)) return 'to-one';
					if (is_array($this->habtm) && array_key_exists($property, $this->habtm)) return 'habtm';
					if (is_array($this->to_many) && array_key_exists($property, $this->to_many)) return 'to-many';
				} else {
					return (is_array($this->to_one) && array_key_exists($property, $this->to_one)) ||
								(is_array($this->to_many) && array_key_exists($property, $this->to_many)) ||
								(is_array($this->habtm) && array_key_exists($property, $this->habtm));
				}
		}
		return false;
	}

	function has_one($class, $options=false) {
		# parse options
		$table = (is_array($options) && array_key_exists("table", $options))?$options['table']:table_name($class);
		$propname = (is_array($options) && array_key_exists("propname", $options))?($options['propname']):(is_string($options)?$options:$table);

		# if to_ones are empty make an array
		if (empty($this->to_one)) {
			$this->to_one = array();
			$this->to_one_obj = array();
		}
		$this->to_one[$propname] = array('table'=>$table, 'class'=>$class);
	}

	function has_many($class, $options=false) {
		# parse options
		$table = (is_array($options) && array_key_exists("table", $options))?$options['table']:table_name($class);
		$propname = (is_array($options) && array_key_exists("propname", $options))?($options['propname']):(is_string($options)?$options:table_name($class));

		# if to_many are empty make an array
		if (empty($this->to_many)) {
			$this->to_many = array();
			$this->to_many_obj	= array();
		}
		
		$this->to_many[$propname] = array('table'=>$table, 'class'=>$class);

		# create the obj
		$this->to_many_obj[$propname] = array();
		
	}

	function has_and_belongs_to_many($class, $options=false) {
		$table = (is_array($options) && array_key_exists("table", $options))?$options['table']:false;
		$other_table = (is_array($options) && array_key_exists("other_table", $options))?$options['other_table']:table_name($class);
		$propname = (is_array($options) && array_key_exists("propname", $options))?($options['propname']):(is_string($options)?$options:table_name($class));

		# if no table, try to get the tablename
		if ($table == false) {
			$tables = array(
				$other_table,
				$this->get_table()
			);
			sort($tables);
			$table = $tables[0].'_'.$tables[1];
		}

		if (empty($this->habtm)) $this->habtm = array();
		$this->habtm[$propname] = array('table'=>$table, 'class'=>$class, 'other_table'=>$other_table);

		if (!is_array($options)) $options = array();
		$options['table'] = $table;
		$options['propname'] = $propname;
		$this->has_many($class, $options);
	}

	protected function add_to_many_object($class, $value) {
		$table = table_name($class);
		
		# search in habtm
		#if (is_array($this->habtm) && array_key_exists($table, $this->habtm)) {
		if ($this->has_relationship($table, 'habtm')) {
			$table = $this->habtm[$table]['table'];
			foreach($value as $k => $v) {
				$this->exec("INSERT INTO $table (".$this->get_table()."_id, ".$v->get_table()."_id) VALUES('".$this->get_id()."', '".$v->get_id()."')");
			}
		} else if ($this->has_relationship($table, 'to-many')) {
			$class = $this->to_many[$table]['class'];
			$idprop = $this->get_table().'_id';
			$id = $this->get_id();
			foreach($value as $k => $v) {
				$v->$idprop = $id;
				$v->save();
			}
		}
	}

// ===========================================================
// - ITERATOR INTERFACE
// ===========================================================
	function rewind() {
		$this->key = 0;
		$k = $this->key();
		$this->current = $this->$k;
		$this->valid = true;
	}
	
	function valid() {
		return $this->valid;
	}

	function key() {
		return $this->fields[$this->key];
	}
	
	function current() {
		return $this->current;
	}
	
	function next() {
		$this->key++;
		if ($this->key >= count($this->fields)) {
			$this->current = false;
			$this->valid = false;
			return;
		}
		$k = $this->key();
		$this->current = $this->$k;
	}

// ===========================================================
// - COUNTABLE INTERFACE
// ===========================================================
	public function count() {
		return count($this->fields);
	}



// ===========================================================
// - REQUIRED FOR THE SERVICEABLE INTERFACE
// ===========================================================
	public function get_service_id() {
		return 'DBRecord';
	}
	
	
// ===========================================================
// - ESCAPE FOR DB
// ===========================================================
	function escape_string($v) {
		if (is_numeric($v)) return $v;
		if (!is_string($v)) return $v;
		return $this->db()->escape_string($this->utf8_to_entities($v));
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

	
	// handy function for getting current date
	function now() {
		return date("Y-m-d H:i:s");
	}
	
// ===========================================================
// - REPRESENTATIONS
// ===========================================================
	// get xml rep of this object
	function to_xml($include=array(), $usecdata=false, $obj=false, $skip_to_many=false) {
		if ($include == 'all') {
			$include = array_keys($this->to_one);
			$include = array_merge($include, array_keys($this->to_many));
		} else if ($include == 'to-one') {
			$include = array_keys($this->to_one);
		} else if ($include == 'to-many') {
			$include = array_keys($this->to_many);
		} else {
			$include = is_array($include)?$include:array($include);
		}


		# make doc and root
		$dom = new DomDocument;
		$root = $dom->createElement($this->get_table());
		$root = $dom->appendChild($root);
		
		# add id
		if ($this->get_id()) $root->setAttribute('id', $this->get_id());

		# add node for each prop
		foreach ($this->data as $k=>$v) {
			$node = $dom->createElement($k);
			if ($usecdata && ((strpos($v, '<')!==false) || (strpos($v, '>')!==false) || (strpos($v, '&')!==false))) {
				$cdata = $dom->createCDATASection($v);
			} else {
				$cdata = $dom->createTextNode($v);
			}
			$node->appendChild($cdata);

			$node = $root->appendChild($node);
		}


		if (!empty($include)) {
			foreach($include as $k => $v) {
				if ($rel = $this->has_relationship($v, 'all', true)) {
					if ($rel == 'to-many' && $skip_to_many) continue;
					$p = $this->$v;
					if(is_array($p)) {
						$list = $dom->createElement($v);
						$list = $root->appendChild($list);
						foreach($p as $k2 => $v2) {
							$child = $dom->importNode($v2->to_xml($include, $usecdata, true, true)->documentElement, true);
							$child = $list->appendChild($child);
						}
					} else {
						$obj = $this->$v;
						$child = $dom->importNode($obj->to_xml($include, $usecdata, true, true)->documentElement, true);
						$child = $root->appendChild($child);
					}	
				}
			}
		}
		return ($obj)?$dom:$dom->saveXML();
	}


	// get array rep of this object
	function to_a($include=array(), $skip_to_many=false) { return $this->to_array($include, $skip_to_many); }
	function to_array($include=array(), $skip_to_many=false) {
		if ($include == 'all') {
			$include = array_keys($this->to_one);
			$include = array_merge($include, array_keys($this->to_many));
		} else if ($include == 'to-one') {
			$include = array_keys($this->to_one);
		} else if ($include == 'to-many') {
			$include = array_keys($this->to_many);
		} else {
			$include = is_array($include)?$include:array($include);
		}

		$out = array();

		# add id
		$out['id'] = $this->get_id();
		
		# add each prop
		foreach ($this->data as $k=>$v) {
			$out[$k] = $v;
		}
		
		if (!empty($include)) {
			foreach($include as $k => $v) {
				if ($rel = $this->has_relationship($v, 'all', true)) {
					if ($rel == 'to-many' && $skip_to_many) continue;
					$p = $this->$v;
					if(is_array($p)) {
						$out[$v] = array();
						foreach($p as $k2 => $v2) {
							$out[$v][] = $v2->to_array($include, true);
						}
					} else {
						$out[$v] = $p->to_array($include, true);
					}	
				}
			}
		}
		return $out;
	}

	function to_json($include=array(), $skip_to_many=false) {
		if ($include == 'all') {
			$include = array_keys($this->to_one);
			$include = array_merge($include, array_keys($this->to_many));
		} else if ($include == 'to-one') {
			$include = array_keys($this->to_one);
		} else if ($include == 'to-many') {
			$include = array_keys($this->to_many);
		} else {
			$include = is_array($include)?$include:array($include);
		}

		$out = array();

		# add id
		$out[] = 'id: "'.$this->get_id().'"';
		
		# add each prop
		foreach ($this->data as $k=>$v) {
			$out[] = "$k: \"".str_replace(
				array('"', chr(0x08),chr(0x09),chr(0x0A),chr(0x0C),chr(0x0D)),
				array('\"', '\b', '\t', '\n', '\f', '\r'),
				$v).'"';
		}

		if (!empty($include)) {
			foreach($include as $k => $v) {
				if ($rel = $this->has_relationship($v, 'all', true)) {
					if ($rel == 'to-many' && $skip_to_many) continue;
					$p = $this->$v;
					if(is_array($p)) {
						$j = array();
						foreach($p as $k2 => $v2) {
							$j[] = $v2->to_json($include, true);
						}
						$out[] = "$v: [\n".join(",\n", $j)."\n]";
					} else {
						$out[] = "$v: ".$p->to_json($include, true);
					}	
				}
			}
		}
		return "{\n".join(",\n",$out)."\n}";
	}
	
	function to_s() { return $this->__toString(); }
	function to_string() { return $this->__toString(); }
	function __toString() {
		if ($this->title) return $this->title;
		if ($this->name) return $this->name;
		if ($this->label) return $this->label;
		if ($this->get_id()) return ''.$this->get_id();
		
		# if there isn't any data for this object, return an empty string
		if (empty($this->fields)) return '';
		
		# otherwise return something somewhat useful
		return str_replace('Object id ', '', 'Instance '.$this.' of class '.get_class($this));
	}

	// function to_url() {
	// 	return url_for($this->to_param());
	// }
	// 
	function to_param() {
		return array(
			'controller' => url_name(get_class($this)),
			'action' => 'show',
			'id' => $this->get_id()
		);
	}
}

// ===========================================================
// - SOME THINGS NEED TO BE EASIER TO GET TO
// ===========================================================
// TODO: reinstate this!
function is_id($val=false) {
	if (!DBRecord::is_valid_id($val)) return false;
	return true;
}



// ===========================================================
// - EXCEPTIONS
// ===========================================================
class InvalidId extends SaintException {}
class MissingIdentifier extends SaintException {}
class RecordNotFound extends SaintException {}
class AmbiguousClass extends SaintException {}
class RecordDeletionError extends DBRecordError {}
class DuplicateRecord extends DBRecordError {}
class ReadOnlyAccess extends SaintException {}
class UndefinedMethod extends SaintException {}
class PluginNotFound extends SaintException {}


// ===========================================================
// - ERROR CODES
// ===========================================================
define('DUPLICATE_ENTRY', 1062);

// for validation
define('VALIDATION_ERROR',			2000);
define('VALIDATION_EMPTY',			2001);
define('VALIDATION_NUMERIC',		2002);
define('VALIDATION_DATE',				2003);
define('VALIDATION_UNIQUE',			2004);
define('VALIDATION_FORMAT',			2005);
define('VALIDATION_EMAIL',			2006);
define('VALIDATION_CURSE',			2007);
define('VALIDATION_URL',				2008);
define('VALIDATION_FALSITY',		2009);
define('VALIDATION_TRUTH',			2010);



?>