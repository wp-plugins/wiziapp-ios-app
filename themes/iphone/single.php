<?php

	ob_start();
	$processed = FALSE;

	if ( have_posts() ) {
		while ( have_posts() && ! $processed ) {
			global $post;
			setup_postdata($post);

			WiziappTheme::getPostHeaders(true);
			echo ob_get_clean();

			include('_content.php');
			$processed = TRUE;
		}
	} else {
		WiziappLog::getInstance()->write('ERROR', "No posts???", "themes.iphone.single");
}