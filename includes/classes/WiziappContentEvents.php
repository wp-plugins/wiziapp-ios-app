<?php if (!defined('WP_WIZIAPP_BASE')) exit();

/**
* This file contains functions that handle the content from the CMS
*
* @package WiziappWordpressPlugin
* @subpackage Core
* @author comobix.com plugins@comobix.com
*
*/
class WiziappContentEvents{

	public function savePost($post){
		if ( ! is_object($post) ){
			$post_id = $post;
			$post = get_post($post_id);
		} else {
			$post_id = $post->ID;
		}

		$this->updateCacheTimestampKey();

		if (is_object($post) && property_exists($post, 'post_type') && $post->post_type == 'page') {
			$this->save($post_id, 'page');
		} else {
			$this->save($post_id, $post->post_type);
		}
	}

	public function deletePost($post_id){
		$this->updateCacheTimestampKey();
		WiziappDB::getInstance()->delete_content_media($post_id, "post");
	}

	public function recoverPost($post_id){
		$status = get_post_status($post_id);
		if ( $status == 'publish' ){
			$this->updateCacheTimestampKey();

			$this->save($post_id, 'post');
		}
	}

	/**
	* This function allow us to have unique actions for
	* saving comments content. It warps the wiziapp_save_content
	*
	* @param integer $comment_id
	*/
	public function saveComment($comment_id){
		$this->save($comment_id, 'comment');
	}

	function savePage($page_id){
		$this->save($page_id, 'page');
	}

	/**
	* This function prepare the content for processing
	* it will receive the content we need to process and will force running
	* wordpress filters on it. In addition it will call a filter: 'wiziapp_before_the_content'
	* so that unknown 'the_content' filters can be remove before processing to make the content parsing simpler.
	* 'wiziapp_before_the_content' takes one string parameter containing the $content itself and should return it
	*
	* @param string $content the content to process
	* @return string $content the content after processing
	*/
	function process($content){
		/*
		* Some of the filters just echo content instead of adding it to the string...
		* avoid those issues by buffering the output
		*/
		ob_start();

		$ch = WiziappContentHandler::getInstance();
		//$ch->forceInApp();
		$ch->setInSave();

		// Remove the theme settings for now,
		$contentWidth = isset($GLOBALS['content_width']) ? $GLOBALS['content_width'] : null;
		$GLOBALS['content_width'] = 0;

		// We might need to remove some filters to be able to parse the content, if that is the case:
		$content = apply_filters('wiziapp_before_the_content', $content);
		$content = apply_filters('the_content', $content);

		$filteredContent = ob_get_clean();

		$content = $filteredContent . $content;
		$content = str_replace(']]>', ']]&gt;', $content);

		// Restore the theme settings
		if ($contentWidth != null){
			$GLOBALS['content_width'] = $contentWidth;
		}
		return $content;
	}

	/**
	* Saves media found in the requested content in a special media table for later retrieval
	*
	* @param integer $id the content id
	* @param string $type can be post/comment/page
	* @return boolean false on non-approved comment
	*/
	public function save($id, $type="post"){
		if ( !WiziappDB::getInstance()->isInstalled() ){
			// No point in scanning saving the post if we are not fully installed
			return FALSE;
		}

		global $more;

		$more = 1;
		$content = '';

		if ( in_array( $type, WiziappComponentsConfiguration::getInstance()->get_post_types() ) ){
			$postslist = get_posts('include='.$id.'&numberposts=1&post_type='.$type);
			// For posts we need to force the loop
			global $post, $wp_query;
			$wp_query->in_the_loop = true;
			foreach ($postslist as $post){
				setup_postdata($post);
				$content .= get_the_content();
			}
		} elseif ($type == 'comment'){
			$content_item = get_comment($id);
			// Only processed approved comments
			if ($content_item->comment_approved){
				$content = $content_item->comment_content;
			} else {
				return FALSE;
			}
		} elseif ($type == 'page'){
			$content_item = get_page($id);
			$content = $content_item->post_content;
		}

		// Handle the special content processing
		$content = $this->process($content);

		// Support for the videozoom plugin, we want to show the video from the top of the post
		$post_custom = get_post_custom($id);
		if (isset($post_custom['wpzoom_post_embed_code']) && $post_custom['wpzoom_post_embed_code']) {
			$content = $post_custom['wpzoom_post_embed_code'][0] . '<br />' . $content;
		}

		// Remove the existing media related to this post to avoid having duplicates and unrelated leftovers
		WiziappDB::getInstance()->delete_content_media($id, $type);

		// Extract the media items with the media extractor
		$extractor = new WiziappMediaExtractor($content);

		// Save the images
		$images = $extractor->getImages();
		$this->saveMediaDetails('image', $images, $id, $type);

		// Save the videos
		$videos = $extractor->getVideos();
		$this->saveSpecialMediaDetails('video', $videos, $id, $type);

		// Save the audios
		$audios = $extractor->getAudios();
		$this->saveSpecialMediaDetails('audio', $audios, $id, $type);

		// Mark the content as processed to avoid processing when already processed
		if ( in_array( $type, WiziappComponentsConfiguration::getInstance()->get_post_types() ) || $type == 'page') {
			add_post_meta($id, 'wiziapp_processed', true, true);
		}
	}

