<!-- Footer -->
<?php
	if ( !empty($GLOBALS['wpFooterHtml']) ){
		echo $GLOBALS['wpFooterHtml'];
	} else {
		wp_footer();
	}
?>
</body>
</html>