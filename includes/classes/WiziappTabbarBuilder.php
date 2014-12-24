<?php if (!defined('WP_WIZIAPP_BASE')) exit();

class WiziappTabbarBuilder{

	private $tabs = null;
	private $selectedTab = 0;
	private $maxTabsInBar = 5;
	private $gotMore = FALSE;
	private $pendingCss = FALSE;
	private $postNext = FALSE;
	private $postPrev = FALSE;
	private $_temporaly_disabled_tabs = array(
		'cmd://open/favorites/0',
		'?wiziapp/content/list/media/audios',
		'?wiziapp/content/about',
	);

	/**
	* This nifty little maps allows escaping CSS to be used in both
	* external, literal, and inline CSS scripts. The escaped subject should
	* be surrounded with quotes.
	*
	* Note: From the control characters, we only pick the printable TAB,
	* LF, and CR characters. This could easily be extended to include all
	* control characters, though.
	*/
	static public $safe_css_map = array("\t" => '\000008', "\n" => '\00000A', "\r" => '\00000R', '"' => '\000022', '&' => '\000026', "'" => '\000027', '<' => '\00003C', '>' => '\00003E');

	public function __construct(){
		$file = WiziappContentHandler::getInstance()->get_blog_property('data_files_dir').'/resources/config.js';
		$config = file_get_contents($file);
		$config = str_replace( 'var config = ', '', substr($config, 0, strlen($config) - 1) );
		$configObj = json_decode($config);
		$this->selectedTab = $configObj->tabbar->selectedTabId;
		$this->tabs 	   = $configObj->tabbar->tabs;

		// Remove the temporaly diabled Tabs
		for ($t = 0, $total = count($this->tabs); $t < $total; ++$t){
			$tab_callback = urldecode($this->tabs[$t]->rootScreenURL);
			if ( $tab_callback_prepared = strstr($tab_callback, '?wiziapp/') ){
				$tab_callback = $tab_callback_prepared;
			}

			if ( ! isset($this->tabs[$t]->enabled) || (int) $this->tabs[$t]->enabled !== 1 || in_array($tab_callback, $this->_temporaly_disabled_tabs) ){
				unset($this->tabs[$t]);
			}
		}

		if ( count($this->tabs) > $this->maxTabsInBar ){
			$this->gotMore = TRUE;
		}

		// Sort the Tabs after elements removing
		$this->tabs = array_values($this->tabs);
	}

	public function getCss(){
		if ($this->pendingCss !== FALSE)
		{
			return $this->pendingCss;
		}
		$this->pendingCss = '';
		$end = $this->gotMorePage() ? 4 : count($this->tabs);

		for ($t = 0; $t < $end; ++$t){
			$tab = $this->tabs[$t];

			$icon = str_replace('_black', '_grey', $tab->iconURL);
			$activeIcon = strtr($tab->iconURL, array('_grey' => '_on', '_black' => '_on'));

			$this->pendingCss .= '.tabbar-tab-'.bin2hex($tab->id).' .ui-icon{background:url("'.strtr($icon, self::$safe_css_map).'") 50% 50% no-repeat;}';
			$this->pendingCss .= '.tabbar-tab-'.bin2hex($tab->id).'.ui-btn-active .ui-icon,.tabbar-tab-'.bin2hex($tab->id).'.ui-btn-hover-a .ui-icon{background:url("'.strtr($activeIcon, self::$safe_css_map).'") 50% 50% no-repeat;}';
		}
		if ( ! $this->gotMorePage() ){
			return $this->pendingCss;
		}
		$this->pendingCss .= '.tabbar-tab-more-tab .ui-icon{background:url('.WiziappContentHandler::getInstance()->get_blog_property('data_files_url').'/resources/icons/iconMore_grey.png) 50% 50% no-repeat;}';
		$this->pendingCss .= '.tabbar-tab-more-tab.ui-btn-active .ui-icon, .tabbar-tab-more-tab.ui-btn-hover-a .ui-icon{background:url('.WiziappContentHandler::getInstance()->get_blog_property('data_files_url').'/resources/icons/iconMore_on.png) 50% 50% no-repeat;}';
		for ($t = 4, $total = count($this->tabs); $t < $total; ++$t){
			$tab = $this->tabs[$t];

			$icon = str_replace('_grey', '_black', $tab->iconURL);
			$activeIcon = strtr($tab->iconURL, array('_grey' => '_white', '_black' => '_white'));

			$this->pendingCss .= '.tabbar-tab-'.bin2hex($tab->id).'{background:url("'.strtr($icon, self::$safe_css_map).'") 10px 50% no-repeat;}';
			$this->pendingCss .= '.ui-btn-active .tabbar-tab-'.bin2hex($tab->id).'{background:url("'.strtr($activeIcon, self::$safe_css_map).'") 10px 50% no-repeat;}';
		}
		return $this->pendingCss;
	}

