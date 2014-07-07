<?php if (!defined('WP_WIZIAPP_BASE')) exit();
/**
* @package WiziappWordpressPlugin
* @subpackage UIComponents
* @author comobix.com plugins@comobix.com
*
*/
class WiziappThumbnailHandler{
	private $post;
	private $size;
	private $_thumb_min_size;
	private $singles = array();
	private $_is_featured_post_thumb = FALSE;

	public function __construct($post) {
		$this->_is_featured_post_thumb = ( $_GET['type'] === 'featured_post_thumb' && WiziappContentHandler::getInstance()->isHTML() && ! WiziappContentHandler::getInstance()->isInApp() );
		$this->post = $post;
		$this->size = WiziappConfig::getInstance()->getImageSize($_GET['type']);
		$this->_thumb_min_size = WiziappConfig::getInstance()->thumb_min_size;
	}

	public static function getPostThumbnail($post, $type) {
		$thumb = WiziappContentHandler::getInstance()->get_blog_property('url') . "/?wiziapp/getthumb/" . $post->ID . '&type=' . $type;
		WiziappLog::getInstance()->write('INFO', "Requesting the post thumbnail url: {$thumb}", "WiziappThumbnailHandler.getPostThumbnail");
		return $thumb;
	}

	public function doPostThumbnail() {
		$foundImage = FALSE;
		WiziappLog::getInstance()->write('INFO', "Getting the post thumbnail: {$this->post}", "wiziapp_doPostThumbnail");
		@include_once(ABSPATH . 'wp-includes/post-thumbnail-template.php');

		if ( function_exists('get_the_post_thumbnail') ) {
			// First we try to get the Featured Image (formerly known as Post Thumbnail)
			if ( has_post_thumbnail($this->post) ) {
				$foundImage = $this->_try_featured_image();
			}
		} else {
			WiziappLog::getInstance()->write('WARNING', "get_the_post_thumbnail method does not exists", "wiziapp_doPostThumbnail");
		}

		if ( ! $foundImage) {
			// If no a Featured Image (formerly known as Post Thumbnail), we take the thumb from a gallery.
			$foundImage = $this->_tryGalleryThumbnail();
		}

		if ( ! $foundImage ) {
			// If no thumb from a gallery, we take the thumb from a video.
			$foundImage = $this->_tryVideoThumbnail();
		}

		if ( ! $foundImage ) {
			// If no thumb from a video, we take the thumb from a single image.
			$foundImage = $this->_trySingleImageThumbnail();
		}

		if ( $foundImage ) {
			return;
		}

		// We couldn't find a thumbnail.
		header("HTTP/1.0 404 Not Found");
	}

	private function _try_featured_image() {
		$featured_image_id = get_post_thumbnail_id($this->post);
		$wpSize = array(
			$this->size['width'],
			$this->size['height'],
		);
		$image = wp_get_attachment_image_src($featured_image_id, $wpSize);
		WiziappLog::getInstance()->write('INFO', "Got WP FEATURED IMAGE thumbnail id: {$featured_image_id} attachment: {$image[0]} for post: {$this->post}", "WiziappThumbnailHandler._try_featured_image");
		$showedImage = $this->_processImageForThumb($image[0], FALSE);

		if ($showedImage) {
			WiziappLog::getInstance()->write('INFO', "Found and will use WP FEATURED IMAGE thumbnail: {$image[0]} for post: {$this->post}", "WiziappThumbnailHandler._try_featured_image");
		} else {
			WiziappLog::getInstance()->write('INFO', "Will *NOT* use WP FEATURED IMAGE thumbnail for post: {$this->post}", "WiziappThumbnailHandler._try_featured_image");
		}

		return $showedImage;
	}

	private function _tryGalleryThumbnail() {
		$post_media = WiziappDB::getInstance()->find_post_media($this->post, 'image');
		$showedImage = FALSE;

		if (!empty($post_media)) {
			$singlesCount = count($this->singles);
			$galleryCount = 0;
			foreach($post_media as $media) {
				$encoding = get_bloginfo('charset');
				$dom = new WiziappDOMLoader($media['original_code'], $encoding);
				$tmp = $dom->getBody();
				$attributes = (object) $tmp[0]['img']['attributes'];

				$info = json_decode($media['attachment_info']);
				if (!isset($info->metadata)) { // Single image
					if ($singlesCount < WiziappConfig::getInstance()->max_thumb_check) {
						WiziappLog::getInstance()->write('INFO', "Found SINGLE IMAGE {$attributes->src} for post: {$this->post}, and will put aside for use if needed.", "WiziappThumbnailHandler._tryGalleryThumbnail");
						$this->singles[] = $attributes->src;
						++$singlesCount;
					}
				} else {
					if ($galleryCount < WiziappConfig::getInstance()->max_thumb_check) {
						if ($showedImage = $this->_processImageForThumb($attributes->src)) {
							WiziappLog::getInstance()->write('INFO', "Found and will use GALLERY thumbnail for post: {$this->post}", "WiziappThumbnailHandler._tryGalleryThumbnail");
							return $showedImage;
						}
						++$galleryCount;
					}
				}
			}
		} else {
			WiziappLog::getInstance()->write('INFO', "No GALLERY/SINGLE IMAGE found for post: {$this->post}", "WiziappThumbnailHandler._tryGalleryThumbnail");
		}
		return $showedImage;
	}

	private function _tryVideoThumbnail() {
		$showedImage = FALSE;
		$post_media = WiziappDB::getInstance()->find_post_media($this->post, 'video');
		if (!empty($post_media)) {
			$media = $post_media[key($post_media)];
			$info = json_decode($media['attachment_info']);
			if (intval($info->bigThumb->width) >= ($this->size['width'] * 0.8)) {
				$image = new WiziappImageHandler($info->bigThumb->url);
				$showedImage = $image->wiziapp_getResizedImage($this->size['width'], $this->size['height'], 'adaptiveResize', true);
				WiziappLog::getInstance()->write('INFO', "Found and will use VIDEO thumbnail for post: " . $this->post, "WiziappThumbnailHandler._tryVideoThumbnail");
			}
		} else {
			WiziappLog::getInstance()->write('INFO', "No VIDEO found for post: {$this->post}", "WiziappThumbnailHandler._tryVideoThumbnail");
		}

		return $showedImage;
	}

	private function _trySingleImageThumbnail() {
		foreach($this->singles as $single) {
			if ( $this->_processImageForThumb($single) ) {
				return TRUE;
			}
		}

		return FALSE;
	}

	private function _processImageForThumb($src, $check_size = TRUE) {
		if ( empty($src) ) {
			return FALSE;
		}

		$image = new WiziappImageHandler($src);
		$image->load();
		$width  = intval($image->getNewWidth());
		$height = intval($image->getNewHeight());

		$not_passed = $check_size && ! $this->_is_featured_post_thumb && (
			empty($width) || empty($height) ||
			( $width  < $this->_thumb_min_size ) ||
			( $height < $this->_thumb_min_size ) ||
			( $width  < ($this->size['width']  * 0.8) ) ||
			( $height < ($this->size['height'] * 0.8) ) );
		if ($not_passed) {
			return FALSE;
		}

		try {
			$image->wiziapp_getResizedImage( $this->size['width'], $this->size['height'], 'adaptiveResize', TRUE, $this->_is_featured_post_thumb );
		} catch (Exception $e) {
			WiziappLog::getInstance()->write('ERROR', $e->getMessage(), "WiziappThumbnailHandler._processImageForThumb");
			return FALSE;
		}

		return TRUE;
	}
}