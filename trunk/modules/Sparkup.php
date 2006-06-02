<?php



/**
	Sparkup class for text formatting.

	@param  $n maximum value for header tags. Useful in keeping document heirarchy intact.

	@usage
		$text = "
		1[This is a page]1
		Now i want to write some *[bold words]* and a line
		=[1px #F60 50%]=
		and parse out the _[smart tags]_ while keeping the text
		+this is
		+ a list
		+ last line
		now what is next? how about a ol
		# one
		# two
		# three
		";

	
		$st = new Sparkup();
		
		# parse for bold, italic, etc as well as UL and OL
		$st->parse($text);
		
		# parse bold, italic etc only
		$st->parse_tags($text);
		
		
**/

class Sparkup
{
	protected $usertags 		=	array();		# array of user tags and functions see add_tag() method;
	protected $skiptags 		=	'';				# string of tags to skip
	protected $parselinks	=	true;			# if false, fully qualified url's won't be parsed into links
	protected $linkformat		=	"<a href='{url}'>{name}</a>";	# format for href replacements
	protected $lineheads	= false;
	protected $tagpattern 	=	'/(.)\[(.*)\]\1/U';
	
	protected $tags;
	protected $head_pats;
	protected $headers_regex;
	protected $linenum;
	
	/**
	* constructor, sets the base header
	*/
	function Sparkup($n=0) {
		$this->baseHeaderNumber = $n;		# define a default for the base header level
		$this->tags = array();
		$this->lineheads = array(
			'0' => array('type' => 'line', 	'func' => array($this, 'format_plain')),
			'+' => array('type' => 'block', 	'func' => array($this, 'format_ul')),
			'#' => array('type' => 'block', 	'func' => array($this, 'format_ol')),
			';' => array('type' => 'line', 	'func' => array($this, 'format_code')),
			'|' => array('type' => 'line', 	'func' => array($this, 'format_blockquote')),
		);
	}

	
	/**
	* add custom line headers
	*/
	function add_line_header($type, $pattern, $callback) {
		$this->lineheads[$pattern] = array('type' => $type, 'func' => $callback);
	}
	
	/**
	* add custom tags
	*/
	function add_tag($pattern, $callback) {
		$this->tags[$pattern] = $callback;
	}
	

	/**
	* parse a str for Sparkup
	*/
	function parse($output, $includeStartandEndP=true) {
		$this->linenum = 0;

		# get list of line headers
		$this->head_pats = array_keys($this->lineheads);
		array_shift($this->head_pats);

		# build the regex
		$this->headers_regex = '/^(';
		foreach($this->head_pats as $v) {
			$this->headers_regex .= '(?:'.preg_quote($v, '/').')|';
		}		
		$this->headers_regex = substr($this->headers_regex,0, -1).'\s*)/m';


		$output = $this->parse_line_headers($output, $includeStartandEndP);
		if ($this->parselinks) $output = $this->parselinks($output);
		return $output;
	}



	function parse_line_headers($str,$includeStartandEndP=false) {
		$str = trim($str);
		$output = $this->process_line_headers($str);
		
		if ($includeStartandEndP) $output = "<p>$output";
		
		# replace any newline not surrounded by >< with a br
		$output = preg_replace("/(?<![o|u]l\>)[\n]/", "<br />\n", $output);

		# now clean up for header tags
		# kill all br's that follow a closing header tag
		$output = preg_replace("/(<\/h\d>)<br \/>/", "\\1", $output);
	
		# now clean up for paragrpahs
		# this a bit weird because by default the first and last p tags
		# aren't returned, since these normally appear in templates
		# override this by passing true as the second arg to parse()
			# first split at double br's
			if (preg_match('/<br \/>[\r\n]{0,2}<br \/>/',$output)) {
				$paras = preg_split('/<br \/>[\r\n]{0,2}<br \/>/', $output);
				# then join with p tags
				$output = join("</p>\n<p>",$paras);				
			}

		# add end p tag
		if ($includeStartandEndP) $output = "$output</p>";

		# now look for header tags inside para tags.
		# since we are *always* in a para, this means replacing
		# open h tags with close para/open h
		# and likewise for close h tags
		$output = preg_replace("/(<h\d>)/", "</p>\\1", $output);
		$output = preg_replace("/(<\/h\d>)/", "\\1<p>", $output);
		
		# replace empty paras and u|ols
		$output = preg_replace("/<p>[\r\n]?<\/p>/", "", $output);
		$output = preg_replace("/<([u|o]l)[^>]*>[\r\n]?<\/\\1>/", "", $output);
		
		# replace ul|ol|p|li/br's
		$output = preg_replace("/(<\/?(?:p|li|ol|ul)[^>]*>)[\r\n]?<br \/>/", "\\1", $output);

		# and empty p/ul|ol
		#$output = preg_replace("/<(\/?)p>(<\\1[o|u]l)/", "\\2", $output);
		
		# double p
		$output = preg_replace("/<(\/?)p><\\1p>/", "<\\1p>", $output);

		
		
		
		return $output;

	}
	
