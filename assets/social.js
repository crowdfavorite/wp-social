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
		var $window = null;
		var $auth_window = null;
		var auth_poll = null;
		$('.social-login').click(function(e) {
			e.preventDefault();

			$window = $(this);
			$auth_window = window.open($(this).attr('href'), "ServiceAssociate", 'width=700,height=400');

			auth_poll = setInterval(function() {
				if ($auth_window.closed) {
					clearInterval(auth_poll);

					if (!$window.hasClass('comments')) {
						window.location.reload();
					}
					else {
						var $parent = $('.social-post');
						$.get($parent.find('#reload_url').val(), {}, function(response) {
							if (response.result == 'success') {
								// Add logged-in body class since we're not going to be refreshing the page.
								$('body').addClass('logged-in');

								var $cancel = $('#cancel-comment-reply-link');
								var $parent = $cancel.closest('li');
								$cancel.click();
								$('#respond').replaceWith(response.html);
								$parent.find('.comment-reply-link').click();

								$('#primary').find('#social_login').parent().html(response.disconnect_url);
							}
						}, 'json');

						// Fix for the missing reply link
						$('#cancel-comment-reply-link').live('click', function() {
							jQuery('.comment-reply-link').show();
						});
					}
				}
			}, 100);
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
                    $('.social-items').show();

					if (nextLink !== null) {
						$nextLink.attr('href', nextLink);
					}

					if (prevLink !== null) {
						$prevLink.attr('href', prevLink);
					}

					$('.social-commentlist li').removeClass('social-comment-collapse');
				} else {
                    $('.social-items').hide();
                    if ($('.social-items.'+className).length) {
                        $('.social-items.'+className).show();
                    }

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
						$prevLink.attr('href', _prevLink[0] + '&social_tab=' + className + '#comments');
					}

					if (nextLink !== null) {
						var _nextLink = nextLink.split('#comments');
						$nextLink.attr('href', _nextLink[0] + '&social_tab=' + className + '#comments');
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

        /**
         * Manual Aggregation
         */
        var $social_comments_adminbar_item = $('#wp-admin-bar-social_find_comments');
        if ($social_comments_adminbar_item.size()) {
            var $social_spinner = $social_comments_adminbar_item.find('img.social-aggregation-spinner');
            var $social_aggregation = $('#social_aggregation');
            var $comment_adminbar_item = $('#wp-admin-bar-comments');
            var $comment_adminbar_item_label = $comment_adminbar_item.find('> a:first > span');
            $social_aggregation.click(function(e) {
                if ($(this).attr('href') == '#') {
                    e.preventDefault();
                }
            }).removeClass('running-aggregation');
            $comment_adminbar_item.removeClass('running-aggregation');

            $social_comments_adminbar_item.find('a').click(function(e) {
                e.preventDefault();
                if (!$comment_adminbar_item.hasClass('running-aggregation')) {
                    $comment_adminbar_item.addClass('running-aggregation');

                    // remove old results (slide left)
                    $('#wp-adminbar-comments-social').animate({ width: '0' }, function() {
                        $(this).remove();
                    });

                    // show spinner
                    $comment_adminbar_item_label.find('#ab-awaiting-mod').hide().end()
                        .append($social_spinner);
                    $social_spinner.show();

                    // make AJAX call
                    $.get(
                        $(this).attr('href'),
                        { render: 'false' },
                        function(response) {
                            // hide spinner
                            $social_spinner.hide();
                            $social_comments_adminbar_item.append($social_spinner);

                            // update count, show count
                            $comment_adminbar_item_label.find('#ab-awaiting-mod')
                                .html(response.total).show();

                            // show results (slide right)
                            $comment_adminbar_item.addClass('social-comments-found').after(response.html);
                            var $social_comments_found = $('#wp-adminbar-comments-social');
                            var found_width = $social_comments_found.width();
                            $social_comments_found.css({
                                position: 'relative',
                                visibility: 'visible',
                                width: 0
                            }).animate({ width: found_width + 'px' });

                            // set params for next call
                            $social_aggregation
                                .attr('href', response.link);

                            $comment_adminbar_item.removeClass('running-aggregation');
                        },
                        'json'
                    );
                }
            });
        }

        /**
         * Twitter @Anywhere
         */
        if (typeof twttr != 'undefined') {
            twttr.anywhere(function(T){
                T.hovercards();
            });
        }

        /**
         * Social items
         */
        if ($('.social-items-and-more').length) {
            $('.social-items-and-more').click(function(e){
                e.preventDefault();

                $(this).hide().parent().find('img').show();
            });
        }
	});
})(jQuery);
