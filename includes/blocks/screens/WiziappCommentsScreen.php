<?php
/**
* @package WiziappWordpressPlugin
* @subpackage Display
* @author comobix.com plugins@comobix.com
*
*/

class WiziappCommentsScreen extends WiziappBaseScreen{
	protected $name = 'comments';
	protected $type = 'list';

	public function run(){}

	// Removes the actionUrl, the '>' image and the 'hand' cursor from comments that do not have descendants
	function fixCommentsDisplay($allSection, $comments) {
		$comments_count = count($comments);
		$approved_comments_count = count($allSection['section']['items']);

		for($j = 0; $j < $approved_comments_count; $j++) {
			for($i = 0; $i < $comments_count; $i++) {
				if (intval($comments[$i]->comment_parent) == $allSection['section']['items'][$j]['commentCellItem']['commentID']) {
					if (strpos($allSection['section']['items'][$j]['commentCellItem']['class'], 'comment-yes-action') === false) {
						$allSection['section']['items'][$j]['commentCellItem']['class'] = 'comment-yes-action ' . $allSection['section']['items'][$j]['commentCellItem']['class'];
					}
				}
			}
		}
		for($k = 0; $k < $approved_comments_count; $k++) {
			if (strpos($allSection['section']['items'][$k]['commentCellItem']['class'], 'comment-yes-action') === false) {
				$allSection['section']['items'][$k]['commentCellItem']['actionURL'] = '';
				$allSection['section']['items'][$k]['commentCellItem']['class'] = 'comment-no-action ' . $allSection['section']['items'][$k]['commentCellItem']['class'];
			}
		}

		return $allSection;
	}

	// Removes the actionUrl, the '>' image and the 'hand' cursor from parent comments, in its own screen
	function clearCommentsDisplay($parentSection) {
		$approved_comments_count = count($parentSection['section']['items']);

		for($k = 0; $k < $approved_comments_count; $k++) {
			$parentSection['section']['items'][$k]['commentCellItem']['actionURL'] = '';
			//$parentSection['section']['items'][$k]['commentCellItem']['class'] = 'comment-no-action ' . $parentSection['section']['items'][$k]['commentCellItem']['class'];
		}

		return $parentSection;
	}

	// @todo: Add paging support here
	public function runByPost($post_id){
		$screen_conf = $this->getConfig();

		$comments = get_approved_comments($post_id);
		$comments_ammount = count($comments);

		$allSection = array(
			'section' => array(
				'title' => '',
				'id' => 'all_comments',
				'items' => array(),
			)
		);

		if ( $comments_ammount > 0 ){
			for ( $i = 0; $i < $comments_ammount; $i++){
				// Only add top level comments unless told otherwise
				if ( $comments[$i]->comment_parent == 0 ){
					$this->appendComponentByLayout($allSection['section']['items'], $screen_conf['items'], $comments[$i]);
				}
			}
		} elseif ( WiziappContentHandler::getInstance()->isHTML() ){
			$allSection['section']['items'] = array( WiziappCommentCellItem::get_welcome_screen() );
		}

		$title = __('Comments', 'wiziapp');

		$screen = $this->prepareSection(array($allSection), $title, "List", false, false, 'comments_screen');
		$screen['post_id'] = $post_id;
		$post = get_post($post_id);
		$this->output($screen, array('url' => WiziappLinks::postLink($post_id), 'text' => $post->post_title));
	}