	public function gotMorePage(){
		return $this->gotMore;
	}

	public function getMorePage(){
		if ( ! $this->gotMorePage() ){
			return '';
		}

		ob_start();

		?>
		<ul data-role="listview">
			<?php

			for ($t = 4, $total = count($this->tabs); $t < $total; ++$t){
				$tab = $this->tabs[$t];

				$screenURL = $tab->rootScreenURL;
				// Add the webapp needed qs params
				$screenURL .= urlencode(WiziappLinks::getAppend());
				if ( in_array( $tab->type, array( 'search', 'favorites', ) ) ){
					$screenURL = '#'.$tab->type;
				}

				?>
				<li>
					<a href="<?php echo esc_attr($screenURL); ?>" class="tabbar-tab-<?php echo bin2hex($tab->id); ?>" data-icon="custom" data-page-type="<?php esc_attr($tab->type); ?>">
						<?php echo esc_html($tab->title).PHP_EOL; ?>
					</a>
				</li>
				<li style="background-color: #bbbbbb;" class="sep"></li>
				<?php
			}

			?>
		</ul>
		<?php

		return ob_get_clean();
	}

	public function getBar($selectedURL = FALSE){
		if ( $selectedURL === FALSE ){
			$selectedURL = $_SERVER['REQUEST_URI'];
		}
		$selectedTab = FALSE;
		$selectedTabIndex = 0;

		for ($t = 0; $t < count($this->tabs); ++$t){
			$tab = $this->tabs[$t];

			if ( $tab->type === $selectedURL ){
				$selectedTab = $tab->id;
				$selectedTabIndex = $t;
				break;
			}

			$url = explode('://', $tab->rootScreenURL, 2);
			$url = isset($url[1]) ? explode('/', $url[1], 2) : '';
			$url = isset($url[1]) ? explode('?', urldecode($url[1]), 2) : '';
			$url = isset($url[1]) ? $url[1] : '';

			// FIXME: Go by longest match?
			if ( $url != '' && strpos($selectedURL, $url) !== FALSE ){
				$selectedTab = $tab->id;
				$selectedTabIndex = $t;
				break;
			}
		}

		$datagrid = count($this->tabs);
		if ($datagrid < 2){
			$datagrid = 1;
		}
		if ($datagrid > 5){
			$datagrid = 5;
		}
		$datagrid_lookup = array('solo', 'a', 'b', 'c', 'd',);
		$datagrid = $datagrid_lookup[$datagrid - 1];

		ob_start();

		?>
		<div data-id="tabbar" data-role="footer" data-position="fixed" data-tap-toggle="false" class="nav-tabbar">
			<div data-role="navbar" class="nav-tabbar" data-grid="<?php echo $datagrid; ?>">
				<ul>
					<?php

					$end = $this->gotMorePage() ? 4 : count($this->tabs);
					if ( 'moreScreen' === $selectedURL ){
						$selectedTab = false;
						$selectedTabIndex = $end;
					}

					for ($t = 0; $t < $end; ++$t){
						$tab = $this->tabs[$t];

						$tabClass = '';
						if ( $tab->id === $selectedTab ){
							$tabClass = ' ui-btn-active ui-state-persist';
						}

						$actionParams = explode('://', $tab->rootScreenURL);
						$actionType = $actionParams[0];
						$screenParams = explode('/', $actionParams[1]);
						$screenURL = urldecode($screenParams[1]);

						// Add the webapp needed qs params
						$screenURL .= WiziappLinks::getAppend().'&back=0';
						if ( in_array( $tab->type, array( 'search', 'favorites', ) ) ){
							$screenURL = '#'.$tab->type;
						}

						$dir = ($selectedTabIndex > $t)?' data-direction="reverse"':'';
						?>
						<li>
							<a href="<?php echo esc_attr($screenURL); ?>" class="list_tabbar tabbar-tab-<?php echo bin2hex($tab->id).esc_attr($tabClass); ?>" data-icon="custom" data-page-type="<?php esc_attr($tab->type); ?>" <?php echo $dir; ?>>
								<?php echo esc_html($tab->title).PHP_EOL; ?>
							</a>
						</li>
						<?php
					}

					if ( $this->gotMorePage() ){
						$tabClass = '';
						if ( 'moreScreen' === $selectedURL ){
							$tabClass = ' ui-btn-active ui-state-persist';
						}
						?>
						<li>
							<a href="#moreScreen" class="list_tabbar tabbar-tab-more-tab<?php echo esc_attr($tabClass); ?>" data-icon="custom" data-page-type="expendedList">
								<?php echo esc_html(__('More', 'wiziapp')).PHP_EOL; ?>
							</a>
						</li>
						<?php
					}
					?>
				</ul>
			</div><!-- /tabbar-->
		</div><!-- /footer -->
		<?php

		return ob_get_clean();
	}

