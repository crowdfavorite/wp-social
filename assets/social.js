(function($){
	$(function(){
		var child = null;
		var polling = null;
		$('.social-login').click(function(e){
			e.preventDefault();

			var $this = $(this);
			child = window.open($(this).attr('href'), "ServiceAssociate", 'width=700,height=400');
			polling = setInterval(function(){
				if (child && child.closed) {
					clearInterval(polling);

					if (!$this.hasClass('comments')) {
						window.location.reload();
					} else {
						var $parent = $this.closest('.social-sign-in');
						$parent.find('form').hide();
						$parent.find('#loading').fadeIn();
						$.get($parent.find('#reload_url').val(), {}, function(response){
							if (response.result == 'success') {
								$parent.html(response.html);
								$('#primary').find('#social_login').parent().html(response.disconnect_url);
							} else {
								$parent.find('#loading').hide();
								$parent.find('form').fadeIn();
							}
						}, 'json');
					}
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
			$('.social-nav a').click(function(e){
				e.preventDefault();
				$('#cancel-comment-reply-link').trigger('click');

				$('.social-current-tab').removeClass('social-current-tab');
				$(this).parent().addClass('social-current-tab');

				var className = $(this).attr('rel');
				if (className == 'social-all') {
					$('.social-commentlist').find('.social-comment-author img, .social-comment-body, .social-actions').show();
				} else {
					$('.social-commentlist li').each(function(){
						if (!$(this).hasClass(className)) {
							$(this).find('.social-comment-author img, .social-comment-body, .social-actions').hide();
						}
						else {
							$(this).find('.social-comment-author img, .social-comment-body, .social-actions').show();
						}
					});
				}
			});

			$('.comment-reply-link').click(function(){
				$(this).hide();
			});

			$('#cancel-comment-reply-link').click(function(){
				$('.comment-reply-link').show();
			});
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

	$('.social_deauth').click(function(e){
		e.preventDefault();
		var $this = $(this);
		$.get($this.attr('href'), {}, function(){
			$this.parent().parent().fadeOut();
		});
	});

	$('#post_accounts').change(function(){
		$(this).find('option:selected').each(function(){
			var label = $(this).parent().attr('label');
			if (label !== undefined) {
				$('#post_to').show().find('span').html(label);
			} else {
				$('#post_to').hide();
			}
		});
	});
})(jQuery);
