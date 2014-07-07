var WIZIAPP_HANDLER = (function($){
	function wiziappReceiveMessage(event){
		// Just wrap our handleRequest
		if ( event.origin === WIZIAPP_HANDLER_ADDITIONAL_PARAMS.http_api_server || event.origin === WIZIAPP_HANDLER_ADDITIONAL_PARAMS.https_api_server ){
			WIZIAPP_HANDLER.handleRequest(event.data);
		}
	};
	if ( window.addEventListener ){
		window.addEventListener("message", wiziappReceiveMessage, false);
	}

	$(document).ready(function(){
		WIZIAPP_HANDLER.wiziapp_generator_container = $("#wiziapp_generator_container");

		$("<iframe frameborder='0'>")
		.css({
			'overflow': 'hidden',
			'width': '100%',
			'height': '1000px',
			'border': '0px none'
		})
		.attr({
			'src': WIZIAPP_HANDLER_ADDITIONAL_PARAMS.iframeSrc,
			'frameborder': '0',
			'id': WIZIAPP_HANDLER_ADDITIONAL_PARAMS.iframeId
		})
		.prependTo(WIZIAPP_HANDLER.wiziapp_generator_container);

		$('.report_issue').click(reportIssue);
		$('.retry_processing').click(retryProcessing);

		$('#general_error_modal').bind('closingReportForm', function(){
			$(this).addClass('s_container');
		});
	});

	var actions = {
		informErrorProcessing: function(params){
			var $box = $('#'+params.el);
			$box
			.find('.processing_message').hide().end()
			.find('.loading_indicator').hide().end()
			.find('.error').text(params.message).show().end()
			.find('.close').show().end();

			$box = null;
		},
		closeProcessing: function(params){
			$('#'+params.el).data("overlay").close();
			if ( typeof(params.scrollTop) != 'undefined' ){
				$(document).scrollTop(0);
			}

			if (typeof(params.reload) != 'undefined'){
				if (params.reload == 1){
					if (typeof(params.qs) != 'undefined'){
						var href = top.location.href;
						var seperator = '?';
						if (href.indexOf('?')){
							seperator = '&';
						}
						href += seperator + unescape(params.qs);
						top.location.replace(href);
					} else {
						top.location.reload(true);
					}
				}
			}

			if ( typeof(params.resizeTo) != 'undefined' ){
				actions.resizeGeneratorIframe({height: params.resizeTo});
			}
		},
		informGeneralError: function(params){
			var $box = $('#'+params.el);
			$box
			.find('.wiziapp_error').text(params.message).end();

			if ( parseInt(params.retry) == 0 ){
				$box.find('.retry_processing').hide();
			} else {
				$box.find('.retry_processing').show();
			}

			if ( parseInt(params.report) == 0 ){
				$box.find('.report_issue').hide();
			} else {
				$box.find('.report_issue').show();
			}

			if (!$box.data("overlay")){
				$box.overlay({
					fixed: true,
					top: 200,
					left: (screen.width / 2) - ($box.outerWidth() / 2),
					// disable this for modal dialog-type of overlays
					closeOnClick: false,
					closeOnEsc: false,
					// load it immediately after the construction
					load: true,
					onBeforeLoad: function(){
						var $toCover = $('#wpbody');
						var $mask = $('#wiziapp_error_mask');
						if ( $mask.length == 0 ){
							$mask = $('<div></div>').attr("id", "wiziapp_error_mask");
							$("body").append($mask);
						}

						$mask.css({
							position:'absolute',
							top: $toCover.offset().top,
							left: $toCover.offset().left,
							width: $toCover.outerWidth(),
							height: $toCover.outerHeight(),
							display: 'block',
							opacity: 0.9,
							backgroundColor: '#444444'
						});

						$mask = $toCover = null;
					}
				});
			} else {
				$box.show();
				$box.data("overlay").load();
			}
			$box = null;
		},
		showProcessing: function(params){
			var $box = $('#'+params.el);
			$box
			.find('.error').hide().end()
			.find('.loading_indicator').show().end()
			.find('.close:not(.nohide)').hide().end()
			.find('.processing_message').show().end();

			if (!$box.data("overlay")){
				$box.overlay({
					fixed: true,
					top: 200,
					left: (screen.width / 2) - ($box.outerWidth() / 2),
					mask: {
						color: '#444444',
						loadSpeed: 200,
						opacity: 0.9
					},

					// disable this for modal dialog-type of overlays
					closeOnClick: false,
					// load it immediately after the construction
					load: true
				});
			} else {
				$box.show();
				$box.data("overlay").load();
			}

			$box = null;
		},
		showSim: function(params){
			var url = decodeURIComponent(params.url);
			url = url + '&rnd=' + Math.floor(Math.random()*999999);
			var $box = $("#wiziappBoxWrapper");
			if ($box.length == 0){
				$box = $("<div id='wiziappBoxWrapper'><div class='close overlay_close'></div><div id='loading_placeholder'>Loading...</div><iframe id='wiziappBox'></iframe>");
				$box.find("iframe").attr('src', url+"&preview=1").unbind('load').bind('load', function(){
					$("#wiziappBoxWrapper").addClass('sim_loaded');
				});

				$box.appendTo(document.body);

				$box.find("iframe").css({
					'border': '0px none',
					'height': '760px',
					'width': '390px'
				});

				$box.overlay({
					top: 20,
					fixed: false,
					mask: {
						color: '#444',
						loadSpeed: 200,
						opacity: 0.8
					},
					closeOnClick: true,
					onClose: function(){
						$("#wiziappBoxWrapper").remove();
					},
					load: true
				});
			} else {
				$box.show();
				$box.data("overlay").load();
			}

			$box = null;
		},
		resizeGeneratorIframe: function(params){
			$("#" + WIZIAPP_HANDLER_ADDITIONAL_PARAMS.iframeId).css({
				'height': (parseInt(params.height) + 50) + 'px'
			});
		}
	};

	function retryProcessing(event){
		event.preventDefault();
		document.location.reload(true);
		return false;
	}

	function registerLicense(event){
		if ( $(this).is('.pending') ){
			return false;
		}
		$(this).addClass('pending');

		$('#enter_license_modal .error').hide();
		var key = $('#enter_license_modal input').val();
		if ( key.length == 0 ){
			$(this).removeClass('pending');
			return false;
		}

		var params = {
			'action': 'wiziapp_register_license',
			'key': key
		};

		$.post(ajaxurl, params, function(data){
			if ( data && data.header && data.header.status ){
				// License updated, inform and reload
				$('#enter_license_modal .success').text('License key updated, please standby...').show();
				top.document.location.reload(true);
			} else {
				// Error,
				$('#enter_license_modal .error')
				.text(data.header.message)
				.show();
			}
			$('#submit_license').removeClass('pending');
			}, 'json');
	}

	function reportIssue(event){
		// Change the current box style so it will enable containing the report form
		event.preventDefault();
		var $box = $('#general_error_modal');
		var $el = $box.find('.report_container');

		var params = {
			action: 'wiziapp_report_issue',
			data: $box.find('.wiziapp_error').text()
		};

		$el.load(ajaxurl, params, function(){
			var $mainEl = $('#general_error_modal');
			$mainEl
			.removeClass('s_container')
			.find(".errors_container").hide().end()
			.find(".report_container").show().end();

			$mainEl = null;
		});

		var $el = null;
		return false;
	}

	return {
		handleRequest: function(q){
			var paramsArray = q.split('&');
			var params = {};
			for (var i = 0; i < paramsArray.length; ++i){
				var parts = paramsArray[i].split('=');
				params[parts[0]] = decodeURIComponent(parts[1]);
			}
			if (typeof(actions[params.action]) == "function"){
				actions[params.action](params);
			}
			params = q = paramsArray = null;
		},
		wiziapp_generator_container: {}
	};
})(jQuery);