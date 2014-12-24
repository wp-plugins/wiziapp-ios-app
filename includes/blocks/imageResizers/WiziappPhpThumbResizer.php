<?php
/**
* @todo sync this with the same code part in the global services
*
* @package WiziappWordpressPlugin
* @subpackage MediaUtils
* @author comobix.com plugins@comobix.com
*/

class WiziappPhpThumbResizer{
	private $newHeight = 0;
	private $thumb = null;
	private $newWidth = 0;

	public function getNewWidth(){
		return $this->newWidth;
	}

	public function getNewHeight(){
		return $this->newHeight;
	}

	public function load($image, $calc_size = TRUE){
		$basePath = WIZI_DIR_PATH . 'includes/libs/';
		require_once $basePath . 'phpThumb/ThumbLib.inc.php';

		WiziappLog::getInstance()->write('INFO', 'Before thumb create: ' . $image, 'WiziappPhpThumbResizer.load');

		try {
			$thumb = PhpThumbFactory::create($image);
			WiziappLog::getInstance()->write('INFO', 'Thumb object created: ' . $image, 'WiziappPhpThumbResizer.load');
			$this->thumb = $thumb;
			$size = $thumb->getCurrentDimensions();
		} catch (Exception $e) {
			WiziappLog::getInstance()->write('ERROR', 'GD failed to create or get size of image for thumb, error: ' . $e->getMessage(), 'WiziappPhpThumbResizer.load');
		}

		WiziappLog::getInstance()->write('INFO', 'After thumb create: ' . $image . ' with size: ' . print_r($size, TRUE), 'WiziappPhpThumbResizer.load');

		if ($calc_size){
			$this->newHeight = $size['height'];
			$this->newWidth = $size['width'];
		}
	}

	public function resize($image, $file, $width, $height, $type, $allow_up = false, $save_image = true){
		WiziappLog::getInstance()->write('INFO', 'About to resize the image: ' . $image, 'WiziappPhpThumbResizer.resize');
		$basePath = WIZI_DIR_PATH . 'includes/libs/';
		$url = '';
		require_once $basePath . 'phpThumb/ThumbLib.inc.php';
		$options = array();
		if ($allow_up){
			$options['resizeUp'] = true;
		}

		try {
			WiziappLog::getInstance()->write('INFO', 'Before thumb resize: ' . $image, 'WiziappPhpThumbResizer.resize');
			$thumb = PhpThumbFactory::create($image, $options);
			WiziappLog::getInstance()->write('INFO', 'Thumb object created: ' . $image, 'WiziappPhpThumbResizer.resize');
			//$thumb->$type($width, $height);
			if ( $type == 'perspectiveResize' ){
				$type = 'resize';
				$dim = $thumb->getCurrentDimensions();
				$currWidth = $dim['width'];
				$currHeight = $dim['height'];
				if ( $currWidth > $currHeight ){
					// This is a wide image, make sure the height will fit
					$width = ceil(($height / $currHeight) * $currWidth);
				} else {
					// This is a high image, make sure the width will fit
					$height = ceil(($width / $currWidth) * $currHeight);

				}
				WiziappLog::getInstance()->write('INFO', "Resizing from width: {$currWidth} to: {$width} and from height: {$currHeight} to: {$height}",
					'WiziappPhpThumbResizer.resize');
			} else {
				if ($height == 0){
					$type = 'resize';
					// Calc the new height based of the need to resize
					$dim = $thumb->getCurrentDimensions();
					$currWidth = $dim['width'];
					$currHeight = $dim['height'];

					if ($currWidth > $width){
						$height = ($width / $currWidth) * $currHeight;
					} else {
						$height = $currHeight;
					}

					WiziappLog::getInstance()->write('INFO', "Resizing from width: {$currWidth} to: {$width} and therefore from height: {$currHeight} to: {$height}",
						'WiziappPhpThumbResizer.resize');
				} elseif ($width == 0) {
					$type = 'resize';
					// Calc the new height based of the need to resize
					$dim = $thumb->getCurrentDimensions();
					$currWidth = $dim['width'];
					$currHeight = $dim['height'];

					if ($currHeight > $height){
						$width = ($height / $currHeight) * $currWidth;
					} else {
						$width = $currWidth;
					}

					WiziappLog::getInstance()->write('INFO', "Resizing from height: {$currHeight} to: {$height} and therefore from width: {$currWidth} to: {$width}",
						'WiziappPhpThumbResizer.resize');
				}
			}

			WiziappLog::getInstance()->write('INFO', 'About to resize' . $image.' with:: '.$type, 'WiziappPhpThumbResizer.resize');
			$thumb->$type($width, $height);
			WiziappLog::getInstance()->write('INFO', 'Resized object for: ' . $image.' is ready', 'WiziappPhpThumbResizer.resize');

			$size = $thumb->getCurrentDimensions();
			$this->newHeight = $size['height'];
			$this->newWidth  = $size['width'];

			$this->thumb = $thumb;

			if ( $save_image ){
				WiziappLog::getInstance()->write('INFO', 'Saving the image ' . $image.' to the file:: '.$file, 'WiziappPhpThumbResizer.resize');
				$thumb->save($file);

				// Convert the cache filesystem path to a public url
				$url = str_replace(WIZI_ABSPATH, WiziappContentHandler::getInstance()->get_blog_property('url') . '/', $file);
				$url = str_replace('\\', '/', $url);
			} else {
				$url = FALSE;
			}

			WiziappLog::getInstance()->write('INFO', 'After thumb resize: ' . $image, 'WiziappPhpThumbResizer.resize');
		} catch (Exception $e) {
			WiziappLog::getInstance()->write('ERROR', 'Error resizing: ' . $e->getMessage(), 'WiziappPhpThumbResizer.resize');
		}

		WiziappLog::getInstance()->write('INFO', 'Done resizing the image::' . $image.' the url should be:: '.$url, 'WiziappPhpThumbResizer.resize');
		return $url;
	}

	public function show(){
		if ($this->thumb != null){
			try {
				$this->thumb->show();
			} catch (RuntimeException $e) {
				WiziappLog::getInstance()->write('DEBUG', $e->getMessage(), 'WiziappPhpThumbResizer.show');
			}
		} else {
			WiziappLog::getInstance()->write('ERROR', 'can not show blank thumbnail', 'WiziappPhpThumbResizer.show');
		}
	}
}