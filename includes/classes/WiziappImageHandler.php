<?php if (!defined('WP_WIZIAPP_BASE')) exit();
/**
* Make sure every image size we need is available from wordpress
*
* @todo sync this with the same code part in the global services
*
* @todo Add file rotating like in the log files, only the conditions here
* should be: older then 30 days, wasn't accessed for 7 days, if the folder is bigger than 10 mb
* delete the older file according to the access date.
*
* @package WiziappWordpressPlugin
* @subpackage MediaUtils
* @author comobix.com plugins@comobix.com
*/

class WiziappImageHandler {
	/**
	* The implementation of the actual resizing
	* can be PhpThumb or our services in this order
	*
	* if an empty string it is our services.
	*
	* @var string
	*/
	private $imp = '';

	/**
	* The image resizing object, will be initialized according to the $imp
	*
	* @var WiziappPhpThumbResizer/WiziappResizer
	*/
	private $handler = null;

	/**
	* The directory to save the cache files in
	*
	* @var mixed
	*/
	private $cache = '';

	/**
	* holds the image src as was given
	*
	* @var string
	*/
	private $imageFile = '';

	private $path = '';
	/**
	* constructor
	*
	* @param string $imageFile
	* @return WiziappImageHandler
	*/
	public function __construct ($imageFile='') {
		$this->cache = WiziappContentHandler::getInstance()->get_blog_property('data_files_dir').DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'images';

		if (!empty($imageFile)) {
			$this->imageFile = $imageFile;

			/**
			* Check and initialized the image resizing object according to the availability
			*/
			$this->_checkImp();
			$imageClass = "Wiziapp{$this->imp}Resizer";
			WiziappLog::getInstance()->write('INFO', 'About to load image handler class::'.$imageClass, 'WiziappImageHandler._checkImp');
			require_once('imageResizers/' . $imageClass . '.php');
			$this->handler = new $imageClass();
		}
	}

	public function checkPath(){
		return is_writable($this->cache);
	}

	public function getResizedImageUrl($url, $width, $height, $type = 'adaptiveResize', $allow_up = FALSE){
		$url = urlencode($url);
		return WiziappContentHandler::getInstance()->get_blog_property('url') . "/?wiziapp/getimage/&width={$width}&height={$height}&type={$type}&allow_up={$allow_up}&url={$url}";
	}

	public function wiziapp_getResizedImage($width, $height, $type = 'adaptiveResize', $allow_up = FALSE, $is_featured_post_thumb = FALSE){
		if ($this->handler == null){
			WiziappLog::getInstance()->write('error', 'No images handler', 'WiziappImageHandler.wiziapp_getResizedImage');
			return false;
		}

		// Get the ext
		$tmp = explode('?', $this->imageFile);
		$ext = substr($tmp[0], strrpos($tmp[0], '.'));

		if (strpos($ext, '/') !== FALSE){
			// There was a slash so this image doesn't have an extension, force file type change to png
			$ext = '.png';
		}

		$extraForKey = '';
		if (function_exists('is_multisite') && is_multisite()) {
			WiziappLog::getInstance()->write('info', 'The blog is a multisite installation, adding the blog id to the key', 'WiziappImageHandler.wiziapp_getResizedImage');
			global $wpdb;
			$extraForKey = $wpdb->blogid;
		}
		$cacheFileImageKey = md5($this->imageFile . $width . $height . $type . $extraForKey);

		$cacheFile = realpath($this->cache) . '/' . $cacheFileImageKey . $ext;

		WiziappLog::getInstance()->write('info', 'Checking for cache key: '.$cacheFileImageKey.' in '.$cacheFile, 'WiziappImageHandler.wiziapp_getResizedImage');

		if ($this->_cacheExists($cacheFile)){
			$url = str_replace(WIZI_ABSPATH, WiziappContentHandler::getInstance()->get_blog_property('url') . '/', $cacheFile);
			WiziappLog::getInstance()->write('info', "Before loading image from cache: " . $cacheFile, "image_resizing.getResizedImage");
			$this->handler->load($cacheFile, FALSE);
			WiziappLog::getInstance()->write('info', "After loading image from cache: " . $cacheFile, "image_resizing.getResizedImage");
		} else {
			$this->imageFile = str_replace(' ', '%20', $this->imageFile);
			WiziappLog::getInstance()->write('info', "Before resizing image: " . $this->imageFile, "image_resizing.getResizedImage");
			$url = $this->imageFile;

			if (strpos($this->imageFile, WiziappContentHandler::getInstance()->get_blog_property('url')) === 0){
				WiziappLog::getInstance()->write('info', 'Looks like a local image: '.$this->imageFile, 'WiziappImageHandler.wiziapp_getResizedImage');
				$url = str_replace(WiziappContentHandler::getInstance()->get_blog_property('url'), WIZI_ABSPATH, $url);
				// Make sure we can read it like this
				if ( !file_exists($url) ){
					WiziappLog::getInstance()->write('WARNING', 'Local file: '.$url.' but does not exists? will try access by url if the blogs allows', 'WiziappImageHandler.wiziapp_getResizedImage');
					if ( ini_get('allow_url_fopen') == '1' ){
						$url = str_replace(WIZI_ABSPATH, WiziappContentHandler::getInstance()->get_blog_property('url'), $url);
					} else {
						WiziappLog::getInstance()->write('WARNING', 'allow_url_fopen is off, the '.$url.' will most likely fail to load', 'WiziappImageHandler.wiziapp_getResizedImage');
					}
				}
			}

			WiziappLog::getInstance()->write('INFO', 'Calling handler resize on::'.$url, 'WiziappImageHandler.wiziapp_getResizedImage');
			if ( $is_featured_post_thumb ){
				$this->handler->load($url);
			} else {
				$url = $this->handler->resize($url, $cacheFile, $width, $height, $type, $allow_up, $this->checkPath());
			}
			WiziappLog::getInstance()->write('INFO', "After resizing image: " . $this->imageFile.' url:: '.$url, "image_resizing.getResizedImage");
		}

		// $thumb = PhpThumbFactory::create($url);
		// $thumb->show();

		if ( $url === FALSE || strlen($url) > 0 ){
			WiziappLog::getInstance()->write('INFO', "Trying to show the image: " . $this->imageFile.' url:: '.$url, "image_resizing.getResizedImage");
			$this->handler->show();
			WiziappLog::getInstance()->write('INFO', "The image was sent to the browser: " . $this->imageFile.' url:: '.$url, "image_resizing.getResizedImage");
		} else {
			WiziappLog::getInstance()->write('INFO', "There was some kind of problem processing the image: " . $this->imageFile.' url:: '.$url, "image_resizing.getResizedImage");
			// If the image is not local, just redirect to it
			if ( strpos($this->imageFile, 'https://') !== FALSE || strpos($this->imageFile, 'http://') !== FALSE ){
				WiziappLog::getInstance()->write('INFO', "The image is a full url so will just try to redirect to it: " . $this->imageFile, "image_resizing.getResizedImage");
				header('Location: '.$this->imageFile);
				// On this special case we need to halt the functions from moving on
				exit;
			}
		}

		// If we show the image it means the output was sent and we should stop the request
		return true;

		// $imginfo = getimagesize($url);
		// header("Content-Type: " . $imginfo['mime']);
		// return file_get_contents($url);
	}

