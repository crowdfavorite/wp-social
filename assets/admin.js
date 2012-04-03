(function($) {
	$(function() {
		$('.social-collapsible').each(function() {
			var $t = $(this);
			$t.find('.social-title a').click(function(e) {
				$t.toggleClass('social-open');
				e.preventDefault();
			});
		});

		function counter($object, $counter, max) {
			var content = $object.val();
			$counter.html(max - content.length);

			var counter = parseFloat($counter.html());
			$counter.removeClass('social-counter-limit');
			if (counter <= 10) {
				$counter.addClass('social-counter-limit');
			}
		}

		/**
		 * Post Meta Box
		 */
		$('#social_meta_broadcast .social-toggle').click(function() {
			var $target = $(this).parent().find('.form-wrap');
			var $textarea = $target.find('textarea');

			if ($(this).val() == '1') {
				$target.slideDown();
			} else {
				$textarea.html('');
				$target.slideUp();
			}
		});

		$('textarea[id$="_preview"]').bind('change keyup paste', function() {
			if (typeof maxLength != 'undefined') {
				var id = $(this).attr('id').split('_');
				counter($(this), $('#' + id[0] + '_counter'), maxLength[id[0]]);
			}
		});

		/**
		 * Import from URL
		 */
		var running_import = false;
		$('#import_from_url').click(function(e) {
			e.preventDefault();

			if (!running_import) {
				running_import = true;

				var $this = $(this);
				$this.attr('disabled', 'disabled');
				$('input[name=source_url]').attr('disabled', 'disabled');
				$('#import_from_url_loader').show();
				$('#social-import-error').hide();

				$.get($this.attr('href'), {
					url: $('input[name=source_url]').val()
				}, function(response) {
					running_import = false;
					$('#import_from_url_loader').hide();
					$('input[name=source_url]').removeAttr('disabled').val('');
					$this.removeAttr('disabled');
					if (response == 'protected') {
						$('#social-import-error').html('Protected Tweet').fadeIn();
					} else if (response == 'invalid') {
						$('#social-import-error').html('Invalid URL.').fadeIn();
					} else {
						$('#aggregation_log').hide().html(response).find('.parent:not(:first)').hide().end().fadeIn();
					}
				});
			}
		});

		$('#social-source-url').keydown(function(e) {
			if (e.keyCode == 13) {
				e.preventDefault();
				$('#import_from_url').trigger('click');
			}
		});

		/**
		 * Manual Aggregation
		 */
		var running_aggregation = false;
		$('#run_aggregation').click(function(e) {
			e.preventDefault();

			if (!running_aggregation) {
				running_aggregation = true;

				var $this = $(this);
				$this.attr('disabled', 'disabled');
				$('#run_aggregation_loader').show();

				$.get($this.attr('href'), {}, function(response) {
					running_aggregation = false;
					$('#run_aggregation_loader').hide();
					$this.removeAttr('disabled');

					if (response.next_run != '0') {
						$('#social-next-run span').html(response.next_run);
					}
					$('#aggregation_log').hide().html(response.html).find('.parent:not(:first)').hide().end().fadeIn();
				}, 'json');
			}
		});
		$('#aggregation_log .parent:not(:first)').hide();
		$('#aggregation_log h5').live('click', function() {
			$('#' + $(this).attr('id') + '-output').toggle();
		});

		var running_row_aggregation = [];
		$('.row-actions .social_aggregation a').click(function(e) {
			e.preventDefault();
			var rel = $(this).attr('rel');
			if (!in_running_row_aggregation(rel)) {
				var $this = $(this);
				var $loader = $this.parent().find('.social_run_aggregation_loader');
				$this.hide().closest('.row-actions').addClass('social_run_aggregation');
				$loader.show();
				$.get(
					$this.attr('href'),
					{
						render: 'false',
						hide_li: 'true'
					},
					function(response) {
						remove_running_row_aggregation(rel);
						$loader.hide();
						$this.parent().find('.social-aggregation-results').remove();
						$this.parent().append(' ' + response.html).find('a').fadeIn();
					},
					'json'
				);
			}
		});

		var in_running_row_aggregation = function(rel) {
			for (var i = 0; i < running_row_aggregation.length; ++i) {
				if (running_row_aggregation[i] == rel) {
					return true;
				}
			}
			return false;
		};
		var remove_running_row_aggregation = function(rel) {
			var _running_row_aggregation = [];
			for (var i = 0; i < running_row_aggregation.length; ++i) {
				if (running_row_aggregation[i] != rel) {
					_running_row_aggregation.push(running_row_aggregation[i]);
				}
			}
			running_row_aggregation = _running_row_aggregation;
		};

		/**
		 * Regenerate API Key
		 */
		$('#social_regenerate_api_key').click(function(e) {
			e.preventDefault();
			$.get($(this).attr('href'), {}, function(response) {
				$('.social_api_key').html(response);
			});
		});

		/**
		 * Dismissal of notices.
		 */
		$('.social_dismiss').click(function(e) {
			e.preventDefault();
			var $this = $(this);
			$.get($this.attr('href'), {}, function() {
				$this.parent().parent().fadeOut();
			});
		});

		/**
		 * Facebook Pages support
		 */
		var originalHref = '';
		$('#social-facebook-pages').click(function(){
			var href = $('#facebook_signin').attr('href');

			if (originalHref == '') {
				originalHref = href;
			}

			if ($(this).is(':checked')) {
				href += '&use_pages=true';
			}
			else {
				href = originalHref;
			}

			$('#facebook_signin').attr('href', href);
		});

		$('.social-manage-facebook-pages').click(function(e){
			e.preventDefault();

			var $this = $(this);
			var $parent = $this.closest('.social-accounts-item');
			var $spinner = $parent.find('.social-facebook-pages-spinner');
			$this.hide();
			$spinner.fadeIn();

			$.get($this.attr('href'), {}, function(data){
				$spinner.hide();
				if (data.result == 'success') {
					var slide = false;
					var $output = $parent.find('.social-facebook-pages');
					if ($output.is(':visible')) {
						$output.hide();
					}
					else {
						slide = true;
					}

					$output.html(data.html);
					if (slide) {
						$output.slideDown();
					}
					else {
						$output.fadeIn();
					}

					$this.parent().hide();
				}
				else {
					$spinner.hide();
					$this.parent().html(' - '+data.html).fadeIn();
				}
			}, 'json');
		});

		$('.social-accounts .social-facebook-pages input[type=checkbox]').live('change', function(){
			var $parent = $(this).closest('.social-accounts-item');
			var data = { 'page_ids[]' : [] };
			$parent.find('input[type=checkbox]:checked').each(function(){
				data['page_ids[]'].push($(this).val());
			});
			$.post($parent.find('input[name=social_save_url]').val(), data);
		});

		$('.social-show-facebook-pages').click(function(e){
			e.preventDefault();
			$(this).parent().hide();
			$(this).closest('.social-accounts-item').find('.social-facebook-pages').slideDown();
		});
	});
})(jQuery);
