<?php if (!defined('WP_WIZIAPP_BASE')) exit();
/**
* @package WiziappWordpressPlugin
* @subpackage Display
* @author comobix.com plugins@comobix.com
*
*/

class WiziappListsServices{

	public function categories(){
		$header = array(
			'action' => 'wiziapp_getAllCategories',
			'status' => TRUE,
			'code' => 200,
			'message' => '',
		);

		$categoriesLimit = WiziappConfig::getInstance()->categories_list_limit;
		$pageNumber = isset($_GET['wizipage']) ? $_GET['wizipage'] : 0;

		$categories = get_categories(array(
			'number' => $categoriesLimit,
			'offset' => $pageNumber * $categoriesLimit,
			'hide_empty' => FALSE,
			'pad_counts' => 1,
		));

		$categoriesSummary = array();
		foreach($categories as $category) {
			$categoriesSummary[$category->cat_ID] = $category->cat_name;
		}

		// Get the total number of categories
		$total = wp_count_terms('category');

		echo json_encode( array( 'header' => $header, 'categories' => $categoriesSummary, 'total' => $total, 'list_limit' => $categoriesLimit, ) );
	}

	public function tags(){
		$header = array(
			'action' => 'wiziapp_getAllTags',
			'status' => TRUE,
			'code' => 200,
			'message' => '',
		);

		$tagsLimit = WiziappConfig::getInstance()->tags_list_limit;
		$pageNumber = isset($_GET['wizipage']) ? $_GET['wizipage'] : 0;

		$tags = get_tags(array(
			'number' => $tagsLimit,
			'offset' => $pageNumber * $tagsLimit,
			'hide_empty' => FALSE,
		));

		$tagsSummary = array();
		foreach($tags as $tag) {
			$tagsSummary[$tag->term_id] = $tag->name;
		}

		// Get the total number of tags
		$total = wp_count_terms('post_tag');

		echo json_encode( array( 'header' => $header, 'tags' => $tagsSummary, 'total' => $total, 'list_limit' => $tagsLimit, ) );
	}

	public function authors(){
		$header = array(
			'action' => 'wiziapp_getAllAuthors',
			'status' => TRUE,
			'code' => 200,
			'message' => '',
		);

		$authorsLimit = WiziappConfig::getInstance()->authors_list_limit;
		$pageNumber = isset($_GET['wizipage']) ? $_GET['wizipage'] : 0;
		$offset = $authorsLimit * $pageNumber;

		global $wpdb;
		$query =
		"SELECT DISTINCT posts.post_author AS author_id, users.display_name AS name " .
		"FROM " . $wpdb->posts . " AS posts " .
		"INNER JOIN " . $wpdb->users . " AS users ON posts.post_author = users.ID " .
		"WHERE posts.post_type = 'post' AND " .
		get_private_posts_cap_sql('post') . " " .
		"ORDER BY users.display_name " .
		"LIMIT " . $offset . ',' . $authorsLimit;
		$authors = $wpdb->get_results($query);

		$authorsSummary = array();
		foreach($authors as $author) {
			$authorsSummary[$author->author_id] = $author->name;
		}

		echo json_encode( array( 'header' => $header, 'authors' => $authorsSummary, 'total' => count($authorsSummary), 'list_limit' => $authorsLimit, ) );
	}

	public function pages(){
		$header = array(
			'action' => 'wiziapp_getAllPages',
			'status' => TRUE,
			'code' => 200,
			'message' => '',
		);

		$pagesLimit = WiziappConfig::getInstance()->pages_list_limit;
		$pageNumber = isset($_GET['wizipage']) ? $_GET['wizipage'] : 0;

		$pages = get_pages(array(
			'number' => $pagesLimit,
			'offset' => $pageNumber * $pagesLimit,
		));
		$pagesSummary = array();
		foreach($pages as $singlePage) {
			$pagesSummary[get_permalink($singlePage->ID)] = $singlePage->post_title;
		}

		// Get the total number of pages
		$total  = wp_count_posts( 'page' );

		echo json_encode( array( 'header' => $header, 'pages' => $pagesSummary, 'total' => $total->publish, 'list_limit' => $pagesLimit, ) );
	}

	public function links(){
		$header = array(
			'action' => 'wiziapp_getAllLinks',
			'status' => TRUE,
			'code' => 200,
			'message' => '',
		);

		$linksLimit = WiziappConfig::getInstance()->links_list_limit;
		$pageNumber = isset($_GET['wizipage']) ? $_GET['wizipage'] : 0;

		$links = get_bookmarks(array(
			'limit' => $linksLimit,
			'offset' => $pageNumber * $linksLimit,
		));

		$linksSummary = array();
		foreach($links as $link) {
			$linksSummary[$link->link_url] = str_replace('&amp;', '&', $link->link_name);
		}

		// Get the total number of pages
		$total  = WiziappDB::getInstance()->get_links_count();

		echo json_encode( array( 'header' => $header, 'links' => $linksSummary, 'total' => $total, 'list_limit' => $linksLimit, ) );
	}
}