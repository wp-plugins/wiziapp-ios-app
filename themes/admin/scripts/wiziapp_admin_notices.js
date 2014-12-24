(function($){
	$(document).ready(function() {

		$("#wiziapp_active_notice div:first-child").click(function() {
			window.location="admin.php?page=wiziapp";
		});

		$("#wiziapp_active_notice div:last-child").click(function() {
			$("#wiziapp_active_notice").hide("slow");
		});

		$("#wiziappHideVerify").click(function() {
			var params = {
				action: 'wiziapp_hide_verify_msg'
			};

			$.post(ajaxurl, params, function(data) {
				$("#wiziapp_email_verified_message").remove();
			});
		});

	});
})( jQuery );