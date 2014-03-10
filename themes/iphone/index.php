<?php

WiziappLog::getInstance()->write('INFO', 'Loaded index template', 'themes.default.index');

global $wiziapp_block, $cPage, $nextPost, $prevPost, $postsScreen, $wiziappQuery;

/**
* Start wordpress loop, the condition for the loop was prepared in the screens functions
* but a minute before starting the loop get the header and footer to try and avoid problems with plugins
* that from some reason resets the query object or more it, or messing with the buffers
*/
WiziappLog::getInstance()->write('INFO', 'Registered scripts', 'themes.default.index');
wp_reset_query();
WiziappLog::getInstance()->write('INFO', 'Wordpress loop reset', 'themes.default.index');
query_posts($wiziappQuery);
WiziappLog::getInstance()->write('INFO', 'Queried the posts: '.$GLOBALS['wp_query']->post_count, 'themes.default.index');

if ( ! have_posts() ){
	WiziappLog::getInstance()->write('ERROR', "No posts???", "themes.iphone.index");
}

WiziappLog::getInstance()->write('INFO', 'We have posts to process', 'themes.default.index');
$GLOBALS['WiziappOverrideScripts'] = TRUE;
if (!isset($GLOBALS['WiziappEtagOverride'])){
	$GLOBALS['WiziappEtagOverride'] = '';
}

/* Pre-copy posts array */
ob_start();
$waPosts = array();
while ( have_posts() ){
	the_post();
	$waPosts[] = $post->ID;
	$GLOBALS['WiziappEtagOverride'] .= serialize($post);
	WiziappLog::getInstance()->write('INFO', "The id: {$post->ID}", 'themes.default.index');

	if ( isset($GLOBALS['wp_posts_listed']) ){
		if ( in_array($post->ID, $GLOBALS['wp_posts_listed']) ){
			continue;
		} else {
			$GLOBALS['wp_posts_listed'][] = $post->ID;
		}
	}
}
ob_end_clean();

// In case something in the template changed, add the modified date to the etag
$GLOBALS['WiziappEtagOverride'] .= date("F d Y H:i:s.", filemtime(dirname(__FILE__).'/_content.php'));
$GLOBALS['WiziappEtagOverride'] .= date("F d Y H:i:s.", filemtime(dirname(__FILE__).'/index.php'));

if ( WiziappConfig::getInstance()->usePostsPreloading() ){
	// Start capturing output from loop events
	ob_start();

	foreach ($waPosts as $waPost){
		query_posts( array( 'p' => $waPost, 'post_type' => WiziappComponentsConfiguration::getInstance()->get_post_types(), ) );

		if ( have_posts() ){
			the_post();

			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			if ( is_plugin_active('pull-product.php') ) {
				// The "Pull Product" plugin at work uses a action "template_redirect".
				// So, to ensure the plugin operation in our "?wiziapp/content/list/posts/recent' flow, need duplication the action here.
				do_action( 'template_redirect' );
			}

			// In this template we are only doing posts list for posts list we will to pre-load the post template,
			// so get the template inside a string to pass it to the component building functions.
			WiziappLog::getInstance()->write('INFO', 'Preloading the posts', 'themes.default.index');
			ob_start();
			$obLevelStart = ob_get_level();

			include('_content.php');

			$contents = ob_get_contents();

			$obLevelEnd = ob_get_level();
			if ( $obLevelEnd == $obLevelStart ){
				ob_end_clean();
			} elseif ( $obLevelEnd > $obLevelStart ){
				// Someone opened a new output buffer cache that might mess up our loop, reset the buffer to what we need
				while ( $obLevelEnd > $obLevelStart ){
					ob_end_clean();
					--$obLevelEnd;
				}
			} else {
				// Someone closed it for us, just make sure it is cleaned
				ob_clean();
			}

			$postsScreen->appendComponentByLayout($cPage, $wiziapp_block, $waPost, $contents);
		}
	}

	ob_end_clean();
	// End capturing output from loop events
} else {
	foreach ($waPosts as $waPost) {
		$postsScreen->appendComponentByLayout($cPage, $wiziapp_block, $waPost, null);
	}
}