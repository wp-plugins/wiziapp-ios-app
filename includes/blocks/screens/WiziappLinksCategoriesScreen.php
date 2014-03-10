<?php
/**
* @package WiziappWordpressPlugin
* @subpackage Display
* @author comobix.com plugins@comobix.com
*
*/

class WiziappLinksCategoriesScreen extends WiziappBaseScreen{
    protected $name = 'categories';
    protected $type = 'links_list';

    public function run(){
        $screen_conf = $this->getConfig();

        $page = array();
        $pageNumber = isset($_GET['wizipage']) ? $_GET['wizipage'] : 0;
        $catLimit = WiziappConfig::getInstance()->links_list_limit;
        $limitForRequest = $catLimit * 2;
        $offset = $catLimit * $pageNumber;

        $categories = get_terms('link_category', array(
            'orderby' => 'name',
            'order' => 'ASC',
            'number' => $limitForRequest,
            'offset' => $offset,
            'hierarchical' => 0)
        );

        $index = 0;
        foreach ($categories as $cat) {
            if ($cat->count > 0){
                $cat->name = str_replace('&amp;', '&', $cat->name);
                $this->appendComponentByLayout($page, $screen_conf['items'], $cat, ++$index);
            }
        }

        $catCount = count($categories);
        $pager = new WiziappPagination($catCount, $catLimit);
        $pager->setOffset(0);
        $page = $pager->extractCurrentPage($page);
        $pager->addMoreCell(__("Load %s more items", 'wiziapp'), $page);

        $this->output($this->prepare($page, $this->getTitle(), 'List', false, false, true));
    }
}
