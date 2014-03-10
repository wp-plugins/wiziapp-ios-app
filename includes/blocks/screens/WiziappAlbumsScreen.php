<?php
/**
* @package WiziappWordpressPlugin
* @subpackage Display
* @author comobix.com plugins@comobix.com
*
*/

class WiziappAlbumsScreen extends WiziappBaseScreen{
	protected $name = 'albums';
	protected $type = 'list';

	// @todo Add paging support here
	public function run(){
		WiziappLog::getInstance()->write('INFO', "Building galleries page",
											'screens.wiziapp_buildPluginGalleriesPage');

		$screen_conf = $this->getConfig();

		$page = array();
		$albumLimit = WiziappConfig::getInstance()->posts_list_limit;

		$sortedAlbums = array();
		$allAlbums = array();

		// @todo If such method already exists, it should implement the pager.
		$galleries = new WiziappGalleries();
		$albums = $galleries->getAll();
		// $albums = apply_filters('wiziapp_albums_request', $albums);

		for($a = 0, $total_albums = count($albums); $a < $total_albums; ++$a){
			$album = $albums[$a];

			$sortedAlbums[$album['postID'] . '_' . $album['id']] = strtotime($album['publish_date']);
			$allAlbums[$album['postID'] . '_' . $album['id']] = $album;
		}

		arsort($sortedAlbums);

		foreach($sortedAlbums as $albumId => $albumDate){
			$album = $allAlbums[$albumId];
			$config_key = 'items';
			if ($sortedAlbums[$albumId]['plugin'] == 'videos'){
				$config_key = 'videos_items';
			}
			$this->appendComponentByLayout($page, $screen_conf[$config_key], $album);
		}

		$albumCount = count($sortedAlbums);
		$pager = new WiziappPagination($albumCount, $albumLimit);
		$page = $pager->extractCurrentPage($page, FALSE);
		$pager->addMoreCell(__("Load %s more items", 'wiziapp'), $page);

		/*WiziappLog::getInstance()->write('DEBUG', "Got the page: ".print_r($page, TRUE),
												"screens.wiziapp_buildPluginGalleriesPage");*/
		$title = __(WiziappConfig::getInstance()->getScreenTitle('albums'), 'wiziapp');
		$screen = $this->prepare($page, $title, 'list', false, true);

		$this->output($screen);
	}

	//@todo Add paging support here
	public function runByPlugin($params){
		$this->name = 'images';

		$plugin = $params[0];
		$item_id = $params[1];
		WiziappLog::getInstance()->write('DEBUG', "Got a request for a gallery from {$plugin} item is: {$item_id}", "WiziappAlbumsScreen.runByPlugin");
		$images = array();
		// Check if we support this plugin
		$plugin = strtolower($plugin);

		$images = apply_filters("wiziapp_get_{$plugin}_album", $images, $item_id);

		$screen_conf = $this->getConfig();
		$page = array();

		foreach($images as $image){
			$this->appendComponentByLayout($page, $screen_conf['items'], $image, true);
		}

		$title = __('Gallery', 'wiziapp');
		$screen = $this->prepare($page, $title, 'gallery');

		$screen['screen']['default'] = 'grid';
		$screen['screen']['sub_type'] = 'image';
		$this->output($screen, array('url' => 'nav://list/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . '/?wiziapp/content/list/galleries'), 'text' => __(WiziappConfig::getInstance()->getScreenTitle('albums'), 'wiziapp')));
	}
}