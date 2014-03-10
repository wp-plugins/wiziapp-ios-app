<?php
/**
* @package WiziappWordpressPlugin
* @subpackage Display
* @author comobix.com plugins@comobix.com
*
*/

class WiziappArchivesScreen extends WiziappBaseScreen{
	protected $name = 'archives';
	protected $type = 'list';

	// @todo: Add paging support here
	public function run(){
		global $wpdb;

		$screen_conf = $this->getConfig();

		$where = apply_filters('getarchives_where', "WHERE post_type = 'post' AND post_status = 'publish'");
		$join = apply_filters('getarchives_join', "");

		$query = "SELECT YEAR(post_date) AS `year`, count(ID) as posts FROM $wpdb->posts $join $where GROUP BY YEAR(post_date) ORDER BY post_date DESC";
		WiziappLog::getInstance()->write('DEBUG', "Prepared the archive query: {$query}", "screens.wiziapp_buildArchiveYearsPage");
		$key = md5($query);
		$cache = wp_cache_get( 'wiziapp_buildArchiveYearsPage' , 'general');
		if ( !isset( $cache[ $key ] ) ) {
			$results = $wpdb->get_results($query);
			$cache[ $key ] = $results;
			wp_cache_add( 'wiziapp_buildArchiveYearsPage', $cache, 'general' );
		} else {
			$results = $cache[ $key ];
		}


		$allYears = array(
			'section' => array(
				'title' => '',
				'id' => 'allYears',
				'items' => array(),
			)
		);

		if ($results) {
			foreach ( (array) $results as $result) {
				$year = sprintf('%d', $result->year);
				$posts = $result->posts;
				$this->appendComponentByLayout($allYears['section']['items'], $screen_conf['items'], $year, $posts, 'years');
			}
		}
		//$title = __('Archive', 'wiziapp');
		$title = $this->getTitle('archive');

		$this->output($this->prepareSection(array($allYears), $title, "List", false, true));
	}

	// @todo: Add paging support here
	public function runByYear($year){
		global $wpdb, $wp_locale;

		$screen_conf = $this->getConfig('months_list');

		$allMonths = array(
			'section' => array(
				'title' => '',
				'id' => 'allYears',
				'items' => array(),
			)
		);

		$where = apply_filters('getarchives_where',
								"WHERE post_type = 'post' AND post_status = 'publish'
								AND YEAR(post_date) = {$year}");

		$join = apply_filters('getarchives_join', "");

		$query = "SELECT YEAR(post_date) AS `year`, MONTH(post_date) AS `month`, count(ID) as posts
					FROM $wpdb->posts $join $where
					GROUP BY YEAR(post_date), MONTH(post_date)
					ORDER BY post_date DESC";
		WiziappLog::getInstance()->write('DEBUG', "Prepared the archive query: {$query}",
											"screens.wiziapp_buildArchiveYearsPage");
		$key = md5($query);
		$cache = wp_cache_get( 'wiziapp_buildArchiveMonthsPage' , 'general');
		if ( !isset( $cache[ $key ] ) ) {
			$results = $wpdb->get_results($query);
			$cache[ $key ] = $results;
			wp_cache_add( 'wiziapp_buildArchiveMonthsPage', $cache, 'general' );
		} else {
			$results = $cache[ $key ];
		}

		if ($results) {
			foreach ( (array) $results as $result) {
				$title = sprintf(__('%1$s'), $wp_locale->get_month($result->month));
				$posts = $result->posts;
				$this->appendComponentByLayout($allMonths['section']['items'],
												$screen_conf['items'], $title, $posts,
												'months', $year, $result->month);
			}
		}
		$title = $year;

		$back = array('url' => 'nav://list/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . '/?wiziapp/content/list/archive'), 'text' => $this->getTitle('archive'));

		$this->output($this->prepareSection(array($allMonths), $title, "List", false, true), $back);
	}
}