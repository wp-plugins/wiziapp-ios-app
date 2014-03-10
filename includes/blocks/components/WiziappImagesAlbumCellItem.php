<?php
/**
* The images album cell item component
*
* The component knows how to return: title, images array, numOfImages, actionURL
*
* @package WiziappWordpressPlugin
* @subpackage UIComponents
* @author comobix.com plugins@comobix.com
*/
class WiziappImagesAlbumCellItem extends WiziappLayoutComponent{
	/**
	* The attribute map
	*
	* @var array
	*/
	var $attrMap = array(
		'L1' => array('title', 'images', 'numOfImages', 'actionURL'),
		'L2' => array('title', 'imageURL', 'numOfImages', 'actionURL'),
	);

	/**
	* The css classes to attach to the component according to the layout
	*
	* @var mixed
	*/
	var $layoutClasses = array(
		'L1' => 'album_item',
		'L2' => 'album_item'
	);

	/**
	* The base name of the component, the application knows the component by this name
	*
	* @var string
	*/
	var $baseName = 'imagesAlbumCellItem';

    public $htmlTemplate = '<li class="imagesAlbumCellItem cellItem default">
                            <a href="__ATTR_actionURL__" class="actionURL __ATTR_class__" data-transition="slide">
                                <span class="attribute text_attribute imageURL __ATTR_class___image" data-image-src="__ATTR_imageURL__">
                                    <img class="hidden" src="" data-class="__ATTR_class___image"/>
                                </span>
                                <span class="__ATTR_classOf-title__ attribute text_attribute title">__ATTR_title__</span>
                                <span class="__ATTR_classOf-numOfImages__ numOfImages attribute text_attribute">__ATTR_numOfImages__</span>
                             </a>
                            </li>';

	/**
	* constructor
	*
	* @uses WiziappLayoutComponent::init()
	*
	* @param string $layout the layout name
	* @param array $data the data the components relays on
	* @return WiziappImagesAlbumCellItem
	*/
	function WiziappImagesAlbumCellItem($layout='L1', $data){
		if ( count($data[0]['images']) < 2 ){
			return FALSE;
		}
		parent::init($layout, $data);
	}

	/**
	* Attribute getter method
	*
	* @return the id of the component
	*/
	function get_id_attr(){
		return "album_{$this->data[0]['postID']}_{$this->data[0]['id']}";
	}

	/**
	* Attribute getter method
	*
	* @return the title of the component
	*/
	function get_title_attr(){
		return $this->data[0]['name'];
	}

	/**
	* Attribute getter method
	*
	* @return the images array of the component
	*/
	function get_images_attr(){
		$images = array();
		if ( !empty($this->data[0]['images']) && !empty($this->data[0]['images'][0])) {
			if ( $this->layout == 'L2')
				$images = $this->data[0]['images'];
		}
		return $images;
	}

	function get_imageURL_attr(){
		WiziappLog::getInstance()->write('DEBUG', "The preview image is:" . $this->data[0]['images'][0],
						'imageGalleryCellItem.get_imageURL_attr');
		$image = new WiziappImageHandler($this->data[0]['images'][0]);
		$size = WiziappConfig::getInstance()->getImageSize('album_thumb');
		return $image->getResizedImageUrl($this->data[0]['images'][0], $size['width'], $size['height']);

		//return $this->data[0]['images'][0];
	}

	/**
	* Attribute getter method
	*
	* @return the numOfImages of the component
	*/
	function get_numOfImages_attr(){
		return "{$this->data[0]['numOfImages']} " . __(' photos');
	}

	/**
	* Attribute getter method
	*
	* @return the actionURL of the component
	*/
	function get_actionURL_attr(){
		$url = '';
		if ( $this->data[0]['plugin'] == 'bypost' ){
			$url = WiziappLinks::postImagesGalleryLink($this->data[0]['content_id']).urlencode('&album=true');
		} else {
			$url = WiziappLinks::pluginAlbumLink($this->data[0]['plugin'], $this->data[0]['id']);
		}
		return $url;
	}
}
