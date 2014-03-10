<div>
	<input type="checkbox" <?php echo $send_wiziapp_push_checked; ?> name="is_send_wiziapp_push" value="1" /> Send Push Notification
	<p>Push notification text message:</p>
	<textarea id="wiziapp_push_message" name="wiziapp_push_message" data-push-message="<?php echo $push_message; ?>"></textarea>
</div>