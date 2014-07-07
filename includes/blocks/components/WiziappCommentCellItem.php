<?php
/**
* The comment cell item component
*
* The component knows how to return:
* commentID, postID, user, content, numOfReplies, date, imageURL, actionURL,
* numOfComments, index
*
* @package WiziappWordpressPlugin
* @subpackage UIComponents
* @author comobix.com plugins@comobix.com
*/
class WiziappCommentCellItem extends WiziappLayoutComponent{
	/**
	* The attribute map
	*
	* @var array
	*/
	var $attrMap = array(
		'L1' => array('commentID', 'user', 'content', 'numOfReplies', 'date', 'imageURL', 'actionURL'),
		'L2' => array('date', 'commentID', 'postID', 'content', 'numOfComments', 'actionURL'),
		//'L3' => array('commentID', 'postID', 'user', 'content', 'numOfReplies', 'date', 'imageURL', 'actionURL'),
		'L3' => array('commentID', 'postID', 'user', 'content', 'numOfReplies', 'date', 'imageURL'),
		'L4' => array('commentID', 'user', 'content', 'numOfReplies', 'date', 'imageURL', 'actionURL'),
	);

	/**
	* The css classes to attach to the component according to the layout
	*
	* @var mixed
	*/
	var $layoutClasses = array(
		'L1' => 'comment',
		'L2' => 'my_comment',
		'L3' => 'parent_comment',
		'L4' => 'sub_comment',
	);

	/**
	* The base name of the component, the application knows the component by this name
	*
	* @var string
	*/
	var $baseName = 'commentCellItem';

	public $htmlTemplate = '';

	/**
	* constructor
	*
	* @uses WiziappLayoutComponent::init()
	*
	* @param string $layout the layout name
	* @param array $data the data the components relays on
	* @return WiziappCommentCellItem
	*/
	function __construct($layout='L1', $data){
		parent::init($layout, $data);

		ob_start();
		?>
		<li data-theme="c" data-comment-id="__ATTR_commentID__">
			<div class="comment_author_avatar">
				<img width="40" height="40" src="__ATTR_imageURL__" alt="" />
			</div>
			<div class="comment_author_details">__ATTR_user__<br />__ATTR_date__</div>
			<div class="comment_replyButton"></div>
			<div class="comment_body">__ATTR_content__</div>
			__ATTR_collapsible_element__
		</li>
		<?php

		$this->htmlTemplate = ob_get_clean();
	}

	function get_collapsible_element_attr(){
		if ( ! isset($this->attributes['numOfReplies']) || ! ( $replies_number = intval($this->attributes['numOfReplies']) ) || ! isset($this->attributes['actionURL'])){
			return;
		}

		$inner_comments_url = urldecode( str_ireplace( 'nav://comments/', '', $this->attributes['actionURL'] ) ).WiziappLinks::getAppend();

		ob_start();
		?>
		<div data-role="collapsible" data-content-theme="c" data-theme="d" data-inner-comments-url="<?php echo $inner_comments_url; ?>">
			<h3 style="margin: 0 -8px;"><?php echo $replies_number; ?> Inner Comments</h3>
		</div>
		<?php

		return ob_get_clean();
	}

	public static function get_welcome_screen(){
		ob_start();

		?>
		<li class="comment_welcome_main">
			<div></div>
		</li>
		<?php

		return ob_get_clean();
	}

