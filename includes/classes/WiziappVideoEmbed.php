<?php if (!defined('WP_WIZIAPP_BASE')) exit();
/**
* Gets the video html embed code for the specified provider
*
* @todo Adds external plugins integration support here
*
* @package WiziappWordpressPlugin
* @subpackage UIComponents
* @author comobix.com plugins@comobix.com
*/

class WiziappVideoEmbed{
    private $content_width = 300;

    /**
     * This method will return the embed html code for a specific video by
     * it's actionURL as defined in the protocol API.
     *
     * If the request indicates that it is inside the simulator the code will
     * be returned with images instead of the video object since the simulator
     * doesn't handle flash videos well.
     *
     * @param  $url the actionURL for the video
     * @param  int $id the id to identify the video by
     * @param  array $thumbData an object containing the needed data on the thumbnail to display for the movie
     * @return string the html code for the embed
     */
    public function getCode($url, $id=0, $thumbData=array()){
        if (isset($_GET['sim']) && $_GET['sim'] == 1 && !empty($id) && !empty($thumbData)){
            return $this->_getSimulatorCode($id, $thumbData);
        } else {
            return $this->_getNormalEmbedCode($url, $id);
        }
    }

    private function _getNormalEmbedCode($url, $id){
        $urlParts = explode('/', $url);
        $movie_id =  $urlParts[5];
        $provider = $urlParts[4];
        $html = "";

		if ( isset($GLOBALS['content_width']) ){
			$contentWidth = $GLOBALS['content_width'];
		}
        $GLOBALS['content_width'] = $this->content_width;

        $iframe_protect_screen =
        '<div class="iframe_protect_screen" style="position: absolute;" data-video-url="'.WiziappContentHandler::getInstance()->get_blog_property('url').'/?wiziapp/content/video/'.$id.WiziappLinks::getAppend().'">'.
        '</div>';

        if ($provider == 'youtube'){
            $html =
			'<div class="video_wrapper data-wiziapp-iphone-support">'.
			$iframe_protect_screen.
			wp_oembed_get('http://www.youtube.com/watch?v='.$movie_id, array()).
			'</div>';
        } elseif ( $provider == 'vimeo' ){
            $partUrl = urldecode($urlParts[6]);
            $movie_id = substr($partUrl, strrpos($partUrl, '/') + 1);

            $html =
			'<div class="vimeo_wrapper data-wiziapp-iphone-support">'.
			$iframe_protect_screen.
			wp_oembed_get('http://vimeo.com/'.$movie_id, array()).
			'</div>';
        }

		if ( isset($contentWidth) ){
			$GLOBALS['content_width'] = $contentWidth;
		}

        return $html;
    }

    private function _getSimulatorCode($id, $thumbData){
        return
		'<div class="video_wrapper_container">
		    <div class="video_wrapper_sim" data-video="video_' . $id . '">
		         <img src="' . $thumbData['url'] . '" width="340" alt="Video Thumbnail" />
		         <div class="video_effect"></div>
		    </div>
		</div>';
    }
}