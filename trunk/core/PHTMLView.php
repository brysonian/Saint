<?php

class PHTMLView extends AbstractView
{
	
	function __construct($template) {
		parent::__construct($template);
	}
	
	function parse($layout_template=false) {
		parent::parse($layout_template);
				
		# unpack the props
		#extract($this->props);
		
		# trap the buffer
		ob_start();
		
		# include the template
		include $this->template;
		
		# get the buffer contents
		$parsed = ob_get_contents();
		
		# clean the buffer
		ob_clean();
		
		# if there is a layout
		if ($this->layout) {
			# find the type
			$type = ViewFactory::get_template_type($layout_template);
			
			if ($type) {
				# push the content into the layout
				$content_for_layout = $parsed;
			
				# include the template
				include $this->layout.".$type";
		
				# get the buffer contents
				$parsed = ob_get_contents();
			}
		}
		
		# close the output buffer
		ob_end_clean();
		
		# save the result
		$this->parsed = $parsed;
		return $parsed;
	}

}





// ===========================================================
// - HTML HELPERS
// ===========================================================
function xml_declaration() {
	return '<'.'?'.'xml version="1.0" encoding="utf-8"?'.">\n";
}

// ===========================================================
// - ASSET HELPERS
// ===========================================================
function javascript_path($file) {
	$base = ($file{0} == '/')?'':get_root().JAVASCRIPT_BASE.'/';	
	return "$base$file.js";
}

function javascript_include_tag() {
	$args = func_get_args();
	$out = '';
	if (empty($args)) {
		if (file_exists(DOC_ROOT.'/'.javascript_path('application')))
			$out .= "<script src=\"".javascript_path('application')."\" type=\"text/javascript\" /></script>\n";
			
		$out .= "<script src=\"".javascript_path('prototype')."\" type=\"text/javascript\" /></script>\n";		
	} else {
		foreach($args as $k => $v) {
			$out .= "<script src=\"".javascript_path($v)."\" type=\"text/javascript\" /></script>\n";
		}
	}
	return $out;
}

function stylesheet_path($file) {
	$base = ($file{0} == '/')?'':get_root().STYLESHEET_BASE.'/';	
	return "$base$file.css";
}

function stylesheet_link_tag($file, $media='screen') {
	return "<link href=\"".stylesheet_path($file)."\" media=\"$media\" rel=\"Stylesheet\" type=\"text/css\" />\n";
}

function media_path($file) {
	$base = ($file{0} == '/')?'':get_root().MEDIA_BASE.'/';	
	return "$base$file";
}

/*
	options can be a k=v array of image tag attributes
	if options === false, or options['alt']===false, the alt tag will be derived from the filename
	if options is a string, it will be used as the value for the alt tag
	if no alt tag is provided, the tag will have alt=''
	size should be provided as WidthxHeight, eg: 45x45
*/
function image_tag($file, $options=array()) {
	if (empty($file)) return;
	$out = "<img src=\"".media_path($file)."\"";
	if (is_string($options)) $options = array('alt'=>$options);
	if ($options === false)  $options = array('alt'=>false);
	if (!is_array($options))  $options = array();

	if (array_key_exists("alt", $options) && ($options['alt'] === false)) {
		$pi = pathinfo($file);
		$options['alt'] = human_name(str_replace('.'.$pi['extension'], '', $pi['basename']));
	}

	if (!array_key_exists("alt", $options)) $out .= ' alt=""';
	foreach($options as $k => $v) {
		if ($k == 'size') {
			$v = explode('x', $v);
			$out .= ' width="'.$v[0].'" height="'.$v[1].'" ';
		} else {
			$out .= " $k=\"$v\" ";
		}
	}
	
	$out .= " />\n";
	return $out;
}

function content_image_tag($file, $options=array()) {
	$base = ($file{0} == '/')?'':get_root().CONTENT_BASE.'/';	
	$file = $base.$file;
	return image_tag($file, $options);
}


// ===========================================================
// - FORM HELPERS
// ===========================================================
function text_field($obj, $name, $prop, $size=40, $maxlength=100, $value='') {
	$v = $obj?(is_object($obj)?$obj->$prop:$obj[$prop]):'';
	# try to determine if the value should be in ' or " since escaping doesn't seem to work.
	#$v = (substr_count($v, "'") > substr_count($v, '"'))?'"'.$v.'"':"'".$v."'";
	if ($v == false) $v = $value;
	$v = htmlspecialchars($v);
	return "<input type=\"text\" name=\"{$name}[{$prop}]\" value=\"$v\" id=\"{$name}_$prop\" size=\"$size\" maxlength=\"$maxlength\" />\n";
}

function text_area($obj, $name, $prop, $size=2000, $rows=false) {
	$v = $obj?(is_object($obj)?$obj->$prop:$obj[$prop]):'';
	if ($size < 256) {
		$rows = $rows?$rows:3;
		$ku = "onkeyup='if(this.value.length >= $size) this.value = this.value.substr(0,".($size-1).");'";
	} else {
		$rows = $rows?$rows:15;
		$ku = '';
	}

	return "<textarea name='{$name}[{$prop}]' id='{$name}_$prop' rows='$rows' cols='40'$ku>$v</textarea>\n";
}

