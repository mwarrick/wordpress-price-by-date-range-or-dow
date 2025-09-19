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
	
	// Check if a date is blacked out
	function checkBlackoutDate(date) {
		if (!date) return;
		
		$.post(DPD_FRONTEND.ajax_url, {
			action: 'dpd_check_blackout',
			nonce: DPD_FRONTEND.nonce,
			date: date
		}, function(resp) {
			console.log('DPD Blackout Check - Response:', resp);
			if (resp && resp.success && resp.data) {
				updateAddToCartButton(resp.data.is_blacked_out, resp.data.message);
			}
		}).fail(function(xhr, status, error) {
			console.error('DPD Blackout Check - AJAX failed:', status, error, xhr.responseText);
		});
	}
	
	// Update the add to cart button based on blackout status
	function updateAddToCartButton(isBlackedOut, message) {
		var $addToCartBtn = $('.single_add_to_cart_button');
		var $form = $('form.cart');
		
		if (isBlackedOut) {
			// Hide the add to cart button and show unavailable message
			$addToCartBtn.hide();
			var $unavailableMsg = $('.dpd-unavailable-message');
			if ($unavailableMsg.length === 0) {
				$unavailableMsg = $('<div class="dpd-unavailable-message" style="padding: 10px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0; text-align: center; font-weight: bold;">' + message + '</div>');
				$form.append($unavailableMsg);
			} else {
				$unavailableMsg.text(message).show();
			}
		} else {
			// Show the add to cart button and hide unavailable message
			$addToCartBtn.show();
			$('.dpd-unavailable-message').hide();
		}
	}
	
	var requestUpdate = debounce(function(){
		var val = $('#dpd_selected_datetime').val();
		var productId = $('#dpd_product_id').val();
		var variationId = $('input[name="variation_id"]').val();
		console.log('DPD Debug - AJAX request with val:', val, 'productId:', productId, 'variationId:', variationId);
		if (!val || !productId) {
			console.log('DPD Debug - Skipping AJAX request - missing datetime or product ID');
			return;
		}
		var ajaxData = {
			action: 'dpd_get_price',
			nonce: DPD_FRONTEND.nonce,
			product_id: productId,
			datetime: val
		};
		if (variationId) {
			ajaxData.variation_id = variationId;
		}
		$.post(DPD_FRONTEND.ajax_url, ajaxData, function(resp){
			console.log('DPD Debug - AJAX response:', resp);
			console.log('DPD Debug - Response data:', resp.data);
			if (resp.data && resp.data.debug) {
				console.log('DPD Debug - Debug info:', resp.data.debug);
			}
			if (resp && resp.success && resp.data && resp.data.price) {
				// Update price display for both simple and variable products
				var isVariableProduct = $('.variations_form').length > 0;
				if (isVariableProduct) {
					// For variable products, update the variation price if one is selected
					var $variationPrice = $('.woocommerce-variation-price .price, .woocommerce-variation .price');
					if ($variationPrice.length) {
						$variationPrice.html(resp.data.price);
						console.log('DPD Debug - Updated variation price to:', resp.data.price);
					} else {
						// No variation selected yet, update the main price range
						var $price = $('.summary .price, .entry-summary .price').first();
						if ($price.length) { 
							$price.html(resp.data.price);
							console.log('DPD Debug - Updated main price to:', resp.data.price);
						}
					}
				} else {
					// Simple product - update the price directly
					var $price = $('.summary .price, .entry-summary .price').first();
					if ($price.length) { 
						$price.html(resp.data.price);
						console.log('DPD Debug - Updated price display to:', resp.data.price);
					}
				}
				console.log('DPD Debug - Original price was:', resp.data.original_price || 'unknown');
				console.log('DPD Debug - Adjusted price is:', resp.data.value || 'unknown');
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
		
		// Check for blackout dates when date changes
		var date = $('#dpd_date').val();
		if (date) {
			checkBlackoutDate(date);
		} else {
			// If no date selected, show the add to cart button
			updateAddToCartButton(false, '');
		}
	});
	
	// Handle variation selection for variable products
	$(document).on('found_variation', '.variations_form', function(event, variation) {
		console.log('DPD Debug - Variation selected:', variation);
		// When a variation is selected, update the price if date/time is selected
		var val = $('#dpd_selected_datetime').val();
		if (val) {
			// Trigger a price update for the selected variation
			requestUpdate();
		}
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
		
		// Check if the selected date is blacked out
		if ($('.dpd-unavailable-message').is(':visible')) {
			e.preventDefault();
			alert('The selected date is not available for booking.');
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


