<?php
/**
* @package WiziappWordpressPlugin
* @subpackage Display
* @author comobix.com plugins@comobix.com
*
*/

class WiziappPagesScreen extends WiziappBaseScreen{
    protected $name = 'pages';
    protected $type = 'list';

    public function run(){
        $screen_conf = $this->getConfig();

        $page = array();
        $pageNumber = isset($_GET['wizipage']) ? $_GET['wizipage'] : 0;
        $linkLimit = WiziappConfig::getInstance()->links_list_limit;
        $limitForRequest = $linkLimit * 2;
        $offset = $linkLimit * $pageNumber;

        $pages = get_pages(array(
            'number' => $limitForRequest,
            'offset' => $offset,
            'sort_column' => 'menu_order',
            'parent' => 0,
        ));

        $section = array(
            'section' => array(
                'title' => '',
                'id'    => "allPages",
                'items' => array(),
            )
        );

        //$pagesConfig = get_option('wiziapp_pages');
        //$allowedPages = implode(',', $pagesConfig['pages']);
        /**
        * @todo replace this algorithm all together...
        * The admin should send the rules and not the allowed
        */
        //var_dump($allowedPages);

        foreach ($pages as $p) {
            $title = str_replace('&amp;', '&', $p->post_title);
            //if ( stripos($allowedPages, $title) !== FALSE ){
            if ($p->post_parent == 0){
                $link = array(
                    'link_name' => $title,
                    'link_url' => WiziappLinks::pageLink($p->ID),
                    'link_id' => $p->ID,
                );
                $this->appendComponentByLayout($section['section']['items'], $screen_conf['items'], (object) $link);
            }
        }

        $linkCount = count($section['section']['items']);
        $pager = new WiziappPagination($linkCount, $linkLimit);
        $pager->setOffset(0);
        $page = $pager->extractCurrentPage($section['section']['items']);
        $pager->addMoreCell(__("Load %s more items", 'wiziapp'), $page);

        $this->output($this->prepare($page, $this->getTitle(), 'List', false, false, false));
    }
}