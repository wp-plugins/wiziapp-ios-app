<?php
/**
* @package WiziappWordpressPlugin
* @subpackage Display
* @author comobix.com plugins@comobix.com
*
*/

class WiziappVideosScreen extends WiziappBaseScreen{
    protected $name = 'video';
    protected $type = 'list';

    public function run(){
        $screen_conf = $this->getConfig();
        $title = $this->getTitle('videos');

        $cPage = array();
        $pageNumber = isset($_GET['wizipage']) ? $_GET['wizipage'] : 0;
        $videoLimit = WiziappConfig::getInstance()->videos_list_limit;
        $limitForRequest = $videoLimit * 2;
        $offset = $videoLimit * $pageNumber;

        $videos = WiziappDB::getInstance()->get_all_videos($offset, $limitForRequest);
        if ($videos !== FALSE){
            $videos = apply_filters('wiziapp_video_request', $videos);
            $videoCount = count($videos);
            $pager = new WiziappPagination($videoCount, $videoLimit);
            $pager->setOffset(0);
            $videos = $pager->extractCurrentPage($videos);

            $sortedVideos = array();
            $allVideos = array();

            for($v = 0, $vTotal = count($videos); $v < $vTotal; ++$v){
                // Get the video date
                $post = get_post($videos[$v]['content_id']);
                $authorId = $post->post_author;
                $authorInfo = get_userdata($authorId);

                $video = array_merge(
                    array(
                        'id' => $videos[$v]['id'],
                        'content_id' => $videos[$v]['content_id'],
                        'author' => $authorInfo->display_name,
                    ),
                    json_decode($videos[$v]['attachment_info'], TRUE)
                );
                if (!isset($video['gotMobile']) || $video['gotMobile'] == TRUE){
                    $sortedVideos[$video['id']] = strtotime($post->post_date);
                    $allVideos[$video['id']] = $video;
                }
            }
            arsort($sortedVideos);

            /**
            * Handle paging
            */
            foreach($sortedVideos as $videoId => $videoDate){
                $video = $allVideos[$videoId];
                $this->appendComponentByLayout($cPage, $screen_conf['items'], $video);
            }

            $pager->addMoreCell(__("Load %s more items", 'wiziapp'), $cPage);
        }

        $screen = $this->prepare($cPage, $title, 'list');
        $screen['screen']['default'] = 'list';
        $screen['screen']['sub_type'] = 'video';
        $this->output($screen);
    }
}