	public function getDefaultTab(){
		for ($t = 0; $t < count($this->tabs); ++$t){
			$tab = $this->tabs[$t];

			if ($tab->id === $this->selectedTab){
				$actionParams = explode('://', $tab->rootScreenURL);
				$screenParams = explode('/', $actionParams[1]);
				$screenURL = urldecode($screenParams[1]);

				// Add the webapp needed qs params
				$screenURL .= WiziappLinks::getAppend().'&back=0';
				if ( $tab->type === 'favorites') {
					$screenURL = '#'.$tab->type;
				}
				return $screenURL;
			}
		}

		for ($t = 0; $t < count($this->tabs); ++$t){
			$tab = $this->tabs[$t];
			$actionParams = explode('://', $tab->rootScreenURL);
			$screenParams = explode('/', $actionParams[1]);
			$screenURL = urldecode($screenParams[1]);

			// Add the webapp needed qs params
			$screenURL .= WiziappLinks::getAppend().'&back=0';
			if ( $tab->type === 'favorites') {
				$screenURL = '#'.$tab->type;
			}

			return $screenURL;
		}

		return '#';
	}

	public function post_header_bar($post){
		$url = 'nav://list/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . '/?wiziapp/content/list/posts/recent');
		$title = WiziappConfig::getInstance()->app_name;

		if ( isset($_GET['cat']) ){
			foreach ( get_the_category($post->ID) as $cat ){
				$cur_cat = $cat;

				while ($cur_cat){
					if ($_GET['cat'] == $cur_cat->cat_ID){
						$url = WiziappLinks::categoryLink($cur_cat->cat_ID);
						$title = $cur_cat->cat_name;
						break;
					}

					if ($cur_cat->category_parent){
						$cur_cat = get_category($cur_cat->category_parent);
					} else {
						break;
					}
				}
			}
		}

		if ( isset($_GET['tag']) ){
			foreach ( get_the_tags($post->ID) as $tag ){
				if ( $_GET['tag'] == $tag->term_id ){
					$url = WiziappLinks::tagLink($tag->term_id);
					$title = $tag->name;
					break;
				}
			}
		}

		if ( isset($_GET['commented']) ){
			foreach (get_approved_comments($post->ID) as $comment){
				if ($_GET['commented'] == $comment->user_id){
					$url = 'nav://list/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . "/?wiziapp/content/list/user/{$comment->user_id}/commented");
					$title = __('My Commented Posts', 'wiziapp');
					break;
				}
			}
		}

		if ( isset($_GET['author']) && $_GET['author'] == $post->post_author ){
			$authorInfo = get_userdata($post->post_author);
			$url = WiziappLinks::authorLink($post->post_author);
			$title = __("Posts By:", 'wiziapp').' '.$authorInfo->display_name;
		}

		if ( isset($_GET['favorites']) ){
			$screen = new WiziappPostsScreen();
			$url = '#favorites';
			$title = $screen->getTitle('favorites');
		}

		if ( isset($_GET['search']) && $_GET['search'] === "1" ){
			$screen = new WiziappPostsScreen();
			$url = '#search';
			$title = $screen->getTitle('search');

			if ( ! empty($_GET['keyword']) ){
				$url = 'nav://list/'.urlencode(WiziappContentHandler::getInstance()->get_blog_property('url').'/?wiziapp/search&category=all&search=1&keyword='.$_GET['keyword'].'&submit=Search');
			}
		}

		if ( isset($_GET['from_attachment_id']) ){
			$attachment = get_post($_GET['from_attachment_id']);
			if ($attachment && $attachment->post_parent == $post->ID){
				$url = 'nav://list/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . '/?wiziapp/content/list/attachment/'.$attachment->ID.'/posts');
				$title = __('Related Posts', 'wiziapp');
			}
		}

		if ( isset($_GET['year']) && isset($_GET['monthnum']) && preg_match('/^'.preg_quote($_GET['year']).'-0*'.preg_quote($_GET['monthnum']).'-[0-9]+( |$)/', $post->post_date) ){
			global $wp_locale;
			if ( isset($_GET['day']) && preg_match('/^'.preg_quote($_GET['year']).'-0*'.preg_quote($_GET['monthnum']).'-0*'.preg_quote($_GET['day']).'( |$)/', $post->post_date) ){
				$url = 'nav://list/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . '/?wiziapp/content/list/archive/'.$_GET['year'].'/'.$_GET['monthnum'].'/'.$_GET['day']);
				$title = sprintf(__('%3$d %1$s %2$d'), $wp_locale->get_month($_GET['monthnum']), $_GET['year'], $_GET['day']);
			} else {
				$url = 'nav://list/' . urlencode(WiziappContentHandler::getInstance()->get_blog_property('url') . '/?wiziapp/content/list/archive/'.$_GET['year'].'/'.$_GET['monthnum']);
				$title = sprintf(__('%1$s %2$d'), $wp_locale->get_month($_GET['monthnum']), $_GET['year']);
			}
		}
		?>
		<div data-role="header" data-id="header" data-position="fixed">
			<?php
			wiziapp_back_button($url, $title);
			?>
			<h1><?php echo WiziappConfig::getInstance()->app_name; ?></h1>
		</div><!-- /header -->
		<?php
	}

