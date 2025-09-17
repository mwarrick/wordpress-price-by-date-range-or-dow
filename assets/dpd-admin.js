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
})(jQuery);
