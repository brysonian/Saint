<?php

class PHTMLView extends ViewCore
{
	
	function PHTMLView($template) {
		parent::ViewCore($template);
	}
	
	function parse($layout_template=false) {
		parent::parse($layout_template);
				
		# unpack the props
		extract($this->props);
		
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
			# validate it
			$templateinfo = ViewFactory::template_info($layout_template);
			
			# push the content into the layout
			$content_for_layout = $parsed;
			
			# include the template
			include $templateinfo['file'];
		
			# get the buffer contents
			$parsed = ob_get_contents();
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
	return '<'.'?'.'xml version="1.0" encoding="utf-8"?'.'>';
}

function text_field($obj, $name, $prop) {
	$v = $obj?(is_object($obj)?$obj->$prop:$obj[$prop]):'';
	# try to determine if the value should be in ' or " since escaping doesn't seem to work.
	$v = (substr_count($v, "'") > substr_count($v, '"'))?'"'.$v.'"':"'".$v."'";
	return "<input type='text' name='{$name}[{$prop}]' value=$v id='{$name}_$prop' size='40' maxlength='100' />\n";
}

function text_area($obj, $name, $prop, $size=2000) {
	$v = $obj?(is_object($obj)?$obj->$prop:$obj[$prop]):'';
	if ($size < 256) {
		$rows = 3;
		$ku = "onkeyup='if(this.value.length >= $size) this.value = this.value.substr(0,".($size-1).");'";
	} else {
		$rows = 15;
		$ku = '';
	}

	return "<textarea name='{$name}[{$prop}]' id='{$name}_$prop' rows='$rows' cols='40'$ku>$v</textarea>\n";
}

function select($obj, $name, $prop, $collection, $key, $value, $options=array()) {
	$html = "<select name='{$name}[{$prop}]' id='{$name}_$prop'>\n";
		
	# get selected value
	$default = false;
	if ($obj) {
		if (is_object($obj)) {
			$p = (strpos($prop, '_uid') > 0)?str_replace('_uid','',$prop):$prop;
			if ($obj->$p) $default = $obj->$p;
		} else {
			if ($obj[$p]) $default = $obj[$p];
		}
	}
	
	if ($default === false && array_key_exists('default', $options)) {
		$default = $options['default'];
	}
	
	if (is_object($default)) $default = $default->get_uid();

	# add blank
	if (array_key_exists('include_blank', $options)) {
		$html .= "<option value=''></option>\n";
	}
	
	# add before items
	if (array_key_exists('before', $options)) {
		foreach($options['before'] as $item) {
			$v = is_object($item)?((!$value)?$item->__toString():$item->$value):$item[$value];
			$k = is_object($item)?$item->$key:$item[$key];
			$sel = ($default !== false && $k == $default)?" selected='true'":'';
			$k = (substr_count($k, "'") > substr_count($k, '"'))?'"'.$k.'"':"'".$k."'";
			$html .= "<option$sel value=$k>$v</option>\n";
		}
	}
	
	foreach($collection as $item) {
		$v = is_object($item)?((!$value)?$item->__toString():$item->$value):$item[$value];
		$k = is_object($item)?$item->$key:$item[$key];
		$sel = ($default !== false && $k == $default)?" selected='true'":'';
		$k = (substr_count($k, "'") > substr_count($k, '"'))?'"'.$k.'"':"'".$k."'";
		$html .= "<option$sel value=$k>$v</option>\n";
	}

	# add after items
	if (array_key_exists('after', $options)) {
		foreach($options['after'] as $item) {
			$v = is_object($item)?((!$value)?$item->__toString():$item->$value):$item[$value];
			$k = is_object($item)?$item->$key:$item[$key];
			$sel = ($default !== false && $k == $default)?" selected='true'":'';
			$k = (substr_count($k, "'") > substr_count($k, '"'))?'"'.$k.'"':"'".$k."'";
			$html .= "<option$sel value=$k>$v</option>\n";
		}
	}

	$html .= "</select>";
	return $html;
}

function file_select($obj, $name, $prop, $dir, $options=array(), $abs=false) {
	if (!$abs) $dir = $_SERVER['DOCUMENT_ROOT'].$dir;
	$dir = new DirectoryIterator($dir);
	$out = array();
	foreach($dir as $file) {
		$fname = $file->getFilename();
		if (!$file->isDir() && $fname{0} != '.') {
			$pn = (!$abs)?str_replace($_SERVER['DOCUMENT_ROOT'], '', $file->getPathname()):$file->getPathname();
			$out[] = array('pathname' => $pn, 'path' => $file->getPath(), 'filename' => $file->getFilename());
		}
	}
	if (array_key_exists('upload',$options)) {
		$upload = $options['upload'];
		unset($options['upload']);
	} else {
		false;
	}
	$out = select($obj, $name, $prop, $out, 'pathname', 'filename', $options);
	
	# if upload add the input
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



function date_field($obj, $name, $prop) {
	$v = $obj?(is_object($obj)?$obj->$prop:$obj[$prop]):'';
	if ($v == -1 || $v === false) $v = Format::prettyDate();
	$out = "<input type='text' name='{$name}[{$prop}]' value='$v' id='{$name}_$prop' size='40' maxlength='100' />\n";
	$out .= "<span class='tip'><a href='#' onclick='d=new Date(); ds=d.toString(); ds=ds.split(\" \"); v=ds[1]+\" \"+ds[2]+\", \"+ds[3]; document.getElementById(\"{$name}_$prop\").value=v;return false;'>Insert Current Date</a></span>";
	return $out;
}

function datetime_field($obj, $name, $prop) {
	$v = $obj?(is_object($obj)?$obj->$prop:$obj[$prop]):'';
	if ($v == -1 || $v === false) $v = Format::prettyDateTime();
	$out = "<input type='text' name='{$name}[{$prop}]' value='$v' id='{$name}_$prop' size='40' maxlength='100' />\n";
	$out .= "<span class='tip'><a href='#' onclick='d=new Date(); ds=d.toString(); ds=ds.split(\" \"); v=ds[1]+\" \"+ds[2]+\", \"+ds[3]+\" \"+ds[4].substring(0,ds[4].lastIndexOf(\":\"))+(d.getHours()>11?\" PM\":\" AM\"); document.getElementById(\"{$name}_$prop\").value=v;return false;'>Insert Current Date and Time</a></span>";
	return $out;
}

function time_field($obj, $name, $prop) {
	$v = $obj?(is_object($obj)?$obj->$prop:$obj[$prop]):'';
	if ($v == -1 || $v === false) $v = Format::prettyTime();
	$out = "<input type='text' name='{$name}[{$prop}]' value='$v' id='{$name}_$prop' size='40' maxlength='100' />\n";
	$out .= "<span class='tip'><a href='#' onclick='d=new Date(); ds=d.toString(); ds=ds.split(\" \"); v=ds[4].substring(0,ds[4].lastIndexOf(\":\"))+(d.getHours()>11?\" PM\":\" AM\"); document.getElementById(\"{$name}_$prop\").value=v;return false;'>Insert Current Time</a></span>";
	return $out;
}

function submit_to($name, $confirm=false) {
	ob_start();
	echo "<input type='submit'";
	if ($confirm) echo " onclick='return confirm(\"Are You Sure?\");' ";
	echo " value='";
	echo $name;
	echo "'>";
	return ob_get_clean();
}


function button_to($name, $args=false, $confirm=false) {
	if (!is_array($args)) {
		$args = func_get_args();
		array_shift($args);
	}
	
	ob_start();
	echo "<input type='button' onclick='";
	if ($confirm) echo 'if(confirm("Are You Sure?")) ';
	echo "document.location.href=\"".url_for($args)."\"' value='";
	echo $name;
	echo "'>";
	return ob_get_clean();
}

?>


