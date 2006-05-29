<?php

class UploadedFile
{

	protected $path;
	protected $name;
	protected $type;
	protected $mime_type;
	protected $error;
	protected $size;
	
	protected $extension;
	
	protected $imagetypecode;
	protected $isimage;
	protected $width;
	protected $height;
	

	// since these don't appear if GD isn't installed
	const IMG_GIF		= 1;
	const IMG_JPG		= 2;
	const IMG_PNG8	= 3;
	const IMG_PNG		= 4;
	const IMG_WBMP	= 8;
	const IMG_SWF		= 13;
	const IMG_XPM		= 16;


	static function create($fileinfo) {
		$out = array();
		foreach($fileinfo['name'] as $k => $v) {
			if (!empty($v)) {
				$out[$k] = new UploadedFile($fileinfo['tmp_name'][$k], $v, $fileinfo['type'][$k], $fileinfo['error'][$k], $fileinfo['size'][$k]);
			}
		}
		return $out;
	}



	function __construct($path, $name, $mime, $err, $size) {
		
		# make sure it was uploaded
		if (!is_uploaded_file($path)) throw new SaintException("File is not a valid uploaded file", INVALID_UPLOADED_FILE);
		
		# set params
		$this->set_path($path);
		$this->set_filename($name);
		$pi = pathinfo($name);
		$this->set_extension($pi['extension']);
		$this->set_mime_type($mime);
		$this->set_error($err);
		$this->set_size($size);
		
		
		# check the type
		$s = getimagesize($path);
		$t = false;		
		if (is_array($s)) {
			switch($s[2]) {
				case self::IMG_GIF:
					$t = 'gif';
					break;
				
				case self::IMG_JPG:
					$t = 'jpg';
					break;
				
				case self::IMG_PNG8:
				case self::IMG_PNG:
					$t = 'png';
					break;
				
				case self::IMG_WBMP:
					$t = 'wbmp';
					break;
				
				case self::IMG_XPM:
					$t = 'xpm';
					break;
			
				case self::IMG_SWF:
					$t = 'swf';
					break;
			}
		}

		if ($t) {
			$this->set_type($t);
			$this->set_image_type_code($s[2]);
			$this->set_is_image(true);
 			$this->set_width($s[0]);
			$this->set_height($s[1]);
		} else {
			$this->set_image_type_code(false);
			$this->set_is_image(false);
		}
		
	}
	
// ===========================================================
// - ACCESSORS
// ===========================================================
	// getters
	public function get_path() { return $this->path; }
	public function get_web_path() { return str_replace(DOC_ROOT, '', $this->path); }
	public function get_type() { return $this->type; }
	public function get_filename() { return $this->name; }
	public function get_extension() { return $this->extension; }
	public function get_mime_type() { return $this->mime_type; }
	public function get_error() 		{ return $this->size; }
	public function get_size() 			{ return $this->error; }

	public function get_image_type_code() { return $this->imagetypecode; }
	public function is_image() { return $this->isimage; }
	public function get_width() { return $this->width; }
	public function get_height() { return $this->height; }
	
	
	// setters
	private function set_path($newval) { $this->path = $newval; }
	private function set_filename($newval) { $this->name = $newval; }
	private function set_extension($newval) { $this->extension = $newval; }
	private function set_mime_type($newval) { $this->mime_type = $newval; }
	private function set_error($newval) { $this->error = $newval; }
	private function set_size($newval) { $this->size = $newval; }

	private function set_type($newval) { $this->type = $newval; }
	private function set_image_type_code($newval) { $this->imagetypecode  = $newval; }
	private function set_is_image($newval) { $this->isimage  = $newval; }
	private function set_width($newval) { $this->width  = $newval; }
	private function set_height($newval) { $this->height  = $newval; }


	// testers
	public function is_jpg() { return ($this->get_image_type_code() == self::IMG_JPG); }
	public function is_jpeg() { return ($this->get_image_type_code() == self::IMG_JPG); }
	public function is_gif() { return ($this->get_image_type_code() == self::IMG_GIF); }
	public function is_png() { return ($this->get_image_type_code() == self::IMG_PNG || $this->get_image_type_code() == self::IMG_PNG8); }
	


// ===========================================================
// - MISC METHODS
// ===========================================================
	/**
	*	move uploaded file to a directory
	*	@param $path: the path to save the file to
	*	@param $newname: if set, the new name for the file
	*/
	function move_to($path, $newname=false, $force=false) {
		# first see if the dir exists
		if (!file_exists($path)) throw new SaintException("The path ".$path." does not seem to exist.", PATH_DOESNT_EXIST);

		# now see if i can write to it
		if (!is_writable($path)) throw new SaintException("Cannot write to the directory ".$path.".", PATH_NOT_WRITABLE);

		# if no newname is specified, set newname to the original name
		if ($newname == false) $newname = $this->get_filename();

		# make sure the filename is kosher by killing non alpanum chars
		$newname =  preg_replace("/[^a-zA-Z0-9_.]/i", '_', stripslashes($newname));
		
		# check for trailing slash
		if (substr($path, -1) != '/') $path .= '/';
		
		# if we aren't forcing, check that it doesn't exist first
		if (!$force) {
			if (file_exists($path.$newname)) return false;
		}
		
		# copy it to the directory
		$status = move_uploaded_file($this->get_path(), $path.$newname);

		# if it worked, set the perms and return new location
		if ($status) {
			chmod ($path.$newname, 0775);
			$this->set_path($path.$newname);
		} else {
			throw new SaintException("There was a problem moving the file ".$this->get_name()." to the directory $path.", 0);
		}
		return true;
	}
	
	
	

// ===========================================================
// - IMAGE METHODS
// ===========================================================
	/**
	*	resize image
	*	@param $width: new width
	*	@param $height: new height
	*	@returns bool of success
	*/
	function resize($width, $height, $path, $output_type='jpg') {
		# make sure GD is installed
		if (!function_exists('gd_info')) 
			throw new SaintException("GD is required to resize image.", 0);
			
		if ($this->is_jpeg()) {
			$src_img = @imagecreatefromjpeg($this->get_path());

		} else if ($this->is_gif()) {
			$src_img = @imagecreatefromgif($this->get_path());

		} else if ($this->is_png()) {
			$src_img = @imagecreatefrompng($this->get_path());
			imagealphablending($src_img, false); 
			imagesavealpha($src_img,true);
		} else {
			throw new SaintException("File is not a resizable format.", 0);
		}

		$dst_img = imagecreatetruecolor($width,$height);
		$bg = imagecolorallocate($dst_img, 255,255,255);
		imagefill($dst_img, 0, 0, $bg);

		imagecopyresized($dst_img,$src_img,0,0,0,0,$width,$height,imagesx($src_img),imagesy($src_img));

		// cleanup
		imagedestroy($src_img);
		
		// update size
		$this->set_width($width);
		$this->set_height($height);
		
		switch ($output_type) {
			case 'gif':
				return imagegif($dst_img, $path);
			case 'png':
				return imagepng($dst_img, $path);
			default:
				return imagejpeg($dst_img, $path, 100);
		}
	}
	
	/**
	*	resize image preserving aspect to a max size
	*	@param $max: max size
	*	@returns bool of success
	*/
	function resize_max($max, $path, $output_type='jpg') {
		if ($this->get_width() > $this->get_height()) {
			$w = $max;
			$h = floor($max * ($this->get_height()/$this->get_width()));
		} else {
			$w = floor($max * ($this->get_width()/$this->get_height()));
			$h = $max;
		}
		return $this->resize($w, $h, $path, $output_type);
	}
}

?>