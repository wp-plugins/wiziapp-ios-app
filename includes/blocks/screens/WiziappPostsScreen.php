<?php
/**
* @package WiziappWordpressPlugin
* @subpackage Display
* @author comobix.com plugins@comobix.com
*
*/

class WiziappPostsScreen extends WiziappBaseScreen{

	// Yes, I know this is an ugly hack, but it's the most non-destructive way to pass this info
	static public $postLinkAdd = false;

	protected $name = 'posts';
	protected $type = 'list';

	public function run(){}

	public function runByRecent(){
		$screen_conf = $this->getConfig();
		$title = htmlspecialchars( WiziappConfig::getInstance()->app_name );
		if ( isset($screen_conf['items_inner']) ){
			$this->scrollingCategories($screen_conf, $title);
			return;
		}

		$numberOfPosts = WiziappConfig::getInstance()->posts_list_limit;
		$query = array(
			'orderby' => 'post_date',
			'posts_per_page' => $numberOfPosts
		);

		// Handle paging
		$page = isset($_GET['wizipage']) ? $_GET['wizipage'] : 0;
		if ( empty($page) ){
			$query['pageOffset'] = $query['offset'] = 0;
			// Remove one for the featured post
			$query['posts_per_page'] = $numberOfPosts - 1;
		} else {
			$query['pageOffset'] = $query['offset'] = ( $numberOfPosts * $page );
		}

		// With the offset which post have we reached?
		$totalShownPosts = $numberOfPosts + ( ($numberOfPosts * $page) + 1 );

		// Find the total number of posts in the blog
		$publishedPosts = 0;
		foreach( WiziappComponentsConfiguration::getInstance()->get_post_types() as $post_type ){
			$countPosts = wp_count_posts($post_type);
			$publishedPosts += $countPosts->publish;
		}

		if ( $totalShownPosts < $publishedPosts ){
			$leftToShow = $publishedPosts - $totalShownPosts;
			$showMore = $leftToShow < $numberOfPosts ? $leftToShow : $numberOfPosts;
		} else {
			$showMore = FALSE;
		}

		// Only show the first section on the first request (Main Page).
		// The rest of the requests need to update the recent section.
		if ( empty($page) || $page == 0 ){
			$firstQuery = array(
				'posts_per_page'      => 1,
				'post__in'            => get_option('sticky_posts'),
				'ignore_sticky_posts' => 1,
				'orderby'             => 'post_date'
			);

			$featuredPostSection = array(
				'section' => array(
					'title' => '',
					'id'    => 'featured_post',
					'items' => array(),
				)
			);

			$GLOBALS['wp_posts_listed'] = array();
			$featuredPostSection['section']['items'] = $this->build($firstQuery, '', $screen_conf['header'], true);

			$query['post__not_in'] = $GLOBALS['wp_posts_listed'];

			$stickPosts = get_option('sticky_posts');
			$stickPostsCount = count($stickPosts);
			if ( $stickPostsCount > 1 ){
				$postsIncludedAnyway = $stickPostsCount - 1 + intval( $wiziapp_featured_post !== '' );
				$query['posts_per_page'] = $query['posts_per_page'] - $postsIncludedAnyway;
			}
		} else {
			// We are retrieving an update page to add to the first, the first displayed the featured post.
			// This might have more sticky so we can't ignore them, but we need to exclude some of them, since they might already be shown.
			$stickPosts = get_option('sticky_posts');
			if ( ! empty($stickPosts) ){
				$query['post__not_in'] = array_slice ($stickPosts , 0, $query['offset']);
				$query['pageOffset'] = $query['offset'];
				$query['offset'] -= count($query['post__not_in']);
			}

			$featuredPostSection = array();
		}
		$query['post_type'] = WiziappComponentsConfiguration::getInstance()->get_post_types();

		$recentSection = array(
			'section' => array(
				'title' => '',
				'id' => 'recent_posts',
				'items' => array(),
			)
		);

		$recentSection['section']['items'] = $this->build($query, '', $screen_conf['items'], true, $showMore);

		if ( ! empty($featuredPostSection) ){
			$mergedSections = array($featuredPostSection, $recentSection);
		} else {
			$mergedSections = array($recentSection);
		}

		$GLOBALS['WiziappEtagOverride'] .= $title;
		$screen = $this->prepareSection($mergedSections, $title, "List", false, true);
		$this->output($screen);
	}