	function parse_tags($str) {
		#$tagpattern = '/(.)\[([^\]]*)\]\1/';
		$output = preg_replace_callback(
			$this->tagpattern,
			array($this, 'tag_callback'),
			$str
		);
		return $output;
	}

	/**
	* this is the callback function for the
	* regex to find the smart tags
	*/
	function tag_callback($matches) {
		# def the tag for this needle
		$tag = $matches[1];
		$content = $matches[2];
		
		# if the content contains [], recurse
		if (preg_match($this->tagpattern, $content)) $content = $this->parse_tags($content);
		
		# if this tag is in the skip string,
		# return the tag unparsed
		if (strstr($this->skiptags, $tag) !== false) return $tag.'['.$matches[2].']'.$tag;
		
		# do the right thing for each tag
		switch($tag) {
			# bold
			case '*':
				$output = "<strong>$content</strong>";
				break;
			
			# italic
			case '_':
				$output = "<em>$content</em>";
				break;
			
			# img
			case '!':
				$output = "<img src='$content'/>";
				break;
			
			# strikethrough
			case '-':
				$output = "<span class='strike'>$content</span>";
				break;

			# code
			case ';':
				$output = "<code>$content</code>";
				break;
				
			# line
			case '=':
				$output = '<hr';		# init output value
				
				# if there are args, add them
				if(strlen($content) > 0) {
					$output .= ' style="border:none;';
					list($height, $color, $width) = sscanf($content, '%s %s %s');
					if ($height)	$output .= "height:$height;";
					if ($color)		$output .= "color:$color;background-color:$color;";
					if ($width)		$output .= "width:$width;";
					$output .= '"';		# close style
				}
				$output .= ' />';		# close tag
				break;
			
			default:
				# first make sure there aren't custom ones
				if (array_key_exists($tag, $this->tags)) {
					$output .= call_user_func($this->tags[$tag], $content);
			
				# then handle numbers
				}else if (is_numeric($tag)) {
					$output = "<h".($tag + $this->baseHeaderNumber).">$content</h".($tag + $this->baseHeaderNumber).">";
				
				# else if the tag ain't found
				} else {
					$output = $tag.'['.$matches[2].']'.$tag;
				}
				break;
		}
	
		return $output;
	}
	
	/**
	* parse for ul
	* pass the string to parse
	|	This function parses text for line head tags
	|	Because line head tags are used for block elements,
	|	a buffer is kept of the current block (including lines w/o a head)
	|	once the block type changes, the block contents are passed off to
	|	an associated function to handle the content.
	|	The function returns the processed content and the content
	|	is then added to the overall string buffer.
	*/
	function process_line_headers($str, $main=true) {
		#$db = debug_backtrace();
		#$main = false;
		#if ($db[1]['function'] == 'parse_line_headers') $main = true;
		#unset($db);
				
		# break the string at lines
		$lines = array();
		preg_match_all('/^(.*)$/m', $str, $lines);
		$output = '';		# init output
		$blocktype = false;	# flag to know if a list is being built
		$buffer = '';		# buffer of processed content
		$blockbuffer = '';	# buffer containing unparsed content of current block
		
		# loop through lines and look for a starter tag
		$lines = $lines[0];
		foreach($lines as $i => $line) {
			$matches = array();
			preg_match($this->headers_regex, $line, $matches);
			$matches = isset($matches[1])?trim($matches[1]):'';
			
			if (in_array($matches, $this->head_pats)) {
				if ($blocktype != $matches) {
					$this->handle_line_header($blocktype, $matches, $buffer, $blockbuffer, $line);
					$blocktype = $matches;
				}
				$blockbuffer .= "$line\n";
			} else {
				if ($blocktype) {
					$this->handle_line_header($blocktype, $matches, $buffer, $blockbuffer, $line);
					$blocktype = false;
				}
				$blockbuffer .= "$line\n";
			}
			if ($main) $this->linenum++;
		}

		$buffer .= $this->format_line_head($blockbuffer, $blocktype);
		return trim($buffer);
	}
	
	function format_line_head($blockbuffer, $blocktype) {
		if ($blocktype) {
			$blockbuffer = preg_replace('/^'.preg_quote($blocktype, '/').'/m', '', $blockbuffer);
		}
		
		$lh = $this->lineheads[$blocktype];

		# if the type is line, just call,
		# otherwise recurse the block first
		if ($lh['type'] == 'line') {
			return call_user_func($lh['func'], $blockbuffer, $blocktype, $this->linenum);
		} else {
			$blockbuffer = trim($blockbuffer);
			#$blockbuffer = preg_replace('/^'.preg_quote($blocktype, '/').'/m', '', $blockbuffer);
			$blockbuffer = $this->process_line_headers($blockbuffer, false);
			return call_user_func($lh['func'], $blockbuffer, $blocktype, $this->linenum);
		}
	}
	
	/**
	* handle a line header
	*/
	function handle_line_header($blocktype, $matches, $buffer, $blockbuffer, $line) {
		if (isset($this->lineheads[$blocktype])) {
			$buffer .= $this->format_line_head($blockbuffer, $blocktype);
		}
		$blockbuffer = '';
	}
	