	public static function get_comment_form($post_id){
		ob_start();

		?>
		<div class="comment_reply_form">
			<div data-role="fieldcontain">
				<label for="comment_reply_author">Name:</label>
				<input type="text" id="comment_reply_author" placeholder="Required" aria-required="true" />
			</div>

			<div data-role="fieldcontain">
				<label for="comment_reply_email">Email:</label>
				<input type="text" id="comment_reply_email" placeholder="Required" aria-required="true" />
			</div>

			<div data-role="fieldcontain">
				<label for="comment_reply_url">Site:</label>
				<input type="text" id="comment_reply_url" />
			</div>

			<div data-role="fieldcontain" class="ui-hide-label">
				<label for="comment_reply_content">Content:</label>
				<textarea cols="40" rows="8" id="comment_reply_content" placeholder="Type your Comment here" aria-required="true"></textarea>
			</div>

			<input type="hidden" id="comment_form_action" value="<?php echo WiziappContentHandler::getInstance()->get_blog_property('url').'/wp-comments-post.php'.WiziappLinks::getAppend('?'); ?>" />
			<input type="hidden" id="comment_post_ID" value="<?php echo $post_id; ?>" />
			<?php do_action( 'comment_form', $post_id ); ?>

			<input type="button" value="Post Comment" name="submit" data-theme="e" />
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	* Attribute getter method
	*
	* @return the id of the component
	*/
	function get_id_attr(){
		return "comment_{$this->data[0]->comment_ID}";
	}

	/**
	* Attribute getter method
	*
	* @return the postID of the component
	*/
	function get_postID_attr(){
		return (int) $this->data[0]->comment_post_ID;
	}

	/**
	* Attribute getter method
	*
	* @return the user of the component
	*/
	function get_user_attr(){
		return "{$this->data[0]->comment_author} ".__('says:', 'wiziapp');
	}

	/**
	* Attribute getter method
	*
	* @return the date of the component
	*/
	function get_date_attr(){
		//return human_time_diff(strtotime($this->data[0]->comment_date), current_time('timestamp')) . " " . __('ago');
		return $this->data[0]->comment_date;
	}

	/**
	* Attribute getter method
	*
	* @return the numOfReplies of the component
	*/
	function get_numOfReplies_attr(){
		return $this->getSubCommentsCount($this->data[0]->comment_post_ID, $this->data[0]->comment_ID).__(' Replies', 'wiziapp');
	}

	public function getSubCommentsCount($post_id, $comment_id){
		global $wpdb;

		// Ignore the "comment_approved" parameter to allow to see the added inner unapproved comments
		$approved = "comment_approved = '1'";
		$post_where = "comment_post_ID = '{$post_id}' AND comment_parent = '{$comment_id}'"; // AND";
		$count = $wpdb->get_var( "SELECT count(*) FROM $wpdb->comments WHERE $post_where" ); // $approved" );

		return (int)$count;
	}

	/**
	* Attribute getter method
	*
	* @return the content of the component
	*/
	function get_content_attr(){
		$content = strip_tags($this->data[0]->comment_content);
		$content = str_replace(array("\r\n", "\r"), " ", $content);
		return $content;
	}

	/**
	* Attribute getter method
	*
	* @return the commentID of the component
	*/
	function get_commentID_attr(){
		return (int) $this->data[0]->comment_ID;
	}

	/**
	* Attribute getter method
	*
	* @return the imageURL of the component
	*/
	function get_imageURL_attr(){
		$img = get_avatar($this->data[0], WiziappConfig::getInstance()->comments_avatar_height);
		$dom = new WiziappDOMLoader($img, get_bloginfo('charset'));
		$imgArray = $dom->getBody();
		$imageURL = $imgArray[0]['img']['attributes']['src'];
		return $imageURL;
	}

	/**
	* Attribute getter method
	*
	* @return the actionURL of the component
	*/
	function get_actionURL_attr(){
		if ( $this->getSubCommentsCount($this->data[0]->comment_post_ID, $this->data[0]->comment_ID) > 0 ){
			return WiziappLinks::postCommentSubCommentsLink($this->data[0]->comment_post_ID, $this->data[0]->comment_ID);
		} else {
			return '';
		}
	}

	/**
	* Attribute getter method
	*
	* @return the numOfComments of the component
	*/
	function get_numOfComments_attr(){
		$post = get_post($this->data[0]->comment_post_ID);
		return $post->comment_count;
	}
}