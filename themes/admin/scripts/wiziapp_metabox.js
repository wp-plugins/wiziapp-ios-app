(function($) {

	$(document).ready(function() {
		wiziapp_push_message = $('#wiziapp_push_message');
		wiziapp_push_message.val( wiziapp_push_message.attr('data-push-message') );

		wiziapp_push_message
		.after(jqEasyCounterMsg)
		.bind('keydown keyup keypress', doCount)
		.bind('focus paste', function() { setTimeout(doCount, 10); })
		.bind('blur', countStop);
	});

	var wiziapp_push_message;
	var jqEasyCounterMsg = $('<div>&nbsp;</div>');

	var counter_options = {
		maxChars: 105,
		maxCharsWarning: 90,
	};

	function countStop() {
		jqEasyCounterMsg
		.stop()
		.fadeTo( 'fast', 0);

		return false;
	}

	function doCount() {
		var val = wiziapp_push_message.val();
		var message_length = val.length;

		if (message_length > counter_options.maxChars) {
			wiziapp_push_message
			.val(val.substring(0, counter_options.maxChars))
			.scrollTop(wiziapp_push_message.scrollTop());
		};

		if (message_length > counter_options.maxCharsWarning) {
			jqEasyCounterMsg.css({"color" : "#F00"});
		} else {
			jqEasyCounterMsg.css({"color" : "#000"});
		};

		jqEasyCounterMsg
		.text('Maximum 105 Characters. Printed: ' + message_length + "/" + counter_options.maxChars)
		.stop()
		.fadeTo('fast', 1);
	}

})(jQuery);