	/**
	* functions for formatting block elements
	**/
	function format_ul($str, $pat) {
		$str = preg_replace("/\n(<[u|o]l>)/m", '\\1', $str);

 		$output = "<ul>";
		$lines = array();
		preg_match_all('/^(.*)$/m', $str, $lines);
		foreach($lines[0] as $i => $line) {
			if (trim($line)) $output .= "<li>".trim($line)."</li>";
		}
		$output .= "</ul>\n";
		return $output;
	}
		
	function format_ol($str, $pat) {
		$str = preg_replace("/\n(<[u|o]l>)/m", '\\1', $str);
 		$output = "<ol>";
		$lines = array();
		preg_match_all('/^(.*)$/m', $str, $lines);
		foreach($lines[0] as $i => $line) {
			if ($line = trim($line)) $output .= "<li>".$line."</li>";
		}
		$output .= "</ol>\n";
		return $output;
	}
	
	function format_code($str, $pat) {
		$str = preg_replace('/^\;/m', '', $str);
		return "<code>".rtrim($str)."</code>";
		return $output;
	}

	function format_blockquote($str, $pat) {
		$str = preg_replace('/^\|/m', '', $str);
		return "<blockquote>".rtrim($str)."</blockquote>";
		return $output;
	}
	
	function format_plain($str, $pat) {
		$output = $this->parse_tags($str);
		#$output = $this->parse_tags(preg_replace('/(?!#)/', '&amp;', $str));

		
		# replace typography stuff
		$output = preg_replace('/\'/', '&apos;', $output);
		$output = preg_replace('/--/', '&emdash;', $output);

		$matches =array();
		preg_match_all('/"/', $output, $matches, PREG_OFFSET_CAPTURE);
		$flipper = 0;
		$offset = 0;
		foreach ($matches[0] as $match) {
			$pre = substr($output, 0, $match[1]+$offset);
			if ($flipper == 0) {
				$q = "&ldquo;";
			} else {
				$q = "&rdquo;";
			}
			$post = substr($output, $match[1]+$offset+1);
			$flipper = 1 - $flipper;
			$output = $pre.$q.$post;
			$offset += 6;
		}
		return $output;
	}
		
		
	
	
	/**
	* this method parses fully qualified http address 
	* if the address is IMMEDIATELY followed by [name] the text of the anchor will be set to name
	* i.e.
	*	http://www.pre-cursor.com[my website]
	* would become:
	*	<a href="http://www.pre-cursor.com">my website</a>
	* The format of the anchor is set in the $this->linkformat property.
	**/
	function parselinks($str) {
		# try to skip links in attributes
		$needle = "/((?<!['|\"])(?:http|mailto|ftp|https|telnet):\/\/[\w\.\;\%\_\/\=\?\-\,\~\!\@\#\$\&\+\'\:\(\)]*)(\[)?(?(2)([^\]]+))(?(2)\])/xis";	# our needle

		$output = preg_replace_callback(
			$needle,
			create_function(
                '$matches',
               '
					 $url = str_replace("mailto://","mailto:",$matches[1]);
                if (!empty($matches[3])) {
                	$name = str_replace("mailto://","",$matches[3]);
                	return str_replace(array("{url}","{name}"), array($url,$name), "'.$this->linkformat.'");
                } else {
                	$name = str_replace("mailto://","",$matches[1]);
                	return str_replace(array("{url}","{name}"), array($url, $name), "'.$this->linkformat.'");
                }
                '
              ),
			$str
		);
		return $output;
	}
		
		
	/**
	* this method allows you to disable certain tags
	* by passing a string of tags to disable
	*/
	function disable_tags($skip) {
		$this->skiptags = $skip;
	}
	
	/**
	* set the format for anchor tags
	* ie: "<a href='{url}' rel='external'>{name}</a>"
	**/
	function set_link_format($str) {
		$this->linkformat		=	$str;	# format for href replacements
	}
	
	
	// ===========================================================
	// - STATIC METHODS FOR CONVIENCE
	// ===========================================================
	/**
		htmlify content including p tags

		@param  text the text to parse
		@return text
	*/
	static function textToHTML($text, $ptags=true) {
		$st = new Sparkup(1);
		$output = $st->parse($text, $ptags);
		return trim($output);
	}

	/**
		Only replace Sparkup tags in text

		@param  text the text to parse
		@return text
	*/
	static function tagText($text) {
		$st = new Sparkup(1);
		$output = $st->parselinks($st->parse_tags($text));
		return trim($output);
	}
}


// ===========================================================
// - ADD SOME NORMAL FUNCTIONS SO THESE ARE MORE ACCESSIBLE
// ===========================================================
	// text to html
	function h($text, $ptags=true) {
		if (is_object($text)) $text = $text->__toString();
		return Sparkup::textToHTML($text, $ptags);
	}
	
	// tag text
	function stag($text) {
		return Sparkup::tagText($text);
	}


?>