	function runByAuthor($author_id){
		self::$postLinkAdd = 'author='.urlencode($author_id);

		$numberOfPosts = WiziappConfig::getInstance()->posts_list_limit;
		$screen_conf = $this->getConfig('author_list');

		$cQuery = "orderby=modified&posts_per_page=-1&author={$author_id}";

		$countQuery = new WP_Query($cQuery);
		$total = count($countQuery->posts);

		$pager = new WiziappPagination($total);

		$query = "orderby=modified&posts_per_page={$numberOfPosts}&author={$author_id}&offset={$pager->getOffset()}";
		$authorInfo = get_userdata($author_id);
		$authorName = $authorInfo->display_name;

		$title = strip_tags(__("Posts By:", 'wiziapp')." {$authorName}");

		$this->build($query, $title, $screen_conf['items'], false, $pager->leftToShow);
	}

	function runByIds($ids){
		self::$postLinkAdd = 'favorites=true';

		if ( !is_array($ids) ){
			$ids = explode(",", $ids);
		}

		$screen_conf = $this->getConfig();

		$query = array("post__in" => $ids, "orderby"=>"none"); // The orderby none is available only from wordpress 2.8
		$page = $this->build($query, '', $screen_conf['items'], TRUE);

		$this->output($this->prepare($page,  $this->getTitle('favorites'), 'favorites_list'));
	}

	function runByAttachment($attachment_id){
		self::$postLinkAdd = 'from_attachment_id='.urlencode($attachment_id);

		$screen_conf = $this->getConfig();

		$image = get_post($attachment_id);
		$query = array("post__in" => array($image->post_parent), "orderby"=>"none"); // The oderby none is available only from wordpress 2.8
		$page = $this->build($query, '', $screen_conf['items'], TRUE);

		$this->output($this->prepare($page, __('Related Posts', 'wiziapp'), 'list', false, false, false), array('url' => 'nav://page/'.urlencode(get_attachment_link($attachment_id)), 'text' => $image->post_title));
	}

