<?php
/**
* @package WiziappWordpressPlugin
* @subpackage Display
* @author comobix.com plugins@comobix.com
*
*/

class WiziappLinksScreen extends WiziappBaseScreen{
    protected $name = 'links';
    protected $type = 'list';

    public function run(){
        $screen_conf = $this->getConfig();

    //    $limit = wiziapp_getLinksLimit();

        $categories = get_terms('link_category', array(
            'orderby' => 'name',
            'order' => 'ASC',
            'number' => 200, //We limit this to 200 for now
            'hierarchical' => 0)
        );

        $sections = array();
        foreach ($categories as $cat) {
            // Build a section for each category
            if ( $cat->count > 0 ){
                // Get all the links in this category
                $section = array(
                    'section' => array(
                        'title' => $cat->name,
                        'id'    => "cat_{$cat->term_id}",
                        'items' => array(),
                    )
                );
				$links = get_bookmarks(apply_filters('widget_links_args', array(
							'limit' => $cat->count,
							'category' => $cat->term_id,
						)));

                foreach ($links as $link) {
                    $link->link_name = str_replace('&amp;', '&', $link->link_name);
                    $this->appendComponentByLayout($section['section']['items'], $screen_conf['items'], $link);
                }
                $sections[] = $section;
            }
        }

        $this->output($this->prepare($sections, $this->getTitle(), 'List', true));
    }

    public function runByCategory($cat_id){
        $screen_conf = $this->getConfig();

        $page = array();
        $pageNumber = isset($_GET['wizipage']) ? $_GET['wizipage'] : 0;
        $linkLimit = WiziappConfig::getInstance()->links_list_limit;
        $limitForRequest = $linkLimit * 2;
        $offset = $linkLimit * $pageNumber;

        $links = get_bookmarks(array(
            'limit' => $limitForRequest,
            'category' => $cat_id,
            'offset' => $offset,
        ));

        foreach ($links as $link) {
            $link->link_name = str_replace('&amp;', '&', $link->link_name);
            $this->appendComponentByLayout($page, $screen_conf['items'], $link);
        }

        $linkCount = count($links);
        $pager = new WiziappPagination($linkCount, $linkLimit);
        $pager->setOffset(0);
        $page = $pager->extractCurrentPage($page);
        $pager->addMoreCell(__("Load %s more items", 'wiziapp'), $page);

        $this->output($this->prepare($page, $this->getTitle(), 'List', false, false, true), array('url' => 'nav://list/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . '/?wiziapp/content/list/links/categories'), 'text' => $this->getTitle('categories')));
    }
}