<?php if (!defined('WP_WIZIAPP_BASE')) exit();
/**
* @package WiziappWordpressPlugin
* @subpackage contentDisplay
* @author comobix.com plugins@comobix.com
*
*/

class WiziappTheme{
	private static $postHeaders = array();
	/**
	* This function sets the post headers needed by the application
	* the headers describes the post information that needed in
	* the favorite screen and in the tab bar like navigation and such
	*
	* @param boolean $set_header    A flag indicating if the function
	*                               should set the headers or return
	*                               an array.
	* @return array the post header array
	*/
	public static function getPostHeaders($set_header=false){
		global $post;
		if (empty(self::$postHeaders[$post->ID])){
			self::$postHeaders[$post->ID] = self::doPostHeaders();
		}
		$headers = self::$postHeaders[$post->ID];

		if ($set_header) {
			$params = array();
			foreach($headers as $key => $value){
				$params[] = "{$key}={$value}";
			}
			header('content_meta_data: ' . implode('&', $params));
			WiziappLog::getInstance()->write('DEBUG', 'Headers in place: ' . print_r($headers, TRUE), 'theme_functions.wiziapp_do_content_get_post_headers');
		}

		return $headers;
	}

	private static function doPostHeaders(){
		global $post, $wp_query;

		$authorId = $post->post_author;
		$authorInfo = get_userdata($authorId);
		$authorName = $authorInfo->display_name;
		if (strlen($authorName) > 15){
			$authorName = substr($authorName, 0, 12) . '...';
		}

		$totalComments = $post->comment_count;
		$postDate = WiziappTheme::formatDate(strip_tags($post->post_date));
		$imageURL = WiziappThumbnailHandler::getPostThumbnail($post, 'posts_thumb');

		$c = new WiziappPostDescriptionCellItem('L1', array(), FALSE);
		$default_class = $c->getDefaultClass();

		$showCommentsIcon = TRUE;
		if ( $totalComments==0 && ('open' != $post->comment_status) )  {
				$showCommentsIcon = FALSE;
		} else {
			// Might be turned off due to the theme settings
			if ( isset($c->themeRemoveAttr) ){
				if ( in_array('numOfComments', $c->themeRemoveAttr) ){
					$showCommentsIcon = FALSE;
				}
			}
		}

		$header = array(
			"layout" => "L0", // dynamic layout
			"class" => "{$default_class}",
			"nextURL" => WiziappLinks::adjacent_post_url(FALSE, '', FALSE),
			"prevURL" => WiziappLinks::adjacent_post_url(FALSE, '', TRUE),
			"postID" => $post->ID,
			"title" => strip_tags($post->post_title),
			'screenTitle' => WiziappConfig::getInstance()->app_name,
			"imageURL" => $imageURL,
			"canComment" => ('open' == $post->comment_status),
			"showCommentsIcon" => $showCommentsIcon,
		);

		if ( isset($c->themeRemoveAttr) ){
			if ( !in_array('author', $c->themeRemoveAttr) ){
				$header['author'] =  "{$authorName}";
			}
			if ( !in_array('date', $c->themeRemoveAttr) ){
				$header['date'] =  "{$postDate}";
			}
			if ( !in_array('numOfComments', $c->themeRemoveAttr) ){
				$header['numOfComments'] =  $totalComments;
			}
			if ( !in_array('pages', $c->themeRemoveAttr) ){
				$header['pages'] =  TRUE;
			}
		}


		return $header;
	}

	public static function getVideoHeaders($title){
		$header = array(
			"screenTitle" => strip_tags(self::applyRequestTitle($title)),
		);

		return $header;
	}

	public static function applyRequestTitle($default=''){
		$title = $default;
		if ( isset($_GET['wizi_title']) ){
			// It might have been encoded twice, so decode twice
			$title = urldecode(urldecode($_GET['wizi_title']));
		}
		return $title;
	}

	/**
	* Format the date into a human friendly string
	*
	* @todo decide if we need to add months to the friendly string
	* @param string $date
	* @return string
	*/
	public static function formatDate($date){
		$timestamp = current_time('timestamp');
		$dateStr = date('M, j, y', strtotime($date));

		if(gmdate('Y', $timestamp) != mysql2date('Y', $date, FALSE) && !empty($date)) {
			// More then a year, should be a default display
		} else {
			$diff = (gmdate('z', $timestamp) - mysql2date('z', $date, FALSE));
			if($diff < 0) {
				$diff = 32;
			}

			//$dateStr = date(get_option('date_format'), strtotime($date));
			$dateStr = date('M, j, y', strtotime($date));

			if($diff == 0) {
				$dateStr =  __('Today', 'wiziapp');
			} elseif($diff == 1) {
				$dateStr =  __('Yesterday', 'wiziapp');
			} elseif($diff < 7) {
				$dateStr =  sprintf(_n('%s day ago', '%s days ago', $diff), number_format_i18n($diff));
			} elseif($diff < 31) {
				$dateStr =  sprintf(_n('%s week ago', '%s weeks ago', ceil($diff / 7)), number_format_i18n(ceil($diff / 7)));
			}
		}

		return $dateStr;
	}

	/**
	* Will echo the li item for the tags of the current post
	* No categories - no output
	*/
	public static function getTagsNav(){
		global $post;
		$tags = get_the_tags($post->ID);
		$count = count($tags);

		if ( ! $tags){
			return;
		}

		if ($count > 1){
			$link = WiziappLinks::postTagsLink($post->ID);
			$tag_names = $count . ' ' . __('Tags', 'wiziapp');
		} else {
			$link = WiziappLinks::tagLink($tags[key($tags)]->term_id);
			$tag_names = WiziappHelpers::makeShortString($tags[key($tags)]->name, 18);
		}

		?>
		<li>
			<a id="wizapp_tags_footer_link" class="wiziapp_footer_links" href="<?php echo $link; ?>" data-transition="slide">
				<div class="title-bar"></div>
				<span class="title"><?php echo __('Tags', 'wiziapp'); ?></span>

				<span class="names">: <?php echo $tag_names; ?></span>
			</a>
		</li>
		<?php
	}

	/**
	* Will echo the li item for the categories of the current post
	* No categories - no output
	*/
	static function getCategoriesNav(){
		global $post;
		$categories = get_the_category($post->ID);
		$count = count($categories);

		if ( ! $categories || ( $count === 1 && $categories[0]->cat_name == 'Uncategorized' ) ){
			// Do nothing, if the only category we have is uncategorized, we will not show anything
			return;
		}

		if ($count > 1){
			$link = WiziappLinks::postCategoriesLink($post->ID);
			$cat_names = $count . ' ' . __('Categories', 'wiziapp');
		} else {
			$link = WiziappLinks::categoryLink($categories[0]->cat_ID);
			$cat_names = WiziappHelpers::makeShortString($categories[0]->cat_name, 13);
		}

		?>
		<li class="categories">
			<a id="wizapp_cats_footer_link" class="wiziapp_footer_links" href="<?php echo $link; ?>" data-transition="slide">
				<div class="title-bar"></div>
				<span class="title"><?php echo __('Categories', 'wiziapp'); ?></span>
				<span class="names">: <?php echo $cat_names; ?></span>
			</a>
		</li>
		<?php
	}
}