	public function runBySelf($comment){
		if ( is_string($comment) ){
			$temporal_comment = new stdClass;

			$temporal_comment->comment_ID = '0';
			$temporal_comment->comment_post_ID = '0';
			$temporal_comment->comment_author = '';
			$temporal_comment->comment_author_email = '';
			$temporal_comment->comment_author_url = '';
			$temporal_comment->comment_author_IP = '';
			$temporal_comment->comment_date = '';
			$temporal_comment->comment_date_gmt = '';
			$temporal_comment->comment_content = $comment;
			$temporal_comment->comment_karma = '0';
			$temporal_comment->comment_approved = '0';
			$temporal_comment->comment_agent = '';
			$temporal_comment->comment_type = '';
			$temporal_comment->comment_parent = '0';
			$temporal_comment->user_id = '0';

			$comment = $temporal_comment;
		}

		$screen_conf = $this->getConfig();

		$parentCommentSection = array(
			'section' => array(
				'title' => '',
				'id'    => 'parent_comment',
				'items' => array(),
			)
		);

		$subCommentsSection = array(
			'section' => array(
				'title' => '',
				'id'    => 'subComments',
				'items' => array(),
			)
		);

		$comment_parent = get_comment($comment->comment_parent);
		$this->appendComponentByLayout($parentCommentSection['section']['items'], $screen_conf['items'], $comment_parent);

		$this->appendComponentByLayout($subCommentsSection['section']['items'], $screen_conf['items'], $comment);

		$title = __("Comments", 'title');

		$screen = $this->prepareSection(array($parentCommentSection, $subCommentsSection), $title, "List");

		$this->output($screen);
		exit;
	}

	function runByComment($params){
		$post_id = $params[0];
		$p_comment_id = $params[1];
		$screen_conf = $this->getConfig('sub_list');

		$comments = get_approved_comments($post_id);
		// First add the parent comment to the list
		$parentCommentSection = array(
			'section' => array(
				'title' => '',
				'id'    => 'parent_comment',
				'items' => array(),
			)
		);

		$subCommentsSection = array(
			'section' => array(
				'title' => '',
				'id'    => 'subComments',
				'items' => array(),
			)
		);

		if ( ! WiziappContentHandler::getInstance()->isHTML() ){
			$comment = get_comment($p_comment_id);
			$this->appendComponentByLayout($parentCommentSection['section']['items'], $screen_conf['header'], $comment);
		}

		foreach($comments as $comment){
			// Only add top level comments unless told otherwise
			if ( $comment->comment_parent == $p_comment_id ){
				$this->appendComponentByLayout($subCommentsSection['section']['items'], $screen_conf['items'], $comment);
			}
		}

		$title = __("Comments", 'title');

		if ( ! WiziappContentHandler::getInstance()->isHTML() ){
			// We will remove the display child elements from the parent, because we are watching them in this screen
			$parentCommentSection = $this->clearCommentsDisplay($parentCommentSection);
		}

		//$subCommentsSection = $this->fixCommentsDisplay($subCommentsSection, $comments);

		if ( WiziappContentHandler::getInstance()->isHTML() ){
			$screen = $this->prepareSection(array($subCommentsSection,), $title, "List");
		} else {
			$screen = $this->prepareSection(array($parentCommentSection, $subCommentsSection), $title, "List");
		}
		$comment = get_comment($p_comment_id);
		$this->output($screen, array('url' => $comment->comment_parent?WiziappLinks::postCommentSubCommentsLink($post_id, $comment->comment_parent):WiziappLinks::postCommentsLink($post_id), 'text' => $title));
	}

	function runByMyComments($author_id){
		$numberOfPosts = WiziappConfig::getInstance()->comments_list_limit;

		$screen_conf = $this->getConfig('user_list');

		$page = array();

		global $wpdb;

		$key = md5( serialize( "number={$numberOfPosts}&user_id={$author_id}")  );
		$last_changed = wp_cache_get('last_changed', 'comment');
		if ( !$last_changed ) {
			$last_changed = time();
			wp_cache_set('last_changed', $last_changed, 'comment');
		}
		$cache_key = "get_comments:$key:$last_changed";

		if ( $cache = wp_cache_get( $cache_key, 'comment' ) ) {
			$comments = $cache;
		} else {
			$approved = "comment_approved = '1'";
			$order = 'DESC';
			$orderby = 'comment_date_gmt';
			$number = 'LIMIT ' . $numberOfPosts;
			$post_where = "user_id = '{$author_id}' AND ";

			$comments = $wpdb->get_results( "SELECT * FROM $wpdb->comments WHERE $post_where $approved ORDER BY $orderby $order $number" );
			wp_cache_add( $cache_key, $comments, 'comment' );
		}

		foreach($comments as $comment){
			$this->appendComponentByLayout($page, $screen_conf['items'], $comment);
		}

		$title = __('My Comments', 'wiziapp');

		$this->output($this->prepare($page, $title, "List"));
	}

	public function set_error_function() {
		return array(&$this, 'runBySelf');
	}
}