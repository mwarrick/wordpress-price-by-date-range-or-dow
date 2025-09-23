(function($){
	function nextIndex($tbody){
		var max = -1;
		$tbody.find('tr.dpd-rule-row').each(function(){
			var $row = $(this);
			var name = $row.find('input,select').first().attr('name') || '';
			var m = name.match(/\[(\d+)\]/);
			if (m) {
				var idx = parseInt(m[1], 10);
				if (idx > max) max = idx;
			}
		});
		return max + 1;
	}

	function buildGlobalRow(idx){
		return '' +
		'<tr class="dpd-rule-row">' +
		'  <td><input type="checkbox" name="dpd_rules['+idx+'][enabled]" value="1" /></td>' +
		'  <td><select name="dpd_rules['+idx+'][dow]">' +
		'    <option value="">Any</option>' +
		'    <option value="0">Sunday</option>' +
		'    <option value="1">Monday</option>' +
		'    <option value="2">Tuesday</option>' +
		'    <option value="3">Wednesday</option>' +
		'    <option value="4">Thursday</option>' +
		'    <option value="5">Friday</option>' +
		'    <option value="6">Saturday</option>' +
		'  </select></td>' +
		'  <td><input type="date" name="dpd_rules['+idx+'][date_start]" /></td>' +
		'  <td><input type="date" name="dpd_rules['+idx+'][date_end]" /></td>' +
		'  <td><select name="dpd_rules['+idx+'][type]">' +
		'    <option value="percent">Percent</option>' +
		'    <option value="fixed">Fixed</option>' +
		'  </select></td>' +
		'  <td><select name="dpd_rules['+idx+'][direction]">' +
		'    <option value="increase">Increase</option>' +
		'    <option value="decrease">Decrease</option>' +
		'  </select></td>' +
		'  <td><input type="number" step="0.01" min="0" name="dpd_rules['+idx+'][amount]" /></td>' +
		'  <td><button type="button" class="button dpd-remove-row">Remove</button></td>' +
		'</tr>';
	}

	function buildProductRows(idx){
		return '' +
		'<tr class="dpd-rule-row">' +
		'  <td><input type="checkbox" name="dpd_product_rules['+idx+'][enabled]" value="1" /></td>' +
		'  <td><select name="dpd_product_rules['+idx+'][dow]">' +
		'    <option value="">Any</option>' +
		'    <option value="0">Sun</option>' +
		'    <option value="1">Mon</option>' +
		'    <option value="2">Tue</option>' +
		'    <option value="3">Wed</option>' +
		'    <option value="4">Thu</option>' +
		'    <option value="5">Fri</option>' +
		'    <option value="6">Sat</option>' +
		'  </select></td>' +
		'  <td><input type="date" name="dpd_product_rules['+idx+'][date_start]" /></td>' +
		'  <td><input type="date" name="dpd_product_rules['+idx+'][date_end]" /></td>' +
		'</tr>' +
		'<tr>' +
		'  <td colspan="4">' +
		'    <select name="dpd_product_rules['+idx+'][type]">' +
		'      <option value="percent">Percent</option>' +
		'      <option value="fixed">Fixed</option>' +
		'    </select> ' +
		'    <select name="dpd_product_rules['+idx+'][direction]">' +
		'      <option value="increase">Increase</option>' +
		'      <option value="decrease">Decrease</option>' +
		'    </select> ' +
		'    <input type="number" step="0.01" min="0" name="dpd_product_rules['+idx+'][amount]" /> ' +
		'    <button type="button" class="button dpd-remove-row">Remove</button>' +
		'  </td>' +
		'</tr>';
	}

	$(document).on('click', '#dpd-add-row', function(){
		var $tbody = $('#dpd-rules-body');
		var idx = nextIndex($tbody);
		$tbody.append(buildGlobalRow(idx));
	});

	$(document).on('click', '#dpd-add-product-row', function(){
		var $tbody = $('#dpd-product-rules-body');
		var idx = nextIndex($tbody);
		$tbody.append(buildProductRows(idx));
	});

	$(document).on('click', '.dpd-remove-row', function(){
		var $btn = $(this);
		var $row = $btn.closest('tr');
		var $next = $row.next();
		if ($next.length && !$next.hasClass('dpd-rule-row')) { $next.remove(); }
		$row.remove();
	});

	// Delete all product rules: clears tbody and triggers save
	$(document).on('click', '#dpd-delete-all-product-rules', function(){
		var $tbody = $('#dpd-product-rules-body');
		if (!$tbody.length) { return; }
		if (!confirm('Delete all pricing rules for this product?')) { return; }
		var productId = parseInt($('#dpd_admin_product_id').val(), 10) || 0;
		var nonce = $('#dpd_product_nonce').val();
		if (!productId || !nonce) { return; }
		// Optimistically clear UI
		$tbody.empty();
		$.post(ajaxurl, {
			action: 'dpd_delete_all_product_rules',
			nonce: nonce,
			product_id: productId
		}, function(resp){
			if (!resp || !resp.success) {
				alert('Failed to delete rules. Please save the product to retry.');
			}
		});
	});

	// Prune blank rows on submit for both global and product forms
	$(document).on('submit', 'form', function(){
		var $form = $(this);
		// Only act on our DPD forms
		var isDPD = $form.find('#dpd-rules-body, #dpd-product-rules-body').length > 0;
		if (!isDPD) { return; }
		// Remove rows where all key inputs are empty/unchecked
		$form.find('tbody#dpd-rules-body tr.dpd-rule-row, tbody#dpd-product-rules-body tr.dpd-rule-row').each(function(){
			var $r = $(this);
			var enabled = $r.find('input[type="checkbox"]').is(':checked');
			var hasDow = ($r.find('select[name*="[dow]"]').val() || '') !== '';
			var ds = ($r.find('input[name*="[date_start]"]').val() || '').trim();
			var de = ($r.find('input[name*="[date_end]"]').val() || '').trim();
			// Check the partner row for type/direction/amount if present
			var $partner = $r.next();
			var type = $partner.find('select[name*="[type]"]').val() || '';
			var dir = $partner.find('select[name*="[direction]"]').val() || '';
			var amt = ($partner.find('input[name*="[amount]"]').val() || '').trim();
			var hasAny = enabled || hasDow || ds || de || type || dir || amt;
			console.log('DPD Prune: Row check - enabled:', enabled, 'hasDow:', hasDow, 'ds:', ds, 'de:', de, 'type:', type, 'dir:', dir, 'amt:', amt, 'hasAny:', hasAny);
			if (!hasAny) {
				// Remove both the row and its partner (if any)
				if ($partner.length && !$partner.hasClass('dpd-rule-row')) { $partner.remove(); }
				$r.remove();
			}
		});
	});

	// Date validation - prevent past dates
	$(document).on('change', 'input[type="date"]', function(){
		var $input = $(this);
		var selectedDate = new Date($input.val());
		var today = new Date();
		today.setHours(0, 0, 0, 0); // Reset time to start of day
		
		if (selectedDate < today) {
			alert('Please select a date in the future. Past dates are not allowed.');
			$input.val('');
		}
	});

	// Set minimum date to today for all date inputs
	$(document).ready(function(){
		var today = new Date().toISOString().split('T')[0];
		$('input[type="date"]').attr('min', today);
	});

	// Blackout dates functionality
	function buildBlackoutDateRangeRow(idx){
		return '' +
		'<tr class="dpd-blackout-row dpd-blackout-date-range-row">' +
		'  <td><input type="checkbox" name="dpd_blackouts['+idx+'][enabled]" value="1" />' +
		'      <input type="hidden" name="dpd_blackouts['+idx+'][type]" value="date_range" /></td>' +
		'  <td class="dpd-date-start-field"><input type="date" name="dpd_blackouts['+idx+'][date_start]" /></td>' +
		'  <td class="dpd-date-end-field"><input type="date" name="dpd_blackouts['+idx+'][date_end]" /></td>' +
		'  <td><button type="button" class="button dpd-remove-blackout-row">Remove</button></td>' +
		'</tr>';
	}

	function buildBlackoutDowRow(idx){
		return '' +
		'<tr class="dpd-blackout-row dpd-blackout-dow-row">' +
		'  <td><input type="checkbox" name="dpd_blackouts['+idx+'][enabled]" value="1" />' +
		'      <input type="hidden" name="dpd_blackouts['+idx+'][type]" value="day_of_week" /></td>' +
		'  <td class="dpd-dow-field"><select name="dpd_blackouts['+idx+'][dow]">' +
		'    <option value="">Any</option>' +
		'    <option value="0">Sunday</option>' +
		'    <option value="1">Monday</option>' +
		'    <option value="2">Tuesday</option>' +
		'    <option value="3">Wednesday</option>' +
		'    <option value="4">Thursday</option>' +
		'    <option value="5">Friday</option>' +
		'    <option value="6">Saturday</option>' +
		'  </select></td>' +
		'  <td><button type="button" class="button dpd-remove-blackout-row">Remove</button></td>' +
		'</tr>';
	}

	$(document).on('click', '#dpd-add-blackout-date-range-row', function(){
		var $tbody = $('#dpd-blackouts-body-date-range');
		var idx = $('tbody#dpd-blackouts-body-date-range tr.dpd-blackout-row, tbody#dpd-blackouts-body-dow tr.dpd-blackout-row').length;
		$tbody.append(buildBlackoutDateRangeRow(idx));
		var today = new Date().toISOString().split('T')[0];
		$tbody.find('input[type="date"]').attr('min', today);
	});

	$(document).on('click', '#dpd-add-blackout-dow-row', function(){
		var $tbody = $('#dpd-blackouts-body-dow');
		var idx = $('tbody#dpd-blackouts-body-date-range tr.dpd-blackout-row, tbody#dpd-blackouts-body-dow tr.dpd-blackout-row').length;
		$tbody.append(buildBlackoutDowRow(idx));
	});

	$(document).on('click', '.dpd-remove-blackout-row', function(){
		var $btn = $(this);
		var $row = $btn.closest('tr');
		$row.remove();
		// Update headers after removing row
		updateBlackoutHeaders();
	});

	// No need for header toggling or per-row type switching now that UI is split

	// Prune blank blackout rows on submit
	$(document).on('submit', 'form', function(){
		var $form = $(this);
		// Only act on blackout forms
		var isBlackoutForm = $form.find('#dpd-blackouts-body').length > 0;
		if (!isBlackoutForm) { return; }
		
		var $rows = $form.find('tbody#dpd-blackouts-body tr.dpd-blackout-row');
		
		// Remove rows where all key inputs are empty/unchecked
		$rows.each(function(index){
			var $r = $(this);
			var enabled = $r.find('input[type="checkbox"]').is(':checked');
			var type = $r.find('select[name*="[type]"]').val() || '';
			var hasDow = ($r.find('select[name*="[dow]"]').val() || '') !== '';
			var ds = ($r.find('input[name*="[date_start]"]').val() || '').trim();
			var de = ($r.find('input[name*="[date_end]"]').val() || '').trim();
			
			// Only remove if the rule is not enabled AND has no data
			var hasData = false;
			if (type === 'day_of_week') {
				hasData = hasDow;
			} else {
				hasData = ds || de;
			}
			
			// Keep the row if it's enabled OR has data
			if (!enabled && !hasData) {
				$r.remove();
			}
		});
	});
})(jQuery);
