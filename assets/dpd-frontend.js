(function($){
	console.log('DPD Frontend JS loaded');
	
	function updateHidden(){
		var d = $('#dpd_date').val();
		var t = $('#dpd_time').val();
		console.log('DPD Debug - Date:', d, 'Time:', t);
		if (d && t) {
			// Both date and time must be selected
			var combined = d + 'T' + t;
			$('#dpd_selected_datetime').val(combined);
			console.log('DPD Debug - Set hidden field to:', combined);
		} else {
			$('#dpd_selected_datetime').val('');
			console.log('DPD Debug - Cleared hidden field');
		}
	}
	function debounce(fn, wait){ var to; return function(){ var ctx=this, args=arguments; clearTimeout(to); to=setTimeout(function(){ fn.apply(ctx,args); }, wait); }; }
	var requestUpdate = debounce(function(){
		var val = $('#dpd_selected_datetime').val();
		var productId = $('#dpd_product_id').val();
		console.log('DPD Debug - AJAX request with val:', val, 'productId:', productId);
		if (!val || !productId) {
			console.log('DPD Debug - Skipping AJAX request - missing datetime or product ID');
			return;
		}
		$.post(DPD_FRONTEND.ajax_url, {
			action: 'dpd_get_price',
			nonce: DPD_FRONTEND.nonce,
			product_id: productId,
			datetime: val
		}, function(resp){
			console.log('DPD Debug - AJAX response:', resp);
			console.log('DPD Debug - Response data:', resp.data);
			if (resp && resp.success && resp.data && resp.data.price) {
				var $price = $('.summary .price, .entry-summary .price').first();
				if ($price.length) { 
					$price.html(resp.data.price);
					console.log('DPD Debug - Updated price display to:', resp.data.price);
					console.log('DPD Debug - Original price was:', resp.data.original_price || 'unknown');
					console.log('DPD Debug - Adjusted price is:', resp.data.value || 'unknown');
				}
			} else {
				if (window.console && console.warn) { console.warn('DPD price update failed', resp); }
			}
		}).fail(function(xhr, status, error) {
			console.error('DPD Debug - AJAX failed:', status, error, xhr.responseText);
		});
	}, 250);
	$(document).on('change input', '#dpd_date, #dpd_time', function(){
		updateHidden();
		requestUpdate();
	});
	
	// Ensure hidden field is updated before form submission
	$(document).on('submit', 'form.cart', function(e){
		var date = $('#dpd_date').val();
		var time = $('#dpd_time').val();
		
		// Validate that both date and time are selected
		if (!date || !time) {
			e.preventDefault();
			alert('Please select both a date and time for pricing.');
			return false;
		}
		
		updateHidden();
		console.log('DPD Debug - Form submitted, hidden field value:', $('#dpd_selected_datetime').val());
		console.log('DPD Debug - Hidden field exists:', $('#dpd_selected_datetime').length);
		console.log('DPD Debug - Hidden field name:', $('#dpd_selected_datetime').attr('name'));
		console.log('DPD Debug - Hidden field parent:', $('#dpd_selected_datetime').parent().prop('tagName'));
		console.log('DPD Debug - All form inputs:', $('form.cart input').map(function(){ return this.name + '=' + this.value; }).get());
		
		// Force add the hidden field to the form if it's not inside
		var $hiddenField = $('#dpd_selected_datetime');
		var $form = $('form.cart');
		if ($hiddenField.length && $form.length && !$form.find($hiddenField).length) {
			console.log('DPD Debug - Moving hidden field into form');
			$form.append($hiddenField);
		}
	});
	
	// Also try to catch WooCommerce AJAX add to cart
	$(document).on('click', '.single_add_to_cart_button', function(e){
		updateHidden();
		console.log('DPD Debug - Add to cart button clicked, hidden field value:', $('#dpd_selected_datetime').val());
		console.log('DPD Debug - Hidden field exists:', $('#dpd_selected_datetime').length);
	});
	
	// Monitor for WooCommerce AJAX events
	$(document.body).on('added_to_cart', function(){
		console.log('DPD Debug - WooCommerce added_to_cart event fired');
	});
})(jQuery);


