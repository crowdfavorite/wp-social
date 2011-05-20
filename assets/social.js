(function($){
	$(function(){
		var child = null;
		var polling = null;
		$('.social-login').click(function(e){
			e.preventDefault();

			child = window.open($(this).attr('href'), "ServiceAssociate", 'width=700,height=400');
			polling = setInterval(function(){
				if (child && child.closed) {
					clearInterval(polling);
					window.location.reload();
				}
			}, 100);
		});

		// comments.php
		if ($('#social').length) {
			/**
			 * Append social-js class to html element. This allows us to prevent JS FOUC in an accessible way.
			 * In a self-executing function, so it runs immediately. Doesn't need to run on domReady
			 * because the html element is the first thing available.
			 */
			var c = document.getElementsByTagName('html')[0];
			c.className += ' social-js';

			// MCC Tabs
			$('#social-tabs-comments').tabs();
		}
		// wp-admin
		else {
			function counter($object, $counter, max) {
				var content = $object.val();
				if (content.length > max) {
					$object.val(content.substr(0, max));
				} else if (counter == 0) {
					$object.val(content.substr(0, -1));
				} else {
					$counter.html(max - content.length);
				}
			}

			/**
			 * Post Meta Box
			 */
			$('#social_meta_broadcast .social-toggle').click(function(){
				var $target = $(this).parent().find('.form-wrap');
				var $textarea = $target.find('textarea');

				if ($(this).val() == '1') {
					var content = tinyMCE.get('content').getContent();
					$textarea.html(content.replace(/<[^>]*>?/g, ''));
					$target.slideDown();
				} else {
					$textarea.html('');
					$target.slideUp();
				}
			});

			$('#tweet_preview').bind("change keyup paste", function(){
				counter($(this), $('#tweet_counter'), 140);
			});

			$('#facebook_preview').bind("change keyup paste", function(){
				counter($(this), $('#facebook_counter'), 420);
			});
		}
	});
})(jQuery);