	public function post_footer_bar($post){
		$previous_post_url = ($this->postPrev === FALSE)?WiziappLinks::adjacent_post_url(FALSE, '', TRUE):$this->postPrev;
		$button_left = ( $previous_post_url === '' ) ? 'left_disabled' : 'left_enabled';

		$next_post_url = ($this->postNext === FALSE)?WiziappLinks::adjacent_post_url(FALSE, '', FALSE):$this->postNext;
		$button_right = ( $next_post_url === '' ) ? 'right_disabled' : 'right_enabled';

		$url_add = '';
		if ( isset($_GET['androidapp']) && $_GET['androidapp'] === 1 ) {
			$url_add .= '&androidapp=1';
		}
		?>
		<div data-id="single-tabbar" data-role="footer" class="nav-tabbar" data-position="fixed" data-tap-toggle="false">
			<div data-role="navbar" class="webview_bar">
				<ul>
					<li>
						<a href="#sharing_menu" class="post_button_share" data-rel="dialog" data-transition="pop"></a>
					</li>
					<li>
						<div class="post_button_fontsize">
							<div class="hidden font_panel">
								<div class="plus_button"></div>
								<div class="minus_button"></div>
							</div>
						</div>
					</li>
					<li>
						<div class="left_right_buttons_wrapper">
							<a href="<?php echo $previous_post_url;  ?>" class="post_toolbar_buttons <?php echo $button_left;  ?>"></a>
							<a href="<?php echo $next_post_url; 	 ?>" class="post_toolbar_buttons <?php echo $button_right; ?>"></a>
						</div>
					</li>
					<li>
						<?php
						$config = WiziappComponentsConfiguration::getInstance();
						if ( ! in_array( 'numOfComments', $config->getAttrToRemove('postDescriptionCellItem') ) ) {
							$comments_path = '?wiziapp/content/list/post/'.$post->ID.'/comments'.WiziappLinks::getAppend();
							?>
							<a href="<?php echo WiziappContentHandler::getInstance()->get_blog_property('url').'/'.$comments_path; ?>" class="post_button_show_comments">
								<?php echo $post->comment_count.PHP_EOL; ?>
							</a>
							<?php
						}
						?>
					</li>
				</ul>
			</div><!-- /navbar -->
		</div><!-- /footer -->
		<?php
	}

	public function getBackButton($selectedURL = false){
		$selectedTab = $this->getTabFromURL($selectedURL);
		$end = $this->gotMorePage() ? 4 : count($this->tabs);

		if ( 'moreScreen' === $selectedURL ){
			return false;
		}

		for ($t = count($this->tabs); $t > $end; ){
			--$t;
			$tab = $this->tabs[$t];

			if ( $tab->id === $selectedTab ){
				wiziapp_back_button('#moreScreen', __('More', 'wiziapp'));
				return true;
			}
		}

		return false;
	}

	public function getTabFromURL($selectedURL = false){
		if ($selectedURL === false){
			$selectedURL = $_SERVER['REQUEST_URI'];
		}

		$selectedTab = false;
		for ($t = 0; $t < count($this->tabs); ++$t){
			$tab = $this->tabs[$t];

			if ($tab->type === $selectedURL){
				$selectedTab = $tab->id;
				break;
			}

			$url = explode('://', $tab->rootScreenURL, 2);
			$url = isset($url[1])?explode('/', $url[1], 2):'';
			$url = isset($url[1])?explode('?', urldecode($url[1]), 2):'';
			$url = isset($url[1])?$url[1]:'';

			// FIXME: Go by longest match?
			if ($url != '' && strpos($selectedURL, $url) !== false){
				$selectedTab = $tab->id;
				break;
			}
		}

		return $selectedTab;
	}
}