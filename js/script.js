
jQuery.noConflict(); 
jQuery(document).ready(function ($) {
	if ($("#pp_thesaurus_input_term").length && typeof(pp_thesaurus_suggest_url) == "string") {
		$("#pp_thesaurus_input_term").clearField().autocomplete(pp_thesaurus_suggest_url, {
			minChars:		2,
			matchContains:	true,
			cacheLength:	10,
			max:			15,
			scroll:			false
		}).result(function (event, item) {
			location.href = item[1];
		});
	}
});


;(function($) {
$.fn.extend({
	clearField: function() {
		var selVal = null;
		if (this.val() == '') {
			this.val(this.attr('title'));
		}
		return this.focus(function() {
			if ($(this).val() == $(this).attr('title')) {
				$(this).val('');
			}
		}).blur(function() {
			if ($(this).val() == '') {
				if (selVal == null) {
					$(this).val($(this).attr('title'));
				} else {
					$(this).val(selVal);
					selVal = null;
				}
			}
		});
	}
});
})(jQuery);