	function runByTag($tag_id){
		self::$postLinkAdd = 'tag='.urlencode($tag_id);

		$screen_conf = $this->getConfig();

		$pageNumber = isset($_GET['wizipage']) ? $_GET['wizipage'] : 0;
		$numberOfPosts = WiziappConfig::getInstance()->posts_list_limit;
		$offset = $numberOfPosts * $pageNumber;
		$back = array('url' => 'nav://list/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . '/?wiziapp/content/list/tags'), 'text' => $this->getTitle('tags'));

		$tag = get_tag($tag_id);
		$title = WiziappTheme::applyRequestTitle("{$tag->name}");
		$query = "tag__in={$tag_id}&orderby=post_date&posts_per_page=" . $numberOfPosts . "&offset=" . $offset;

		// Find the total number of posts in the blog
		$totalPostsInTag = $tag->count;

		if ($totalPostsInTag < $offset){
			$this->output($this->prepareSection(array(), $title, "List"), $back);
			return;
		}

		if ($numberOfPosts < $totalPostsInTag){
			$showMore = $totalPostsInTag - $numberOfPosts;
		} else {
			$showMore = FALSE;
		}

		$posts = $this->build($query, '', $screen_conf['items'], TRUE, $showMore, FALSE);
		$postsCount = count($posts);
		$totalShownPosts = $totalPostsInTag - ($offset + $postsCount);
		if ($totalShownPosts < $numberOfPosts){
			$showMore = $totalShownPosts;
		} else {
			$showMore = $numberOfPosts;
		}

		if ($showMore){
			$obj = new WiziappMoreCellItem('L1', array(sprintf(__("Load %d more items", 'wiziapp'), $showMore), $pageNumber + 1));
			$moreComponent = $obj->getComponent();
			$posts[] = $moreComponent;
		}

		$section = array();
		$section[] = array(
			'section' => array(
				'title' => '',
				'id' => 'tags_'.time(), // Make it random
				'items' => $posts,
			)
		);

		$this->output($this->prepareSection($section, $title, "List"), $back);
	}

	function runByCategory($category_id){
		self::$postLinkAdd = 'cat='.urlencode($category_id);

		$screen_conf = $this->getConfig();

		$pageNumber = isset($_GET['wizipage']) ? $_GET['wizipage'] : 0;
		$numberOfPosts = WiziappConfig::getInstance()->posts_list_limit;
		$offset = $numberOfPosts * $pageNumber;

		$cat = get_category($category_id);
		$title = WiziappTheme::applyRequestTitle($cat->cat_name);
		$back = array('url' => 'nav://list/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . '/?wiziapp/content/list/categories'), 'text' => $this->getTitle('categories'));

		$query = "cat={$category_id}&orderby=post_date&posts_per_page=" . $numberOfPosts . "&offset=" . $offset;

		// Find the total number of posts in the category
		$totalPostsInCat = $cat->count;

		if ($totalPostsInCat < $offset){
			$this->output($this->prepareSection(array(), $title, "List"), $back);
			return;
		}

		if ($offset + $numberOfPosts < $totalPostsInCat){
			$showMore = $totalPostsInCat - $numberOfPosts - $offset;
			if ( $showMore > $numberOfPosts ){
				$showMore = $numberOfPosts;
			}
		} else {
			$showMore = FALSE;
		}

		$posts = $this->build($query, '', $screen_conf['items'], TRUE, $showMore);

		$section = array();
		$section[] = array(
			'section' => array(
				'title' => '',
				'id' => 'recent',
				'items' => $posts,
			)
		);

		$this->output($this->prepareSection($section, $title, "List"), $back);
	}

	// @todo: Add paging support here
	function runByDayOfMonth($params){
		$year = $params[0];
		$month = $params[1];
		$day = $params[2];

		self::$postLinkAdd = 'year='.urlencode($year).'&monthnum='.urlencode($month).'&day='.urlencode($day);

		global $wp_locale;
		$screen_conf = $this->getConfig('archived_list');

		$numberOfPosts = WiziappConfig::getInstance()->posts_list_limit;

		$query = "orderby=modified&posts_per_page={$numberOfPosts}&monthnum={$month}&year={$year}&day={$day}";

		$title = sprintf(__('%3$d %1$s %2$d'), $wp_locale->get_month($month), $year, $day);
		$cPage = $this->build($query, $title, $screen_conf['items'], true);
		$back = array('url' => 'nav://list/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . '/?wiziapp/content/list/archive/'.$year.'/'.$month), 'text' => sprintf(__('%1$s %2$d'), $wp_locale->get_month($month), $year));
		$this->output($this->prepare($cPage, $title, 'list', false, true), $back);
	}

	function runByMonth($params){
		$year = $params[0];
		$month = $params[1];
		global $wp_locale;
		$screen_conf = $this->getConfig('archived_list');

		self::$postLinkAdd = 'year='.urlencode($year).'&monthnum='.urlencode($month);

		$numberOfPosts = WiziappConfig::getInstance()->posts_list_limit;

		$cQuery = "orderby=modified&posts_per_page=-1&monthnum={$month}&year={$year}";

		$countQuery = new WP_Query($cQuery);
		$total = count($countQuery->posts);

		$pager = new WiziappPagination($total);

		$query = "orderby=modified&posts_per_page={$numberOfPosts}&monthnum={$month}&year={$year}&offset={$pager->getOffset()}";

		$title = sprintf(__('%1$s %2$d'), $wp_locale->get_month($month), $year);

		$cPage = $this->build($query, $title, $screen_conf['items'], true, $pager->leftToShow);
		$back = array('url' => 'nav://list/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . '/?wiziapp/content/list/archive/'.$year), 'text' => $year);
		$this->output($this->prepare($cPage, $title, 'list', false, true), $back);
	}

	// @todo: Add paging support here
	function runByAuthorCommented($author_id){
		self::$postLinkAdd = 'commented='.urlencode($author_id);

		$numberOfPosts = WiziappConfig::getInstance()->comments_list_limit;

		$screen_conf = $this->getConfig('commented_list');

		$page = array();
		global $wpdb;

		$key = md5( serialize( "posts=true&number={$numberOfPosts}&user_id={$author_id}")  );
		$last_changed = wp_cache_get('last_changed', 'comment');
		if ( !$last_changed ){
			$last_changed = time();
			wp_cache_set('last_changed', $last_changed, 'comment');
		}
		$cache_key = "get_comments:$key:$last_changed";

		if ( $cache = wp_cache_get( $cache_key, 'comment' ) ){
			$comments = $cache;
		} else {
			$approved = "comment_approved = '1'";
			$order = 'DESC';
			$orderby = 'comment_date_gmt';
			$number = 'LIMIT ' . $numberOfPosts;
			$post_where = "user_id = '{$author_id}' AND ";

			$comments = $wpdb->get_results( "SELECT * FROM $wpdb->comments
				WHERE $post_where $approved ORDER BY $orderby $order $number" );
			wp_cache_add( $cache_key, $comments, 'comment' );
		}

		$posts = array();
		// Get the posts id, and num of personal comments in each
		foreach($comments as $comment){
			$post_id = $comment->comment_post_ID;
			if ( !isset($posts[$post_id]) ){
				$posts[$post_id] = 0;
			}
			++$posts[$post_id];
		}

		foreach($posts as $post_id => $user_comments_count){
			//$comment_id = $comment->comment_ID;
			$this->appendComponentByLayout($page, $screen_conf['items'], $post_id, $user_comments_count);
		}

		$title = __('My Commented Posts', 'wiziapp');

		$this->output($this->prepare($page, $title, "List"), array('url' => false, 'text' => false));
	}
	/**
	* Used as an alternative recent page only for supported layouts
	*
	* @param array $screen_conf
	* @param string $title
	*/
	function scrollingCategories($screen_conf, $title){
		$page = array();
		$numberOfPosts = WiziappConfig::getInstance()->posts_list_limit * 2;
		$numOfScrollingItems = 6;
		$minScrollingItems = 3;

		// Get the recent posts and gather them in categories
		//    $posts = get_posts("category={$cat->cat_ID}&numberposts=" . $numberOfPosts);
		$posts = get_posts("numberposts=" . $numberOfPosts);
		$categories = array();

		foreach($posts as $post){
			foreach(get_the_category($post->ID) as $cat){
				if ( isset($categories[$cat->cat_ID]) ){
					++$categories[$cat->cat_ID];
				} else {
					$categories[$cat->cat_ID] = 1;
				}
			}
		}

		$catsCounter = 0;

		foreach($categories as $catId => $count){
			if ( $count >= $minScrollingItems ){
				$query = "cat={$catId}&orderby=post_date&posts_per_page={$numOfScrollingItems}";
				$items = $this->build($query, '', $screen_conf['items_inner'], TRUE);
				$this->appendComponentByLayout($page, $screen_conf['items'], get_category($catId), $items);
				++$catsCounter;
			}
		}

		$this->output($this->prepare($page, $title, 'List', false, false, true));
	}

	function build($query, $title, $block, $just_return = false, $show_more = false, $display_more_item = true){
		WiziappLog::getInstance()->write('DEBUG', "About to query posts by: " . print_r($query, TRUE), "screens.wiziapp_buildPostListPage");

		// Use the power of wordpress loop by passing the post component building to a template
		global $wiziapp_block, $cPage, $postsScreen, $wiziappQuery;
		$cPage = array();
		$wiziapp_block = $block;
		$postsScreen = $this;
		$wiziappQuery = $query;
		WiziappTemplateHandler::load(WIZI_DIR_PATH . 'themes/iphone/index.php');

		/**
		* Format the components in the appropriated screen format
		*/

		if ($show_more > 0){
			$offset = 0;

			if ( is_array($query) ){
				$offset = isset($query['offset']) ? $query['offset'] : 0;
				if ( isset($query['pageOffset']) ){
					$offset = $query['pageOffset'];
				}
			} else {
				parse_str($query);
			}

			if ( $offset > 0 ){
				$page = floor($offset / WiziappConfig::getInstance()->posts_list_limit);
			} else {
				$page = 0;
			}
			// Now increase the current page so it will point to the next
			++$page;

			if ($display_more_item){
				$obj = new WiziappMoreCellItem('L1', array(sprintf(__("Load %d more items", 'wiziapp'), $show_more), $page));
				$moreComponent = $obj->getComponent();
				$cPage[] = $moreComponent;
			}

			// Posts lists screens alter their etag, so we need to force include the more tag into the calculation...
			$GLOBALS['WiziappEtagOverride'] .= serialize($moreComponent);
		}

		// $pager = new WiziappPagination(count($cPage), appcom_getAppPostListLimit());
		// $cPage = $pager->extractCurrentPage($cPage);
		// $pager->addMoreCell(__("Load %s more items", 'wiziapp'), $cPage);

		if ($just_return){
			WiziappLog::getInstance()->write('INFO', 'About to return the posts section', 'wiziappPostsScreen.build');
			return $cPage;
		}

		$this->output($this->prepare($cPage, $title, 'list', false, true), array('url' => false, 'text' => false));
	}
}