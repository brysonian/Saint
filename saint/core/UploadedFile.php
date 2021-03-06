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
	

	static function create($fileinfo) {
		$out = array();
		if (is_array($fileinfo['name'])) {
			foreach($fileinfo['name'] as $k => $v) {
				if (!empty($v) && $fileinfo['error'][$k] == UPLOAD_ERR_OK) {
					$out[$k] = new UploadedFile(
						$fileinfo['tmp_name'][$k],
						$v,
						$fileinfo['type'][$k],
						$fileinfo['error'][$k],
						$fileinfo['size'][$k]);
				}
			}
		} else {
			$out[] = new UploadedFile(
				$fileinfo['tmp_name'],
				$fileinfo['name'],
				$fileinfo['type'],
				$fileinfo['error'],
				$fileinfo['size']);			
		}
		return $out;
	}



	function __construct($path, $name, $mime, $err, $size) {
		
		# make sure it was uploaded
		if (!is_uploaded_file($path)) throw new NotUploadedFile("File is not a valid uploaded file.");
		
		# set params
		$this->set_path($path);
		$this->set_filename($name);
		$pi = pathinfo($name);
		$this->set_extension($pi['extension']);
		$this->set_mime_type($mime);
		$this->set_error($err);
		$this->set_size($size);

		$exif_type = exif_imagetype($path);
		$t = false;
		switch(true) {
			case ($exif_type == IMAGETYPE_GIF && $this->get_extension() == 'gif'):
				$t = 'gif';
				break;
			
			case ($exif_type == IMAGETYPE_JPEG && ($this->get_extension() == 'jpg' || $this->get_extension() == 'jpeg')):
				$t = 'jpg';
				break;
			
			case ($exif_type == IMAGETYPE_PNG && $this->get_extension() == 'png'):
				$t = 'png';
				break;
					
			case (($exif_type == IMAGETYPE_SWF || $exif_type == IMAGETYPE_SWC) && $this->get_extension() == 'swf'):
				$t = 'swf';
				break;				
		}

		if ($t) {
			$this->set_type($t);
			$this->set_image_type_code($exif_type);
			$this->set_is_image(true);
		} else {
			$this->set_image_type_code(false);
			$this->set_is_image(false);
		}

		
		/*
		# check the type
		$s = getimagesize($path);
		$t = false;		
		if (is_array($s)) {
			switch($s[2]) {
				case IMAGETYPE_GIF:
					$t = 'gif';
					break;
				
				case IMAGETYPE_JPEG:
					$t = 'jpg';
					break;
				
				case IMAGETYPE_PNG:
					$t = 'png';
					break;
				
				case self::IMG_WBMP:
					$t = 'wbmp';
					break;
				
				case self::IMG_XPM:
					$t = 'xpm';
					break;
			
				case IMAGETYPE_SWF:
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
		*/
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
	public function get_error() 		{ return $this->error; }
	public function get_size() 			{ return $this->size; }

	public function get_image_type_code() { return $this->imagetypecode; }
	public function is_image() { return $this->isimage; }
	public function get_width() {
		if (!$this->width) $this->init_width_and_height();
		return $this->width; 
	}
	public function get_height() { 
		if (!$this->height) $this->init_width_and_height();
		return $this->height; 
	}
	
	
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
	private function init_width_and_height() {
		$s = getimagesize($this->get_path());
 		$this->set_width($s[0]);
		$this->set_height($s[1]);
		}

	// testers
	public function is_jpg() { return ($this->get_image_type_code() == IMAGETYPE_JPEG); }
	public function is_jpeg() { return ($this->get_image_type_code() == IMAGETYPE_JPEG); }
	public function is_gif() { return ($this->get_image_type_code() == IMAGETYPE_GIF); }
	public function is_png() { return ($this->get_image_type_code() == IMAGETYPE_PNG); }
	
	
	
// ===========================================================
// - SHORTCUT FOR HANDLING AN UPLOADED IMAGE
// ===========================================================
	/**
	 * Handle moving an image, setting the right property in the model, and optionally show an error if the file exists
	 * 
	 *	@param $model: 				the model object to deal with
	 *	@param $property: 		the property to set
	 *  @param $destination: 	where to move the file
	 *  @param $force:				if true, overwrite the image
	 */
	public static function move_and_set_model_property($model, $property, $destination, $newname=false, $force=false) {
		$ok = true;
		if ($model->$property instanceof UploadedFile && !$model->errors()) {
			$ok = $model->$property->move_to($destination, $newname, $force);
			if (!$ok) {
				$model->add_error($property, 'An :property with the name: '.$model->$property->get_filename().' already exists.');
				unset($model->$property);
				$model->$property = '';
			} else {
				$model->$property = $model->$property->get_web_path();
			}
		}
		return $ok;
	}

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
		if (!file_exists($path)) throw new InvalidPath("The path ".$path." does not seem to exist.");

		# now see if i can write to it
		if (!is_writable($path)) throw new WritePermission("Cannot write to the directory ".$path.".");

		# if no newname is specified, set newname to the original name
		if ($newname == false) $newname = $this->get_filename();

		# make sure the filename is kosher by killing non alpanum chars
		$newname =  preg_replace("/[^a-zA-Z0-9_.]/i", '-', stripslashes($newname));
		
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
#			chmod ($path.$newname, 0777);
			$this->set_path($path.$newname);
		} else {
			throw new FileMove("There was a problem moving the file ".$this->get_name()." to the directory $path.");;
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
			throw new GDMissing("GD is required to resize image.", 0);
			
		if ($this->is_jpeg()) {
			$src_img = @imagecreatefromjpeg($this->get_path());

		} else if ($this->is_gif()) {
			$src_img = @imagecreatefromgif($this->get_path());

		} else if ($this->is_png()) {
			$src_img = @imagecreatefrompng($this->get_path());
			imagealphablending($src_img, false); 
			imagesavealpha($src_img,true);
		} else {
			throw new FormatNotResizable("File is not a resizable format.", 0);
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
	
	function __toString() {
		return $this->path;
	}
}

// ===========================================================
// - EXCEPTIONS
// ===========================================================
class InvalidPath extends SaintException {}
class WritePermission extends SaintException {}
class NotUploadedFile extends SaintException {}
class FileMove extends SaintException {}
class GDMissing extends SaintException {}
class FormatNotResizable extends SaintException {}
class InvalidFileType extends SaintException {}

?>