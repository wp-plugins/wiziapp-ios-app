<?php
/**
* The audio cell item component
*
* The component knows how to return: title, author, date, numOfComments, numOfUserComments, imageURL, actionURL, contents, categories, rating
*
* @package WiziappWordpressPlugin
* @subpackage UIComponents
* @author comobix.com plugins@comobix.com
*
*/
class WiziappPostDescriptionCellItem extends WiziappLayoutComponent{
	/**
	* A wordpress processed post
	*
	* @var WP_Post
	*/
	public $post;

	/**
	* The attribute map
	*
	* @var array
	*/
	public $attrMap = array(
		'L1' => array('title', 'author', 'date', 'numOfComments', 'pages', 'imageURL', 'actionURL', 'contents'),
		'L2' => array('title', 'author', 'date', 'numOfComments', 'pages', 'numOfUserComments', 'imageURL', 'actionURL', 'contents'),
		'L3' => array('title', 'author', 'date', 'numOfComments', 'pages', 'categories', 'imageURL', 'actionURL', 'contents'),
		'L4' => array('title', 'date', 'numOfComments', 'pages', 'imageURL', 'actionURL', 'contents'),
		'L5' => array('title', 'author', 'date', 'pages', 'rating', 'imageURL', 'actionURL', 'contents'),
		'L6' => 'L1',
		'L7' => array('title', 'imageURL', 'actionURL', 'contents'),
		'L8' => array('title', 'imageURL', 'actionURL'),
	);

	public $attrIgnoreAddOverride = array(
		'L5' => array('numOfComments'=>FALSE),
	);
	/**
	* The css classes to attach to the component according to the layout
	*
	* @var mixed
	*/
	public $layoutClasses = array(
		'L1' => 'general_post',
		'L2' => 'commented_post',
		'L3' => 'archived_post',
		'L4' => 'user_post',
		'L5' => 'featured_post',
		'L6' => 'video_post',
		'L7' => 'mini_post',
		'L8' => 'mini_post',
	);

	/**
	* Possible images sizes according to the layout
	*
	* @var array
	*/
	public $imageSizes = array(
		'default' => 'posts_thumb',
		'L5' => 'featured_post_thumb',
		'L7' => 'mini_post_thumb',
	);

	/**
	* Possible thumbnail limits according to the layout
	*
	* @var array
	*/
	public $thumbnailLimits = array(
		'default' => 'limit_posts_thumb',
		'L5' => 'limit_featured_post_thumb',
		'L7' => 'limit_mini_post_thumb',
	);

	/**
	* The base name of the component, the application knows the component by this name
	*
	* @var string
	*/
	public $baseName = 'postDescriptionCellItem';

	public $htmlTemplate = '';

	/**
	* @uses WiziappLayoutComponent::init()
	*
	* @param string $layout the layout name
	* @param array $data the data the components relays on
	* @return WiziappPostDescriptionCellItem
	*/
	public function __construct($layout = 'L1', $data, $process = TRUE){
		parent::init($layout, $data, $process);

		ob_start();
		?>
		<li class="postDescriptionCellItem __ATTR_class__ cellItem default" data-post-id="__ATTR_id__">
			<a href="__ATTR_actionURL__" class="actionURL __ATTR_class__" data-ajax="false">
				<div class="attribute imageURL __ATTR_classOf-imageURL-webapp__" data-image-src="__ATTR_imageURL__">
					<img class="hidden ignore_effect" src="<?php echo WiziappHelpers::get_pixelSRC_attr(); ?>" data-class=""/>
				</div>
				<div class="attribute webapp_post_title __ATTR_classOf-title-webapp__">__ATTR_title__</div>
				<span class="attribute __ATTR_classOf-description__">__ATTR_description__</span>
				<span class="attribute __ATTR_classOf-rating__">__ATTR_rating__</span>
				<div class="combined_attributes webapp_post_details">
					<span class="webapp_post_date __ATTR_classOf-date-webapp__">__ATTR_date__</span>
					<span class="webapp_post_date __ATTR_classOf-author-webapp__">__ATTR_author__</span>
				</div>
				<span class="attribute valign_attribute numOfComments __ATTR_classOf-numOfComments-webapp__">__ATTR_numOfComments__</span>
			</a>
		</li>
		<?php
		$this->htmlTemplate = ob_get_clean();
	}

