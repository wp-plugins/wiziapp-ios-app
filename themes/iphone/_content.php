<?php
wiziapp_get_header();
$wiziapp_google_adsense = WiziappHelpers::get_adsense();

	// Before handing the content, make sure this post is scanned
	$processed = get_post_meta($post->ID, 'wiziapp_processed');
	if (empty($processed)) {
		$ce = new WiziappContentEvents();
		$ce->savePost($post);
	}
?>
		<div class="page_content<?php echo $wiziapp_google_adsense['is_shown'] ? ' wiziapp_google_adsenes' : ''; ?>">
			<div class="post">
				<?php
					$pLink = WiziappLinks::postLink($post->ID);

					if ( is_page($post->ID) ) {
						$config = WiziappComponentsConfiguration::getInstance();

						if ( in_array( 'pages', $config->getAttrToAdd('postDescriptionCellItem') ) ) {
							$subPages = get_pages(array(
								'child_of' => $post->ID,
								'sort_column' => 'menu_order',
								'exclude_tree' => 1,
							));

							if ($subPages) {
							?>
							<div class="postDescriptionCellItem_pages">
								<ul class="wiziapp_bottom_nav wiziapp_pages_nav albums_list">
									<?php
										foreach ($subPages as $subPage) {
							if ($subPage->post_parent == $post->ID) {
										?>
										<li>
											<a href="<?php echo WiziappLinks::pageLink($subPage->ID); ?>">
												<div class="album_item wiziapp_pages_item">
													<p class="attribute text_attribute title wiziapp_pages_title"><?php echo ($subPage->post_title); ?></p>
													<span class="rowCellIndicator"></span>
												</div>
											</a>
										</li>
										<?php
										}
									}
									?>
								</ul>
							</div>
							<?php
							}
						}
					}
				?>

				<h2 class="pageitem">
					<a id="post_title" href="<?php echo $pLink ?>" rel="bookmark" title="<?php the_title(); ?>">
						<?php the_title(); ?>
					</a>
				</h2>

				<div class="pageitem">
			<?php
				if ( isset($post->post_type) && $post->post_type === 'post' ) {
					?>
					<div class="single-post-meta-top">
						<div id="author_and_date">
							<span class="postDescriptionCellItem_author">
								By <a href="<?php echo WiziappLinks::authorLink($post->post_author); ?>"><?php the_author(); ?></a>
							</span>
							&nbsp;
							<span class="postDescriptionCellItem_date"><?php echo WiziappTheme::formatDate($post->post_date); ?></span>
						</div>
					</div>
					<div class="clear"></div>
					<?php
				}

				if ( $wiziapp_google_adsense['show_in_post'] & $wiziapp_google_adsense['upper_mask'] ) {
					echo $wiziapp_google_adsense['code'];
				}
			?>

					<div class="post" id="post-<?php the_ID(); ?>">
						<div id="singlentry">
							<?php
								WiziappProfiler::getInstance()->write('Before the thumb inside the post ' . $post->ID, 'theme._content');

								@set_time_limit(60);
								WiziappThumbnailHandler::getPostThumbnail($post, 'posts_thumb');

								WiziappProfiler::getInstance()->write('after the thumb inside the post ' . $post->ID, 'theme._content');
								WiziappProfiler::getInstance()->write('Before the content inside the post ' . $post->ID, 'theme._content');

								global $more;
								$more = -1;

								the_content('');

								WiziappProfiler::getInstance()->write('After the content inside the post ' . $post->ID, 'theme._content');
							?>
						</div>
					</div>
				</div>
				<?php
				if ( ! is_page() ) {
			?>
					<div class="clear"></div>
					<ul class="wiziapp_bottom_nav">
						<?php
							WiziappTheme::getCategoriesNav();
							WiziappTheme::getTagsNav();
						?>
					</ul>
					<div class="clear"></div>
			<?php
		}

		if ( $wiziapp_google_adsense['show_in_post'] & $wiziapp_google_adsense['lower_mask'] ) {
			echo $wiziapp_google_adsense['code'];
		}
?>
			</div>
			<br />
<?php /*
			<div id="debug" style="background-color: #c0c0c0;">
				####AREA 51####
				<div id="swipeme" style="height: 50px; background-color: #ccc;">
					PLACE HOLDER
				</div>
				<a id="reload" href="#" onclick="top.location.reload(true)">RELOAD</a><br />
				<a id="swipeLeft" href="cmd://event/swipeRight"></a>
				<a id="swipeRight" href="cmd://event/swipeLeft"></a>
			</div>
*/ ?>
			<!-- The link below is for handing video in the simulator, the application shows the video itself while the simulator only shows an image. -->
			<a href="cmd://open/video" id="dummy_video_opener"></a>

<?php
		if ( WiziappConfig::getInstance()->is_paid !== '1' ){
			echo WiziappConfig::getInstance()->getWiziappBranding();
		}
?>
		</div><!-- page_content -->
<?php
	//wp_footer(); - no need for this
	/**
	* @todo once the major part of the development is over move the script to the cdn, everything but the variables
	* scripts in the cdn in production mode are combined and minimized.
	* Beside... this code might be needed in other CMS
	*/
?>
<div id="templates" class="hidden">
	<div id="album_template">
		<ul class="wiziapp_bottom_nav albums_list">
			<li>
				<a class="albumURL" href="javascript:void(0);">
					<div class="imagesAlbumCellItem album_item">
						<div class="attribute imageURL album_item_image"></div>
						<div class="album_item_decor"></div>
						<p class="attribute text_attribute title album_item_title"></p>
						<div class="numOfImages attribute text_attribute album_item_numOfImages"></div>
						<span class="rowCellIndicator"></span>
					</div>
				</a>
			</li>
		</ul>
	</div>
</div>

<script type="text/javascript">
	<?php
		/**
		* This class handle all the webview events and provides an external interface for the application
		* and the simulator. The simulator is getting some special treatment to help capture links and such
		*/
	?>

	window.galleryPrefix = "<?php echo WiziappLinks::postImagesGalleryLink($post->ID); ?>%2F";
	window.wiziappDebug = <?php echo (isset(WiziappConfig::getInstance()->wiziapp_log_threshold) && intval(WiziappConfig::getInstance()->wiziapp_log_threshold) !== 0) ? "true" : "false"; ?>;
	window.wiziappPostHeaders = <?php echo json_encode(WiziappTheme::getPostHeaders(FALSE)); ?>;
	window.wiziappRatingUrl = '<?php echo WiziappContentHandler::getInstance()->get_blog_property('url'); ?>/?wiziapp/getrate/post/<?php echo $post->ID ?>';
	window.wiziappCommentsCountUrl = '<?php echo WiziappContentHandler::getInstance()->get_blog_property('url'); ?>/?wiziapp/post/<?php echo $post->ID?>/comments';
	window.multiImageWidthLimit = "<?php echo WiziappConfig::getInstance()->multi_image_width; ?>";
	window.multiImageHeightLimit = "<?php echo WiziappConfig::getInstance()->multi_image_height; ?>";
	window.simMode = <?php echo (isset($_GET['sim']) && $_GET['sim']) ? 'true' : 'false'; ?>;
	window.wiziappCdn = "<?php echo WiziappConfig::getInstance()->getCdnServer(); ?>";
	window.WIZIAPP.doLoad();
</script>
		<?php
			wiziapp_get_footer();
