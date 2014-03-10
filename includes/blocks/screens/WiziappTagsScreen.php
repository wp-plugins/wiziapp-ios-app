<?php
/**
* @package WiziappWordpressPlugin
* @subpackage Display
* @author comobix.com plugins@comobix.com
*
*/

class WiziappTagsScreen extends WiziappBaseScreen{
    protected $name = 'categories';
    protected $type = 'tags_list';

    public function run(){
        $screen_conf = $this->getConfig();

        $page = array();
        $pageNumber = isset($_GET['wizipage']) ? $_GET['wizipage'] : 0;
        $tagLimit = WiziappConfig::getInstance()->tags_list_limit;
        $limitForRequest = $tagLimit * 2;
        $offset = $tagLimit * $pageNumber;

        $tag_args = array(
            'number' => $limitForRequest,
            'offset' => $offset,
            'hierarchical' => FALSE,
        );
        $tags = get_tags(apply_filters('wiziapp_exclude_tags', $tag_args));

        $index = 0;
        foreach ($tags as $tag) {
            $tag->name = str_replace('&amp;', '&', $tag->name);
            $this->appendComponentByLayout($page, $screen_conf['items'], $tag, ++$index);
        }

        $tagCount = count($tags);
        $pager = new WiziappPagination($tagCount, $tagLimit);
        $pager->setOffset(0);
        $page = $pager->extractCurrentPage($page);
        $pager->addMoreCell(__("Load %s more items", 'wiziapp'), $page);

        $this->output($this->prepare($page, $this->getTitle('tags'), 'List', false, false, false));
    }

    public function runByPost($post_id){
        $screen_conf = $this->getConfig();

        $page = array();
        $tags = get_the_tags($post_id);

        $index = 0;
        foreach ($tags as $tag) {
            $this->appendComponentByLayout($page, $screen_conf['items'], $tag, ++$index);
        }

        $pager = new WiziappPagination(count($page), WiziappConfig::getInstance()->categories_list_limit);
        $page = $pager->extractCurrentPage($page);

        $pager->addMoreCell(__("Load %s more items", 'wiziapp'), $page);

        $post = get_post($post_id);
        $this->output($this->prepare($page, $this->getTitle('tags'), 'List', false, false, false), array('url' => WiziappLinks::postLink($post_id), 'text' => $post->post_title));
    }
}