	public function load(){
		if ($this->handler == null){
			WiziappLog::getInstance()->write('error', 'No images handler', 'WiziappImageHandler.wiziapp_getResizedImage');
			return FALSE;
		}

		/**
		* If the image is local, use the local path.
		* If we will access via the url we might end up stuck with allow_url_open off
		*/
		$imagePath = $this->imageFile;
		$calcResize = TRUE; // Try to calc the size of the image, unless remote and allow_url_fopen is off
		if ( strpos($imagePath, WiziappContentHandler::getInstance()->get_blog_property('url')) === 0 ){
			WiziappLog::getInstance()->write('INFO', 'Loading local image::'.$imagePath, 'WiziappImageHandler.load');
			$imagePath = str_replace(WiziappContentHandler::getInstance()->get_blog_property('url'), WIZI_ABSPATH, $imagePath);

			if ( ! file_exists($imagePath) ){
				// We can not read this image file, if the filename is with special encoding, the os might not find it...
				WiziappLog::getInstance()->write('WARNING', 'Local image::'.$imagePath.' does not exists? Avoid calculations', 'WiziappImageHandler.load');
				if ( ini_get('allow_url_fopen') != '1' ){
					$calcResize = FALSE;
				}

				$imagePath = str_replace(WIZI_ABSPATH, WiziappContentHandler::getInstance()->get_blog_property('url'), $imagePath);
			}
		} elseif ( ini_get('allow_url_fopen') != '1' ){
			// The image is not local, if allow_url_fopen is off throw an alert
			// Will affect the ability to make the image a thumbnail
			$calcResize = FALSE;
			WiziappLog::getInstance()->write('ERROR', "allow_url_fopen is turned off, can't check the image size for: " . $imagePath, "WiziappImageHandler.load");
		}

		WiziappLog::getInstance()->write('INFO', 'Going to request loading the image::'.$imagePath.' from the image handler', 'WiziappImageHandler.load');
		$this->handler->load($imagePath, $calcResize);
	}

	public function getNewWidth(){
		if ($this->handler == null){
			WiziappLog::getInstance()->write('error', 'No images handler', 'WiziappImageHandler.wiziapp_getResizedImage');
			return false;
		}

		$width = $this->handler->getNewWidth();
		if ($width == 0){
			$width = "auto";
		}

		return $width;
	}

	public function getNewHeight(){
		if ($this->handler == null){
			WiziappLog::getInstance()->write('error', 'No images handler', 'WiziappImageHandler.wiziapp_getResizedImage');
			return false;
		}

		$height = $this->handler->getNewHeight();
		if ($height == 0){
			$height = "auto";
		}

		return $height;
	}


	private function _cacheExists($cacheFile){
		return file_exists($cacheFile);
	}

	/**
	* Check which implementation is available on this server
	* Fallback to wiziapp global services if nothing local was found
	*/
	private function _checkImp(){
		if ( extension_loaded('gd') || extension_loaded('imagick') ){
			$this->imp = 'PhpThumb';
		}
	}
}
