<?php if (!defined('WP_WIZIAPP_BASE')) exit();

class WiziappApi extends WiziappMediaExtractor{
    public function __construct() {
    }

    public function externalPluginContent($content, $media_type, $medias) {
        $ch = WiziappContentHandler::getInstance();

        if (($ch->isInApp() || $ch->isInSave()) && !empty($medias)) {
            if ($media_type == 'image') {
                $wiziapp_images = '';
                foreach($medias as $image){
                	//return print_r($image);
                    $wiziapp_images .= '<a href="' . $image['src'] . '" class="wiziapp_gallery external-gallery-id">' . PHP_EOL .
                                           '<img src="' . $image['src'] . '" ' .
										   (isset($image['alt'])    ? 'alt="' . $image['alt'] . '" ' : '') .
                                           (isset($image['title'])  ? 'title="' . $image['title'] . '" ' : '') .
                                           (isset($image['width'])  ? 'width="' . $image['width'] . '" ' : '') .
                                           (isset($image['height']) ? 'height="' . $image['height'] . '" ' : '') .
                                           (isset($image['class'])  ? 'class="' . $image['css_class'] . '" ' : '') .
                                           'external-gallery-id="' . $image['gallery_id'] . '" />' . PHP_EOL .
										'</a>' . PHP_EOL;
                }
                $content = $wiziapp_images;
            } else if ($media_type == 'video') {
                $wiziapp_videos = '';
                foreach($medias as $video){
    //                $wiziapp_video = wp_oembed_get($video['src'], array('width' => 400, 'height' => 400));
                    $wiziapp_video = wp_oembed_get($video['src'], array());
                    $wiziapp_videos .= $wiziapp_video;
                }
                $content = $wiziapp_videos;
            } else if ($media_type == 'audio') {
                $wiziapp_audios = '';
                foreach($medias as $audio){
                    $wiziapp_audios .= '<a href="' . $audio['src'] . '" title="' . $audio['title'] . '">' . $audio['title'] . '</a><br />';
                }
                $content = $wiziapp_audios;
            }
        }

        return $content;
    }

}