	/**
	* Preparing the media information to be saved in the database and then triggers the database saving
	*
	* @param string $type the type of media we are saving. can be audio/image/video
	* @param array $items the array of media items we are saving
	* @param integer $content_id the id of the content itself (post/page)
	* @param string $content_type the content type, can be post / page
	* @return result $result
	*/
	public function saveMediaDetails($type, $items, $content_id, $content_type){
		if (count($items) == 0) {
			return FALSE;
		}

		$result = WiziappDB::getInstance()->add_content_medias($type, $items, $content_id, $content_type);
		return $result;
	}

	public function saveSpecialMediaDetails($type, $items, $content_id, $content_type){
		if (count($items) == 0) {
			return FALSE;
		}
		$result = FALSE;

		for($a = 0, $total = count($items); $a < $total; ++$a){
			$obj = $items[$a]['obj'];
			$html = $items[$a]['html'];
			if ($content_type == 'post'){
				$result = WiziappDB::getInstance()->update_post_media($content_id, $type, $obj, $html);
			} elseif ($content_type == 'page'){
				$result = WiziappDB::getInstance()->update_page_media($content_id, $type, $obj, $html);
			}
		}

		return $result;
	}

	/**
	* Called from the install method, to install the base content
	* that will be used at first for the simulator and for building the cms
	* profile
	*
	* After the initial processing the user will be able to trigger the processing
	* via his plugin control panel or when a post is requested for the first time
	*/
	public function generateLatest(){
		global $wpdb;
		$done = false;

		WiziappLog::getInstance()->write('INFO', "Parsing the latest content", "generateLatest.WiziappContentEvents");
		// Parse the latest posts
		$number_recents_posts = WiziappConfig::getInstance()->post_processing_batch_size;
		$recent_posts = wp_get_recent_posts($number_recents_posts);
		$last_post = -1;
		foreach($recent_posts as $post){
			$post_id = $post['ID'];
			WiziappLog::getInstance()->write('INFO', "Processing post: {$post_id}",
				'WiziappContentEvents.generateLatest');
			$this->savePost($post_id);

			$last_post = $post_id;
		}

		WiziappLog::getInstance()->write('INFO', "Processing all pages", 'WiziappContentEvents.generateLatest');
		$pages = get_all_page_ids();
		for($p = 0, $total = count($pages); $p < $total; ++$p){
			$this->savePage($pages[$p]);
		}

		// Save the fact that we processed  $number_recents_posts and if the number of posts
		// in the blog is bigger, we need to continue
		$numposts = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_status = 'publish'");
		if ($numposts <= $number_recents_posts){
			$last_post = -1;
		}
		add_option("wiziapp_last_processed", $last_post);
		WiziappLog::getInstance()->write('INFO', "Finished parsing initial content", 'WiziappContentEvents.generateLatest');

		return $done;
	}

	public function triggerCacheUpdateByProfile($user, $old_user_data){
		$this->updateCacheTimestampKey();
	}

	public function triggerCacheUpdate($option, $oldvalue, $_newvalue){
		// Once per request...
		if (strpos($option, '_') !== 0 && strpos($option, 'wiziapp') === FALSE){
			remove_action('updated_option', array('WiziappContentEvents', 'triggerCacheUpdate'));
			$this->updateCacheTimestampKey();
		}
	}

	public function updateCacheTimestampKey(){
		WiziappConfig::getInstance()->last_recorded_save = time();
	}

	public static function getCacheTimestampKey(){
		return WiziappConfig::getInstance()->last_recorded_save;
	}
}