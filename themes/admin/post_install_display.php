<?php
	echo $errorsHtml;
?>

<div id="wiziapp_activation_container" class="no_js" data-from-php-to-js='<?php echo json_encode($from_php_to_js); ?>'>
	<div id="wiziapp_js_disabled">
		<div id="js_error" class="wiziapp_errors_container s_container">
			<div class="errors">
				<div class="wiziapp_error"><?php echo __('It appears that your browser is blocking the use of javascript. Please change your browser\'s settings and try again', 'wiziapp');?></div>
			</div>
		</div>
	</div>

	<div id="wiziapp_js_enabled">
		<div id="just_a_moment" style="background: url(<?php echo WiziappConfig::getInstance()->getCdnServer(); ?>/images/cms/processingJustAmoment.png) no-repeat top center;"></div>
		<p id="wizi_be_patient" class="text_label"><?php echo __('Please be patient while we generate your app. It may take several minutes.', 'wiziapp');?></p>
		<div id="wizi_icon_wrapper">
			<div id="wizi_icon_processing" style="background: url(<?php echo WiziappConfig::getInstance()->getCdnServer(); ?>/images/cms/wiziapp_processing_icon.png) no-repeat top center;"></div>
			<div id="current_progress_label" class="text_label"><?php echo __('Initializing...', 'wiziapp'); ?></div>
		</div>
		<div id="main_progress_bar_container">
			<div id="main_progress_bar"></div>
			<div id="main_progress_bar_bg" style="background: url(<?php echo WiziappConfig::getInstance()->getCdnServer(); ?>/images/cms/progress_bar_bg.png) no-repeat;"></div>
		</div>
		<p id="current_progress_indicator" class="text_label"></p>

		<p id="wiziapp_finalize_title" class="text_label">
			<?php echo __('Ready, if the wizard doesn\'t load itself in a couple of seconds click ', 'wiziapp'); ?>
			<span id="finializing_activation"><?php echo __('here', 'wiziapp'); ?></span>
		</p>

		<div id="error_activating" class="wiziapp_errors_container s_container hidden">
			<div class="errors">
				<div class="wiziapp_error">
					<?php echo __('There was an error loading the wizard, please contact support', 'wiziapp');?>
				</div>
			</div>
		</div>

		<div id="internal_error" class="wiziapp_errors_container s_container hidden">
			<div class="errors">
				<div class="wiziapp_error"><?php echo __('Connection error. Please try again.,', 'wiziapp');?></div>
				<div class="buttons">
					<a href="javscript:void(0);" class="retry_processing"><?php echo __('retry', 'wiziapp'); ?></a>
				</div>
			</div>
		</div>

		<div id="internal_error_2" class="wiziapp_errors_container s_container hidden">
			<div class="errors">
				<div class="wiziapp_error">
					<?php echo __('There were still errors contacting your server, please contact support', 'wiziapp');?>
				</div>
			</div>
		</div>

		<div id="error_network" class="wiziapp_errors_container s_container hidden">
			<div class="errors">
				<div class="wiziapp_error"><?php echo __('Connection error. Please try again.', 'wiziapp');?></div>
			</div>
			<div class="buttons">
				<a href="javscript:void(0);" class="retry_processing"><?php echo __('retry', 'wiziapp'); ?></a>
			</div>
		</div>
	</div>
</div>