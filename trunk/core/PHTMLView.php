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
// - FORM HELPERS
// ===========================================================
function text_field($obj, $name, $prop) {
	if ($obj) {
		$v = (is_object($obj))?$obj->$prop:$obj[$prop];
	} else {
		$v = '';
	}
	return "<input type='text' name='{$name}[{$prop}]' value='$v' id='{$name}_$prop' size='40' maxlength='100' />\n";
}

function text_area($obj, $name, $prop, $size=2000) {
	if ($obj) {
		$v = (is_object($obj))?$obj->$prop:$obj[$prop];
	} else {
		$v = '';
	}
	
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
			if ($obj->$prop) $default = $obj->$prop;
		} else {
			if ($obj[$prop]) $default = $obj[$prop];
		}
	}
	if ($default === false && array_key_exists('default', $options)) {
		$default = $options['default'];
	}
	
	# add blank
	if (array_key_exists('include_blank', $options)) {
		$html .= "<option value=''></option>\n";
	}
	
	# add before items
	if (array_key_exists('before', $options)) {
		foreach($options['before'] as $item) {
			$v = is_object($item)?$item->$value:$item[$value];
			$k = is_object($item)?$item->$key:$item[$key];
			$sel = ($default !== false && $k == $default)?" selected='true'":'';
			$html .= "<option$sel value='$k'>$v</option>\n";
		}
	}
	
	foreach($collection as $item) {
		$v = is_object($item)?$item->$value:$item[$value];
		$k = is_object($item)?$item->$key:$item[$key];
		$sel = ($default !== false && $k == $default)?" selected='true'":'';
		$html .= "<option$sel value='$k'>$v</option>\n";
	}

	# add after items
	if (array_key_exists('after', $options)) {
		foreach($options['after'] as $item) {
			$v = is_object($item)?$item->$value:$item[$value];
			$k = is_object($item)?$item->$key:$item[$key];
			$sel = ($default !== false && $k == $default)?" selected='true'":'';
			$html .= "<option$sel value='$k'>$v</option>\n";
		}
	}

	$html .= "</select>";
	return $html;
}



?>