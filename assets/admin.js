(function($) {
	$(function(){
		$('.social-collapsible').each(function () {
			var $t = $(this);
			$t.find('.social-title a').click(function (e) {
				$t.toggleClass('social-open');
			});
		});
		
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

		/**
		 * Import from URL
		 */
		var running_import = false;
		$('#import_from_url').click(function(e){
			e.preventDefault();

			if (!running_import) {
				running_import = true;

				var $this = $(this);
				$this.attr('disabled', 'disabled');
				$('input[name=source_url]').attr('disabled', 'disabled');
				$('#import_from_url_loader').show();

				$.get($this.attr('href'), {
					url: $('input[name=source_url]').val()
				}, function(response){
					running_import = false;
					$('#import_from_url_loader').hide();
					$this.removeAttr('disabled');
					$('input[name=source_url]').removeAttr('disabled').val('');

					$('#aggregation_log').hide().html(response).find('.parent:not(:first)').hide().end().fadeIn();
				});
			}
		});

		/**
		 * Manual Aggregation
		 */
		var running_aggregation = false;
		$('#run_aggregation').click(function(e){
			e.preventDefault();

			if (!running_aggregation) {
				running_aggregation = true;

				var $this = $(this);
				$this.attr('disabled', 'disabled');
				$('#run_aggregation_loader').show();

				$.get($this.attr('href'), {}, function(response){
					running_aggregation = false;
					$('#run_aggregation_loader').hide();
					$this.removeAttr('disabled');

					$('#aggregation_log').hide().html(response).find('.parent:not(:first)').hide().end().fadeIn();
				});
			}
		});
		$('#aggregation_log .parent:not(:first)').hide();
		$('#aggregation_log h5').live('click', function(){
			$('#'+$(this).attr('id')+'-output').toggle();
		});

		/**
		 * Regenerate API Key
		 */
		 $('#social_regenerate_api_key').click(function(e){
			e.preventDefault();
			$.get($(this).attr('href'), {}, function(response){
				$('.social_api_key').html(response);
			});
		});
	});
})(jQuery);