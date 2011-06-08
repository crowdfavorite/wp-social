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
						var $parent = $this.closest('.social-post');
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
					$('.social-commentlist li').removeClass('social-comment-collapse');
				} else {
					$('.social-commentlist li').each(function(){
						if (!$(this).hasClass(className)) {
							$(this).addClass('social-comment-collapse');
						}
						else {
							$(this).removeClass('social-comment-collapse');
						}
					});
				}
			});

			/**
			 * Inserts the Twitter username for the reply to content.
			 *
			 * @param $textarea
			 * @param username
			 * @param extraContent
			 */
			function insertTwitterUsername($author, $textarea, extraContent) {
				var username = '@'+$author.html()+' ';
				if (extraContent !== undefined) {
					username += extraContent;
				}

				var pos = username.length;

				$textarea.val(username);
				if ($textarea.get(0).setSelectionRange) {
					$textarea.focus();
					$textarea.get(0).setSelectionRange(pos, pos);
				} else if ($textarea.createTextRange) {
					var range = $textarea.get(0).createTextRange();
					range.collapse(true);
					range.moveEnd('character', pos);
                    range.moveStart('character', pos);
					range.select();
				}

				var author_rel = $author.attr('rel').split(' ');
				$('#in_reply_to_status_id').val(author_rel[0]);
			}

			var $use_twitter_reply = $('#use_twitter_reply');
			$('.comment-reply-link').click(function(){
				$('.comment-reply-link').show();
				$(this).hide();
				var $parent = $(this).closest('li');
				var $textarea = $parent.find('textarea');
				if ($parent.hasClass('social-twitter') && $use_twitter_reply.val() == '1') {
					var $author = $parent.find('.social-comment-author a');
					insertTwitterUsername($respond.closest('li').find('.social-comment-author a'), $textarea);
				}
			});
			$('#cancel-comment-reply-link').click(function(){
				$('.comment-reply-link').show();
			});

			$('#post_accounts').live('change', function(){
				$(this).find('option:selected').each(function(){
					var label = $(this).parent().attr('label');
					if (label !== undefined) {
						$('#post_to').show().find('span').html(label);

						if (label === 'Twitter') {
							var $respond = $('#respond');
							var $textarea = $respond.find('textarea');
							if ($respond.parent().hasClass('social-twitter')) {
								var content = $textarea.val();
								if (!content.length || content.substring(0, 1) != '@') {
									insertTwitterUsername($respond.closest('li').find('.social-comment-author a'), $textarea, content);
								}
							}
							$use_twitter_reply.val('1');
						} else {
							$use_twitter_reply.val('0');
						}
					} else {
						$('#post_to').hide();
					}
				});
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

})(jQuery);
