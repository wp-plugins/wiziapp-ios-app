(function($){

	var batch_size = 1;
	var progressTimer = null;
	var progressWait = 30;
	var from_php_to_js = {};

	$(document).ready(function(){
		// To check, if Javascript enabled in the Browser, do $("#wiziapp_activation_container").addClass("js")
		from_php_to_js = $.parseJSON( $("#wiziapp_activation_container").addClass("js").attr("data-from-php-to-js") );

		wiziappRegisterAjaxErrorHandler();

		// Register the report an error button
		$('#wiziapp_report_problem').click(report_problem);

		$(".retry_processing").click(retryRequest);

		if ( from_php_to_js.can_run ){
			// Start sending requests to generate content till we are getting a flag showing we are done
			startProcessing();
		} else {
			// Show the overlay
			var $box = $('#wiziapp_compatibilities_errors');

			var overlayParams = {
				top: 100,
				left: (screen.width / 2) - ($box.outerWidth() / 2),
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
				},
				// disable this for modal dialog-type of overlays
				closeOnClick: false,
				closeOnEsc: false,
				// load it immediately after the construction
				load: true
			};

			$box.overlay(overlayParams);
		}
	});

	function report_problem(event){
		event.preventDefault();

		var $el = $(this).parents(".wiziapp_errors_container").find(".report_container");
		var data = {};

		$.each($('.wiziapp_error'), function(index, error){
			var text = $(error).text();

			if ( text.indexOf('.') !== -1 ){
				text = text.substr(0, text.indexOf('.'));
			}

			data[index] = text;
		});

		var params = {
			action: 'wiziapp_report_issue',
			data: $.param(data, true)
		};

		$el.load(ajaxurl, params, function(){
			var $mainEl = $(".wiziapp_errors_container");

			$mainEl
			.find(".errors_container").hide().end()
			.find(".report_container").show().end();

			$mainEl = null;
		});

		var $el = null;
		return false;
	}

	function retryRequest(event){
		event.preventDefault();

		var $el = $(this);

		var request =
		$el
		.parents('.wiziapp_errors_container')
		.data('reqObj');

		$el
		.parents('.wiziapp_errors_container')
		.hide();

		delete request.context;
		delete request.accepts;

		$.ajax(request);

		$el = null;
		return false;
	}

	function retryingFailed(req, error){
		$("#internal_error_2").show();
	}

	function startProcessing(){
		if ( from_php_to_js.page_ids.length > 0 ){
			requestPageProcessing();
		} else if ( from_php_to_js.post_ids.length > 0 ){
			requestPostProcessing();
		} else {
			requestFinalizingProcessing();
		}
	}

	function wiziappRegisterAjaxErrorHandler(){
		$.ajaxSetup({
			timeout: 60*1000,
			error:function(req, error){
				clearTimeout(progressTimer);
				if (error == 'timeout'){
					// $("#internal_error").data('reqObj', this).show();
					startProcessing();
				} else if (req.status == 0){
					$("#error_network").data('reqObj', this).show();
				} else if (req.status == 404){
					$("#error_activating").show();
				} else if (req.status == 500){
					// Check if this is our request..
					var data = $.parseJSON(req.responseText);

					if (data){
						var requestParams = this.data.split('&');
						var itemsStr = requestParams[requestParams.length - 1].split('=')[1];

						var neededAction = '';
						var type = '';
						var failed = '';

						if ( typeof(data.post) == 'undefined' ){
							itemsStr = itemsStr.replace(data.page, '');
							neededAction = 'wiziapp_batch_process_pages';
							type = 'pages';
							failed = data.page;
						} else {
							itemsStr = itemsStr.replace(data.post, '');
							neededAction = 'wiziapp_batch_process_posts';
							type = 'posts';
							failed = data.post;
						}

						var items = unescape(itemsStr).split(',');
						var noErrorItems = cleanArray(items);

						if (noErrorItems.length > 0){
							var params = {
								action: neededAction,
								failed: failed
							};
							params[type] = noErrorItems.join(',');

							if (type == 'posts'){
								$.post(ajaxurl, params, handlePostProcessing, 'json');
							} else if (type == 'pages'){
								$.post(ajaxurl, params, handlePageProcessing, 'json');
							}
						} else {
							// Maybe there are more items in the queue
							startProcessing();
						}
					} else {
						// $("#internal_error").data('reqObj', this).show();
						// Don't show the errors, just try to continue
						startProcessing();
					}
				} else if (error == 'parsererror'){
					// $("#error_activating").show();
					startProcessing();
					/*
					} else if(error == 'timeout'){
					// $("#error_network").show();
					$("#internal_error").data('reqObj', this).show();
					*/
				} else {
					$("#error_activating").show();
				}
			}
		});
	}

	function cleanArray(arr){
		var newArr = new Array();

		for (k in arr){
			if ( arr.hasOwnProperty(k) && arr[k] ){
				newArr.push(arr[k]);
			}
		}

		return newArr;
	}

	function requestPageProcessing(){
		var pages = from_php_to_js.page_ids.splice(0, batch_size);

		var params = {
			action: 'wiziapp_batch_process_pages',
			pages: pages.join(',')
		};

		$.post(ajaxurl, params, handlePageProcessing, 'json');
		progressTimer = setTimeout(updateProgressBarByTimer, 1000 * progressWait);
	}

	function handlePageProcessing(data){
		// Update the progress bar
		updateProgressBar();

		if ( typeof(data) == 'undefined' || ! data ){
			// The request failed from some reason... skip it
			startProcessing();
			return;
		}

		if (data.header.status){
			if ( from_php_to_js.page_ids.length == 0 ){
				requestPostProcessing();
			} else {
				requestPageProcessing();
			}
		} else {
			var params = this.data.split('&');
			var pagesStr = params[1].split('=')[1].replace(data.page, '');
			var pages = unescape(pagesStr).split(',');
			var noErrorPages = cleanArray(pages);

			/**
			* Inform the server on the failure so we will not try to scan this page again
			* when entering this page again
			*/
			if (noErrorPages.length > 0){
				var params2 = {
					action: 'wiziapp_batch_process_pages',
					pages: noErrorPages.join(','),
					failed_page: data.page
				};
				$.post(ajaxurl, params2, requestPageProcessing, 'json');
			} else {
				// Maybe there are more items in the queue
				startProcessing();
			}
		}
	}

	function requestPostProcessing(){
		var posts = from_php_to_js.post_ids.splice(0, batch_size);

		var params = {
			action: 'wiziapp_batch_process_posts',
			posts: posts.join(',')
		};

		$.post(ajaxurl, params, handlePostProcessing, 'json');
		progressTimer = setTimeout(updateProgressBarByTimer, 1000 * progressWait);
	}

	function handlePostProcessing(data){
		// Update the progress bar
		updateProgressBar();

		if ( typeof(data) == 'undefined' || ! data ){
			// The request failed from some reason... skip it
			startProcessing();
			return;
		}

		if (data.header.status){
			if ( from_php_to_js.post_ids.length == 0 ){
				requestFinalizingProcessing();
			} else {
				requestPostProcessing();
			}
		} else {
			var params = this.data.split('&');
			var postsStr = params[1].split('=')[1].replace(data.post, '');
			var posts = unescape(postsStr).split(',');
			var noErrorPosts = cleanArray(posts);

			/**
			* Inform the server on the failure so we will not try to scan this post again
			* when entering the page again
			*/
			if (noErrorPosts.length > 0){
				var params2 = {
					action: 'wiziapp_batch_process_posts',
					posts: noErrorPosts.join(','),
					failed_post: data.post
				};

				$.post(ajaxurl, params2, handleProcess_Post, 'json');
			} else {
				// Maybe there are more items in the queue
				startProcessing();
			}
		}
	}

	function updateProgressBarByTimer(){
		var current = $("#current_progress_indicator").text();

		if (current.length == 0){
			current = 0;
		} else if (current.indexOf('%') != -1){
			current.replace('%', '');
		}

		current = parseInt(current) + 1;

		if (current != 100){
			$("#main_progress_bar").css('width', current + '%');
			$("#current_progress_indicator").text(current + '%');

			// Repeat only once
			// progressTimer = setTimeout(updateProgressBarByTimer, 1000*progressWait);
		}
	}

	function updateProgressBar(){
		clearTimeout(progressTimer);
		progressTimer = null;

		// Added one for the profile activation
		from_php_to_js.total_items += from_php_to_js.profile_step;

		var done = ( ( from_php_to_js.post_ids.length + from_php_to_js.page_ids.length + from_php_to_js.profile_step ) / from_php_to_js.total_items ) * 100;
		var left = 100 - done;

		if ( from_php_to_js.page_ids.length > 0 ){
			$("#current_progress_label").text("Initializing...");
		} else if ( from_php_to_js.post_ids.length > 0 ){
			$("#current_progress_label").text("Generating...");
		} else {
			$("#current_progress_label").text("Finalizing...");
		}

		$("#main_progress_bar").css('width', left + '%');
		$("#current_progress_indicator").text(Math.floor(left) + '%');
	}

	function requestFinalizingProcessing(){
		var params = {
			action: 'wiziapp_batch_process_finish'
		};

		$.post(ajaxurl, params, handleFinalizingProcessing, 'json');
	}

	function handleFinalizingProcessing(data){
		if (data.header.status){
			--from_php_to_js.profile_step;
			// Update the progress bar
			updateProgressBar();
			$("#wiziapp_finalize_title").show();
			document.location.reload();
		} else {
			// There was an error??
			$("#error_activating").show();
		}
	}

})( jQuery );