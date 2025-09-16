(function($){
	function updateHidden(){
		var d = $('#dpd_date').val();
		var t = $('#dpd_time').val();
		if (d && t) {
			$('#dpd_selected_datetime').val(d + 'T' + t);
		}
	}
	function debounce(fn, wait){ var to; return function(){ var ctx=this, args=arguments; clearTimeout(to); to=setTimeout(function(){ fn.apply(ctx,args); }, wait); }; }
	var requestUpdate = debounce(function(){
		var val = $('#dpd_selected_datetime').val();
		var productId = $('#dpd_product_id').val();
		if (!val || !productId) return;
		$.post(DPD_FRONTEND.ajax_url, {
			action: 'dpd_get_price',
			nonce: DPD_FRONTEND.nonce,
			product_id: productId,
			datetime: val
		}, function(resp){
			if (resp && resp.success && resp.data && resp.data.price) {
				var $price = $('.summary .price, .entry-summary .price').first();
				if ($price.length) { $price.html(resp.data.price); }
			}
		});
	}, 250);
	$(document).on('change input', '#dpd_date, #dpd_time', function(){
		updateHidden();
		requestUpdate();
	});
})(jQuery);


