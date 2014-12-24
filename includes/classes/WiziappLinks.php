<?php if (!defined('WP_WIZIAPP_BASE')) exit();
/**
* @package WiziappWordpressPlugin
* @subpackage UIComponents
* @author comobix.com plugins@comobix.com
*/

class WiziappLinks{

	public static function authorLink($author_id){
		return 'nav://list/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . "/?wiziapp/content/list/author/{$author_id}/posts");
	}

	public static function postLink($post_id, $styling_page_links = ''){
		//return WiziappContentHandler::getInstance()->get_blog_property('url')."/wiziapp/content/post/{$post_id}";
		$link = urlencode(get_permalink($post_id) . $styling_page_links);
		$url = '';
		if ( !empty($link) ){
			$url = 'nav://post/' . $link;
		}

		return $url;
	}

	public static function adjacent_post_url($in_same_cat, $excluded_categories, $previous){
		$adjacent_post = get_adjacent_post($in_same_cat, $excluded_categories, $previous);

		if ( ! is_object($adjacent_post) || empty($adjacent_post->ID)){
			return '';
		}

		$url = get_permalink( $adjacent_post->ID );

		return 'nav://post/'. urlencode($url);
	}

	public static function pageLink($page_id){
		return 'nav://page/' . urlencode(get_page_link($page_id));
	}

	public static function postLinkFromURL($url){
		return 'nav://post/' . urlencode($url);
	}

	public static function pageLinkFromURL($url){
		return 'nav://page/' . urlencode($url);
	}

	public static function categoryLink($category_id){
		return 'nav://list/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . "/?wiziapp/content/list/category/{$category_id}");
	}

	public static function tagLink($tag_id){
		return 'nav://list/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . "/?wiziapp/content/list/tag/{$tag_id}");
	}

	public static function  postTagsLink($post_id){
		return 'nav://list/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . "/?wiziapp/content/list/post/{$post_id}/tags");
	}

	public static function linksByCategoryLink($cat_id){
		return 'nav://list/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . "/?wiziapp/content/list/links/category/{$cat_id}");
	}

	public static function postCategoriesLink($post_id){
		return 'nav://list/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . "/?wiziapp/content/list/post/{$post_id}/categories");
	}

	public static function postImagesGalleryLink($post_id){
		return 'nav://gallery/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . "/?wiziapp/content/list/post/{$post_id}/images");
	}

	public static function postCommentsLink($post_id){
		//return 'nav://list/'.urlencode(WiziappContentHandler::getInstance()->get_blog_property('url')."/?wiziapp/content/list/post/{$post_id}/comments");
		return 'nav://comments/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . "/?wiziapp/content/list/post/{$post_id}/comments");
	}

	public static function postCommentSubCommentsLink($post_id, $comment_id){
		return 'nav://comments/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . "/?wiziapp/content/list/post/{$post_id}/comments/{$comment_id}");
	}

	/**
	* If the providers won't support mp4 version of the file try the video id will be -1
	*
	*
	* @param mixed $provider
	* @param mixed $video_id
	* @param mixed $url
	* @return string
	*/
	public static function videoLink($provider, $video_id, $url=''){
		$url = urlencode($url);
		return "cmd://open/video/{$provider}/{$video_id}/{$url}";
	}

	public static function audioLink($provider, $url=''){
		$url = rawurlencode($url);
		return "cmd://open/{$provider}/{$url}";
	}

	public static function fixAudioLink($actionURL){
		$url = str_replace('cmd://open/audio/', '', urldecode($actionURL));

		$url = rawurlencode(urldecode($url));
		return "cmd://open/audio/{$url}";
	}

	public static function extractProviderFromVideoLink($link){
		$tmp = str_replace('://', '', $link);
		//$tmp = split('/', $tmp);
		$tmp = explode('/', $tmp);
		return $tmp[2];
	}

	public static function videoPageLink($item_id){
		return "nav://page/" . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . "/?wiziapp/content/video/{$item_id}");
	}

	public static function videoDetailsLink($item_id){
		return "nav://video/" . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . "/?wiziapp/content/list/media/video/{$item_id}");
	}

	public static function archiveYearLink($year){
		return 'nav://list/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . "/?wiziapp/content/list/archive/{$year}");
	}

	public static function archiveMonthLink($year, $month){
		return 'nav://list/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . "/?wiziapp/content/list/archive/{$year}/{$month}");
	}

	public static function pluginAlbumLink($plugin='', $album_id){
		return 'nav://gallery/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . "/?wiziapp/content/list/gallery/{$plugin}/{$album_id}");
	}

	public static function ratingLink(){
		$url = urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . "/?wiziapp/rate/post/");
		return "cmd://openRanking/{$url}";
	}

	public static function moreLink($page){
		// Get the current request url
		$requestUri = $_SERVER['REQUEST_URI'];
		// Isolate wiziapp part of the request
		$wsUrl = substr($requestUri, strpos($_SERVER['QUERY_STRING'], 'wiziapp/'));

		$sep = '&';
		if (strpos($wsUrl, '?') !== FALSE){
			$wsUrl = str_replace('?', '&', $wsUrl);
		}

		$url = 'nav://list/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . "/?{$wsUrl}{$sep}wizipage={$page}");

		return $url;
	}

	public static function externalLink($url){
		return $url;
	}

	public static function linkToImage($url){
		$part2 = strrchr($url, "/");
		$pos = strpos($url, $part2);
		$part1 = substr_replace($url, "", $pos);

		return "cmd://open/image/" . urlencode($part1) . $part2;
	}

	public static function convertVideoActionToWebVideo($actionURL){
		return str_replace("open/video", "open/videopage", $actionURL);
	}

	private static $append = null;

	public static function getAppend($sep = '&'){
		if (self::$append === null){
			self::$append = 'wizi_ver='.WIZIAPP_P_VERSION.((isset($_GET['androidapp']) && $_GET['androidapp'] == 1)?'&androidapp=1':'').((isset($_GET['webapp']) && $_GET['webapp'] == 1)?'&webapp=1':'').'&ap=1&output=html';
		}

		return $sep.self::$append;
	}
}