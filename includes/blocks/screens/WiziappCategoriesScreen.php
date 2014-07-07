<?php
/**
* @package WiziappWordpressPlugin
* @subpackage Display
* @author comobix.com plugins@comobix.com
*
*/

class WiziappCategoriesScreen extends WiziappBaseScreen{
    protected $name = 'categories';
    protected $type = 'list';

    public function run(){
        $screen_conf = $this->getConfig();

        $page = array();
        $pageNumber = isset($_GET['wizipage']) ? $_GET['wizipage'] : 0;
        $catLimit = WiziappConfig::getInstance()->categories_list_limit;

        $limitForRequest = $catLimit * 2;
        $offset = $catLimit * $pageNumber;

        $cat_args = array(
            'number' => $limitForRequest,
            'offset' => $offset,
            'hierarchical' => FALSE,
            'pad_counts' => 1,
        );
		$categories = get_categories(apply_filters('wiziapp_exclude_categories', $cat_args));

        $index = 0;
        foreach ($categories as $cat) {
            $cat->name = str_replace('&amp;', '&', $cat->name);
            $this->appendComponentByLayout($page, $screen_conf['items'], $cat, ++$index);
        }

        $catCount = count($categories);
        $pager = new WiziappPagination($catCount, $catLimit);
        $pager->setOffset(0);
        $page = $pager->extractCurrentPage($page);
        $pager->addMoreCell(__("Load %s more items", 'wiziapp'), $page);

        $this->output($this->prepare($page, $this->getTitle(), 'List', false, false, true));
    }

    public function runByPost($post_id){
        $screen_conf = $this->getConfig();

        $page = array();

        $categories = get_the_category($post_id);

        $index = 0;
        foreach ($categories as $cat) {
            // Only show categories that has posts in them
            if ( $cat->category_count > 0 ){
                $this->appendComponentByLayout($page, $screen_conf['items'], $cat, ++$index);
            }
        }

        $pager = new WiziappPagination(count($page), WiziappConfig::getInstance()->categories_list_limit);
        $page = $pager->extractCurrentPage($page);

        $pager->addMoreCell(__("Load %s more items", 'wiziapp'), $page);

        $post = get_post($post_id);
        $this->output($this->prepare($page, $this->getTitle(), 'List'), array('url' => WiziappLinks::postLink($post_id), 'text' => $post->post_title));
    }
}