function select($obj, $name, $prop, $collection, $key=false, $value=false, $options=array()) {
	$html = "<select ";
	if (array_key_exists("attributes", $options)) {
		foreach($options['attributes'] as $k => $v) {
			$html .= " $k=\"$v\"";
		}
	}

	$html .= "name='{$name}[{$prop}]' id='{$name}";
	if (!empty($prop)) $html .= "_$prop";
	$html .= "'>\n";
		
	# get selected value
	$default = false;
	if ($obj) {
		if (is_object($obj)) {
			$p = (strpos($prop, '_id') > 0)?str_replace('_id','',$prop):$prop;
			if ($obj->$p) $default = $obj->$p;
		} else {
			if ($obj[$p]) $default = $obj[$p];
		}
	}
	
	if ($default === false && array_key_exists('default', $options)) {
		$default = $options['default'];
	}
		
	if (is_object($default) and method_exists($default, 'get_id')) $default = $default->get_id();
	
	# add blank
	if (array_key_exists('include_blank', $options)) {
		$html .= "<option value=''></option>\n";
	}
	
	# add before items
	if (array_key_exists('before', $options)) {
		foreach($options['before'] as $item) {
			if (is_object($item)) {
				$v = ((!$value)?$item->__toString():$item->$value);
			} else if (is_array($item)) {
				$v = $item[$value];
			} else {
				$v = $item;
			}
			if ($key === false) {
				$k = $v;
			} else {
				$k = is_object($item)?$item->$key:$item[$key];
			}
			$sel = ($default !== false && $k == $default)?" selected='true'":'';
			$k = (substr_count($k, "'") > substr_count($k, '"'))?'"'.$k.'"':"'".$k."'";
			$html .= "<option$sel value=$k>$v</option>\n";
		}
	}
	
	foreach($collection as $item_key => $item) {
		if (is_object($item)) {
			$v = ((!$value)?$item->__toString():$item->$value);
		} else if (is_array($item)) {
			$v = $item[$value];
		} else {
			$v = $item;
		}
		if ($key === false) {
			$k = $v;
		} else if ($key === 'index') {
				$k = $item_key;
		} else {
			$k = is_object($item)?$item->$key:$item[$key];
		}
		$sel = ($default !== false && $k == $default)?" selected='true'":'';
		$k = (substr_count($k, "'") > substr_count($k, '"'))?'"'.$k.'"':"'".$k."'";
		$html .= "<option$sel value=$k>$v</option>\n";
	}

	# add after items
	if (array_key_exists('after', $options)) {
		foreach($options['after'] as $item) {
			if (is_object($item)) {
				$v = ((!$value)?$item->__toString():$item->$value);
			} else if (is_array($item)) {
				$v = $item[$value];
			} else {
				$v = $item;
			}
			if ($key === false) {
				$k = $v;
			} else {
				$k = is_object($item)?$item->$key:$item[$key];
			}
			$sel = ($default !== false && $k == $default)?" selected='true'":'';
			$k = (substr_count($k, "'") > substr_count($k, '"'))?'"'.$k.'"':"'".$k."'";
			$html .= "<option$sel value=$k>$v</option>\n";
		}
	}

	$html .= "</select>";
	return $html;
}

function file_select($obj, $name, $prop, $dir, $options=array(), $abs=false) {
	#if (!$abs) $dir = $_SERVER['DOCUMENT_ROOT'].$dir;
	$out = '';
	if (!file_exists($dir)) $dir = $_SERVER['DOCUMENT_ROOT'].$dir;
	if (file_exists($dir)) {
		$dir = new DirectoryIterator($dir);
		$out = array();
		foreach($dir as $file) {
			$fname = $file->getFilename();
			if (!$file->isDir() && $fname{0} != '.') {
				$pn = (!$abs)?str_replace($_SERVER['DOCUMENT_ROOT'], '', $file->getPathname()):$file->getPathname();
				$out[] = array('pathname' => $pn, 'path' => $file->getPath(), 'filename' => $file->getFilename());
			}
		}
		$out = select($obj, $name, $prop, $out, 'pathname', 'filename', $options);
	}
	
	# if upload add the input
	$upload = false;
	if (array_key_exists('upload',$options)) {
		$upload = $options['upload'];
		unset($options['upload']);
	}
	if ($upload) {
		$out .= " <input type='file' name='{$name}[{$prop}]' id='{$name}_{$prop}' /><input type='hidden' name='MAX_FILE_SIZE' value='300000000' />\n";
	}	
	return $out;
}

function file_upload($obj, $name, $prop, $preview=false) {
	$v = $obj?(is_object($obj)?$obj->$prop:$obj[$prop]):'';
	$v = ($preview)?"<div>$v</div>":''; 
	return "$v <div>Choose new File: <input type='file' name='{$name}[{$prop}]' id='{$name}_{$prop}' /><input type='hidden' name='MAX_FILE_SIZE' value='300000000' /></div>\n";
}