	/**
	* before the wiziappLayoutComponent::process() will kick in, we need to
	* do a bit of processing ourselfs to get the post information from wordpress.
	*
	* @uses wiziappLayoutComponent::process();
	*/
	function process(){
		$this->post = get_post($this->data[0]);
		parent::process();
	}

	/**
	* attribute getter method
	*
	* @todo refactor this, the app will use a dynamic layout called L0, we still need the layout name
	* to be able to connect to the right attributes and class name
	* @returns the layout map for the component
	*/
	function get_layout_attr(){
		return 'L0';
	}

	/**
	* Attribute getter method
	*
	* @return the id rating the component
	*/
	function get_rating_attr(){
		//return round(wiziapp_get_rating($this->data[0]));
		return 0;
	}

	/**
	* Attribute getter method
	*
	* @return the description of the component
	*/
	function get_description_attr(){
		$desc = $this->post->post_excerpt;
		if ( empty($this->post->post_excerpt) ){
			// No need for the full processing... can be quite expensive
			//$desc = wiziapp_process_content($this->post->post_content);

			$desc = preg_replace('/\[(.*?)\]/', '', $this->post->post_content);
		}

		return WiziappHelpers::makeShortString(trim(strip_tags($desc)), 45);
	}

	/**
	* Attribute getter method
	*
	* @return the categories of the component
	*/
	function get_categories_attr(){
		foreach((get_the_category($this->data[0])) as $category) {
			$categories[] = $category->cat_name;
		}

		return implode(",", $categories);
	}

	/**
	* Attribute getter method
	*
	* @return the contents of the component
	*/
	function get_contents_attr(){
		$value = array('headers' => WiziappTheme::getPostHeaders(FALSE), 'data'=>'');
		$contents = $this->data[1];
		if ( $contents != null ){
			$value['data'] = $contents;
		}
		return $value;
	}

	/**
	* Attribute getter method
	*
	* @return the imageURL of the component
	*/
	function get_imageURL_attr(){
		$type = $this->imageSizes['default'];
		if ( isset($this->imageSizes[$this->layout]) ){
			$type = $this->imageSizes[$this->layout];
		}

		return WiziappThumbnailHandler::getPostThumbnail($this->post, $type);
	}

	/**
	* Attribute getter method
	*
	* @return the numOfComments of the component
	*/
	function get_numOfComments_attr(){
		return $this->post->comment_count;
	}

	/**
	* Attribute getter method
	*
	* @return the numOfUserComments of the component
	*/
	function get_numOfUserComments_attr(){
		return $this->data[1];
	}

	/**
	* Attribute getter method
	*
	* @return the date of the component
	*/
	function get_date_attr(){
		$dateStr = WiziappTheme::formatDate(strip_tags($this->post->post_date));
		//return '| '.$dateStr;
		return $dateStr;

	}

	/**
	* Attribute getter method
	*
	* @return the author of the component
	*/
	function get_author_attr(){
		$authorInfo = get_userdata($this->post->post_author);
		$authorName = $authorInfo->display_name;
		if ( strlen($authorName) > 15 ){
			$authorName = substr($authorName, 0, 12).'...';
		}
		$prefix = ' ';
		return "{$prefix}{$authorName}";
	}

	function get_pages_attr(){
		return '';
	}

	/**
	* Attribute getter method
	*
	* @return the id of the component
	*/
	function get_id_attr(){
		return "post_{$this->data[0]}";
	}

	/**
	* Attribute getter method
	*
	* @return the tile of the component
	*/
	function get_title_attr(){
		//return WiziappHelpers::makeShortString(strip_tags($this->post->post_title), 28);
		return strip_tags($this->post->post_title);
	}

	/**
	* Attribute getter method
	*
	* @return the actionURL of the component
	*/
	function get_actionURL_attr(){
		$post_id = $this->data[0];
		$link = WiziappLinks::postLink($post_id);
		if (WiziappContentHandler::getInstance()->isHTML()){
			$add = WiziappPostsScreen::$postLinkAdd;
			if ($add !== false){
				$link .= urlencode(((strpos($link, '%3F') === false)?'?':'&').$add);
			}
		}
		return $link;
	}
}