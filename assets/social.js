var $window = null;
var $auth_window = null;
function reloadSocialHTML(saved) {
	$auth_window.close();
	if (!$window.hasClass('comments')) {
		window.location.reload();
	}
	else {
		if (saved == 'false') {
			alert('Failed to authorize this account. Please try again.');
		}
		else {
			var $parent = jQuery('.social-post');
			jQuery.get($parent.find('#reload_url').val(), {}, function(response) {
				if (response.result == 'success') {
					// Add logged-in body class since we're not going to be refreshing the page.
					jQuery('body').addClass('logged-in');

					var $cancel = jQuery('#cancel-comment-reply-link');
					var $parent = $cancel.closest('li');
					$cancel.click();
					jQuery('#respond').replaceWith(response.html);
					$parent.find('.comment-reply-link').click();

					jQuery('#primary').find('#social_login').parent().html(response.disconnect_url);
				}
			}, 'json');

			// Fix for the missing reply link
			jQuery('#cancel-comment-reply-link').live('click', function() {
				jQuery('.comment-reply-link').show();
			});
		}
	}
}

(function($) {
	/*
	 * Append social-js class to html element. This allows us to prevent JS FOUC
	 * in an accessible way.
	 * Run it ASAP. Doesn't need to run on domReady because the html element is
	 * the first thing available.
	 */
	var c = document.getElementsByTagName('html')[0];
	c.className += ' social-js';

	$(function() {
		$('.social-login').click(function(e) {
			e.preventDefault();

			$window = $(this);
			$auth_window = window.open($(this).attr('href'), "ServiceAssociate", 'width=700,height=400');
		});

		// comments.php
		if ($('#social').length) {
			// MCC Tabs
			var $prevLink = null;
			var prevLink = null;
			var $nextLink = null;
			var nextLink = null;
			if ($('#comments .nav-previous a').length) {
				$prevLink = $('#comments .nav-previous a');
				prevLink = $prevLink.attr('href');
			}

			if ($('#comments .nav-next a').length) {
				$nextLink = $('#comments .nav-next a');
				nextLink = $nextLink.attr('href');
			}

			$('.social-nav a').click(function(e) {
				e.preventDefault();
				$('#cancel-comment-reply-link').trigger('click');

				$('.social-current-tab').removeClass('social-current-tab');
				$(this).parent().addClass('social-current-tab');

				var className = $(this).attr('rel');
				if (className == 'social-all') {
					if (nextLink !== null) {
						$nextLink.attr('href', nextLink);
					}

					if (prevLink !== null) {
						$prevLink.attr('href', prevLink);
					}
					
					$('.social-commentlist li').removeClass('social-comment-collapse');
				} else {
					$('.social-commentlist li').each(function() {
						if (!$(this).hasClass(className)) {
							$(this).addClass('social-comment-collapse');
						}
						else {
							$(this).removeClass('social-comment-collapse');
						}
					});

					if (prevLink !== null) {
						var _prevLink = prevLink.split('#comments');
						$prevLink.attr('href', _prevLink[0]+'&social_tab='+className+'#comments');
					}

					if (nextLink !== null) {
						var _nextLink = nextLink.split('#comments');
						$nextLink.attr('href', _nextLink[0]+'&social_tab='+className+'#comments');
					}
				}
			});

			$('.social-current-tab a').trigger('click');

			/**
			 * Inserts the Twitter username for the reply to content.
			 *
			 * @param $textarea
			 * @param username
			 * @param extraContent
			 */
			function insertTwitterUsername($author, $textarea, extraContent) {
				var username = $author.html() + ' ';
				if (username.substr(0, 1) != '@') {
					username = '@' + username;
				}
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
			$('.comment-reply-link').click(function() {
				$('.comment-reply-link').show();
				$(this).hide();
				var $parent = $(this).closest('li');
				var $textarea = $parent.find('textarea');
				if ($parent.hasClass('social-twitter') && $use_twitter_reply.val() == '1') {
					var $author = $parent.find('.social-comment-author a');
					insertTwitterUsername($respond.closest('li').find('.social-comment-author a'), $textarea);
				}
			});
			$('#cancel-comment-reply-link').click(function() {
				$('.comment-reply-link').show();
			});

			var $avatar = $('#commentform .avatar');
			var original_avatar = $avatar.attr('src');
			$('#post_accounts').live('change', function() {
				$(this).find('option:selected').each(function() {
					var avatar = $(this).attr('rel');
					if (avatar !== undefined) {
						$avatar.attr('src', avatar);
					} else {
						$avatar.attr('src', original_avatar);
					}
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
			$('#post_accounts').trigger('change');
		}
	});
})(jQuery);
