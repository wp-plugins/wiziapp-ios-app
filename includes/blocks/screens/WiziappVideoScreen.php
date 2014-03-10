<?php
/**
* @package WiziappWordpressPlugin
* @subpackage Display
* @author comobix.com plugins@comobix.com
*
*/

class WiziappVideoScreen extends WiziappBaseScreen{

	public function run(){}

	public function runById($video_id){
		global $video_row;

		$video_row = WiziappDB::getInstance()->get_videos_by_id($video_id);

		if ( ! WiziappContentHandler::getInstance()->isHTML() ){
			WiziappTemplateHandler::load(WIZI_DIR_PATH . 'themes/iphone/video.php');
			return;
		}

		$video = json_decode($video_row['attachment_info'], TRUE);

		$video_embed = new WiziappVideoEmbed();
		$content = $video_embed->getCode($video['actionURL'], $video_row['id'], $video['bigThumb']);

		$page = array(
			'content' => str_replace('video_wrapper', '', $content),
			'description' => $video['description'],
		);

		$this->output( $this->prepare($page, $video['title'], 'Video'), array('url' => 'nav://list/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . '/?wiziapp/content/list/media/videos'), 'text' => $this->getTitle('videos')) );
	}
}