function date_select($obj, $name, $prop) {
	return date_field($obj, $name, $prop);
}


function date_field($obj, $name, $prop) {
	$v = $obj?(is_object($obj)?$obj->$prop:$obj[$prop]):'';
	if ($v == -1 || $v === false) $v = Format::prettyDate();
	$out = "<input type='text' name='{$name}[{$prop}]' value='$v' id='{$name}_$prop' size='40' maxlength='100' />\n";
	$now = Format::prettyShortDate();
	$out .= "<span class='tip'><a href='#' onclick='document.getElementById(\"{$name}_$prop\").value=\"$now\";return false;'>Insert Current Date</a></span>";
	return $out;
}

function datetime_field($obj, $name, $prop) {
	$v = $obj?(is_object($obj)?$obj->$prop:$obj[$prop]):'';
	if ($v == -1 || $v === false) $v = Format::prettyDateTime();
	$out = "<input type='text' name='{$name}[{$prop}]' value='$v' id='{$name}_$prop' size='40' maxlength='100' />\n";
	$now = Format::prettyShortDateTime();
	$out .= "<span class='tip'><a href='#' onclick='document.getElementById(\"{$name}_$prop\").value=\"$now\";return false;'>Insert Current Date and Time</a></span>";
	return $out;
}

function time_field($obj, $name, $prop) {
	$v = $obj?(is_object($obj)?$obj->$prop:$obj[$prop]):'';
	if ($v == -1 || $v === false) $v = Format::prettyTime();
	$out = "<input type='text' name='{$name}[{$prop}]' value='$v' id='{$name}_$prop' size='40' maxlength='100' />\n";
	$now = Format::prettyTime();
	$out .= "<span class='tip'><a href='#' onclick='document.getElementById(\"{$name}_$prop\").value=\"$now\";return false;'>Insert Current Time</a></span>";
	return $out;
}

function checkbox($obj, $name, $prop, $value=1) {
	$v = $obj?(is_object($obj)?$obj->$prop:$obj[$prop]):'';
	$out = "<input type='checkbox' value='$value' id='{$name}_{$prop}_box' name='{$name}[{$prop}]'";
	if ($value == $v)	$out .= " checked='checked' ";
	$out .= " />";
#	$out .= " onclick='document.getElementById(\"{$name}_$prop\").value = this.checked?this.value:0;' />";
#	$out .= "<input type='hidden' id='{$name}_$prop' name='{$name}[{$prop}]' value='".(($value == $v)?$value:0)."' />\n";
	return $out;
}

function radio($obj, $name, $prop, $values , $join_type= '<br />' , $join_type_li_class= '') {
	$v = $obj?(is_object($obj)?$obj->$prop:$obj[$prop]):'';
	if (!is_numeric($v) && empty($v)) $v = $values[0];
	$out = array();
	foreach($values as $k => $value) {
		if (is_array($value)) {
			$display_name = $value['name'];
			$value = $value['value'];
		} else {
			$display_name = $value;
		}
		$checked = ($value == $v) ? " checked='checked' " : '';
		$out[] = "<input type='radio' value='$value' id='{$name}_{$prop}' name='{$name}[{$prop}]'$checked/> $display_name" ;
	}

	if ( $join_type == 'li' ) {
		$out_2 = array();
		foreach ($out as $line) {
			$class = ($join_type_li_class == '') ? '' : " class='$join_type_li_class'";
			$out_2[] = "<li$class>$line</li>";
		}
		$out = join( "\n" , $out_2 );
	} else {
		$out = join($join_type, $out);
	}	
	return $out;
}



function hidden_field($obj, $name, $prop, $v=false) {
	if ($v == false) $v = $obj?(is_object($obj)?$obj->$prop:$obj[$prop]):'';
	# try to determine if the value should be in ' or " since escaping doesn't seem to work.
	$v = (substr_count($v, "'") > substr_count($v, '"'))?'"'.$v.'"':"'".$v."'";
	return "<input type='hidden' name='{$name}[{$prop}]' value=$v id='{$name}_$prop' />\n";
}


function submit_to($name, $confirm=false, $attributes=array()) {
	ob_start();
	echo "<input type='submit'";
	foreach($attributes as $k => $v) {
		echo " $k = \"$v\"";
	}
	if ($confirm) echo " onclick='return confirm(\"Are You Sure?\");' ";
	echo " value='";
	echo $name;
	echo "' />";
	return ob_get_clean();
}


function button_to($name, $loc='', $confirm=false, $options=array()) {
	ob_start();
	echo "<input type='button' onclick=\"";
	if ($confirm && array_key_exists("onclick", $options)) {
		echo 'if (confirm(\'Are You Sure?\')) {'.$options['onclick'].'} else {return false;}"';
		unset($options['onclick']);			
	} else {
		echo 'if (confirm(\'Are You Sure?\')) ';
		echo "document.location.href='$loc';\" ";
	}
	foreach($options as $k => $v) {
		echo " $k=\"$v\"";
	}
	echo " value='";
	echo $name;
	echo "' />";
	return ob_get_clean();
}

?>