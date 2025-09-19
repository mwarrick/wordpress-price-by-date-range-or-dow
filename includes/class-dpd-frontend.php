<?php
if (!defined('ABSPATH')) {
	exit;
}

class DPD_Frontend {
	const FIELD_KEY = 'dpd_selected_datetime';
	const NONCE_KEY = 'dpd_ajax_nonce';

	public static function init(): void {
		add_action('woocommerce_before_add_to_cart_button', [__CLASS__, 'render_datetime_field']);
		add_action('woocommerce_single_product_summary', [__CLASS__, 'render_datetime_field'], 25);
		add_filter('woocommerce_add_to_cart_validation', [__CLASS__, 'validate_datetime_field'], 10, 3);
		add_filter('woocommerce_add_cart_item_data', [__CLASS__, 'add_cart_item_data'], 10, 3);
		add_filter('woocommerce_get_item_data', [__CLASS__, 'display_cart_item_data'], 10, 2);
		add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'add_order_item_meta'], 10, 4);
		add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
		add_action('wp_ajax_dpd_get_price', [__CLASS__, 'ajax_get_price']);
		add_action('wp_ajax_nopriv_dpd_get_price', [__CLASS__, 'ajax_get_price']);
		add_action('wp_ajax_dpd_check_blackout', [__CLASS__, 'ajax_check_blackout']);
		add_action('wp_ajax_nopriv_dpd_check_blackout', [__CLASS__, 'ajax_check_blackout']);
		add_action('woocommerce_before_cart_table', [__CLASS__, 'display_cart_pricing_notice']);
	}

	public static function render_datetime_field(): void {
		// Prevent duplicate rendering
		static $rendered = false;
		if ($rendered) { return; }
		$rendered = true;
		
		global $product;
		$product_id = is_object($product) && method_exists($product, 'get_id') ? intval($product->get_id()) : 0;
		
		// Only show on single product pages
		if (!is_product() || !$product_id) { return; }
		
		echo '<div class="dpd-datetime-field" style="margin: 10px 0; padding: 10px; border: 1px solid #ddd; background: #f9f9f9;">';
		echo '<label><strong>' . esc_html__('Select date and time for pricing', 'dpd') . '</strong></label><br />';
		echo '<input type="date" id="dpd_date" style="margin: 5px;" required /> ';
		// Friendly time selector at 30-minute intervals
		echo '<select id="dpd_time" style="margin: 5px;" required>';
		echo '<option value="">' . esc_html__('Select time', 'dpd') . '</option>';
		
		// Get time range settings
		$time_start = get_option('dpd_time_start', '06:00');
		$time_end = get_option('dpd_time_end', '20:00');
		
		// Parse start and end times
		$start_parts = explode(':', $time_start);
		$end_parts = explode(':', $time_end);
		$start_hour = intval($start_parts[0]);
		$start_min = intval($start_parts[1]);
		$end_hour = intval($end_parts[0]);
		$end_min = intval($end_parts[1]);
		
		// Convert to minutes for easier comparison
		$start_minutes = $start_hour * 60 + $start_min;
		$end_minutes = $end_hour * 60 + $end_min;
		
		for ($h = 0; $h < 24; $h++) {
			foreach (['00','30'] as $m) {
				$current_minutes = $h * 60 + intval($m);
				if ($current_minutes >= $start_minutes && $current_minutes <= $end_minutes) {
					$val = sprintf('%02d:%s', $h, $m);
					echo '<option value="' . esc_attr($val) . '">' . esc_html($val) . '</option>';
				}
			}
		}
		echo '</select>';
		echo '<input type="hidden" id="dpd_selected_datetime" name="' . esc_attr(self::FIELD_KEY) . '" value="" />';
		echo '<input type="hidden" id="dpd_product_id" value="' . esc_attr($product_id) . '" />';
		echo '</div>';
	}

	public static function validate_datetime_field($passed, $product_id, $quantity) {
		// Debug: Log what's being posted
		dpd_debug_log('DPD Validation - POST data: ' . print_r($_POST, true));
		
		if (empty($_POST[self::FIELD_KEY])) {
			wc_add_notice(__('Please select a date and time.', 'dpd'), 'error');
			return false;
		}
		$val = sanitize_text_field(wp_unslash($_POST[self::FIELD_KEY]));
		// Basic format check: YYYY-MM-DDTHH:MM
		if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $val)) {
			wc_add_notice(__('Invalid date/time format.', 'dpd'), 'error');
			return false;
		}
		
		// Check if the selected date is blacked out
		$date = substr($val, 0, 10); // Extract date part (YYYY-MM-DD)
		if (DPD_Rules::is_date_blacked_out($date)) {
			wc_add_notice(__('This date is not available for booking.', 'dpd'), 'error');
			return false;
		}
		
		return $passed;
	}

	public static function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
		if (!empty($_POST[self::FIELD_KEY])) {
			$cart_item_data[self::FIELD_KEY] = sanitize_text_field(wp_unslash($_POST[self::FIELD_KEY]));
		}
		return $cart_item_data;
	}

	public static function display_cart_item_data($item_data, $cart_item) {
		if (!empty($cart_item[self::FIELD_KEY])) {
			$formatted = self::format_datetime_display($cart_item[self::FIELD_KEY]);
			$item_data[] = [
				'key'   => __('Selected Date/Time', 'dpd'),
				'value' => esc_html($formatted),
			];
			
			// Add pricing explanation if rules are applied
			$pricing_explanation = self::get_pricing_explanation($cart_item);
			if ($pricing_explanation) {
				$item_data[] = [
					'key'   => __('Pricing Adjustment', 'dpd'),
					'value' => $pricing_explanation,
				];
			}
		}
		return $item_data;
	}
	
	protected static function get_pricing_explanation($cart_item): ?string {
		if (empty($cart_item[self::FIELD_KEY])) {
			return null;
		}
		
		$product_id = $cart_item['variation_id'] ?: $cart_item['product_id'];
		$product = wc_get_product($product_id);
		if (!$product) {
			return null;
		}
		
		$original_price = $product->get_price();
		$val = $cart_item[self::FIELD_KEY];
		
		// Parse datetime
		$dt = date_create_immutable_from_format('Y-m-d\TH:i', $val, wp_timezone());
		if (!$dt) {
			return null;
		}
		
		$ctx = [
			'dow' => (int)wp_date('w', $dt->getTimestamp()),
			'date' => wp_date('Y-m-d', $dt->getTimestamp())
		];
		
		// Get rules and check if pricing is adjusted
		$main_product_id = $cart_item['product_id'];
		$rule = DPD_Pricing::get_rule_for_context($main_product_id, $ctx);
		
		if ($rule) {
			$adjusted_price = DPD_Rules::apply_rule_to_price(floatval($original_price), $rule);
			
			// Only show explanation if price is actually different
			if ($adjusted_price != $original_price) {
				$day_name = wp_date('l', $dt->getTimestamp());
				$rule_type = $rule['type'] === 'percent' ? 'percentage' : 'fixed amount';
				$rule_direction = $rule['direction'] === 'increase' ? 'increased' : 'reduced';
				$rule_amount = $rule['amount'];
				
				if ($rule['type'] === 'percent') {
					$explanation = sprintf(
						__('Price %s by %s%% for %s (Base: %s)', 'dpd'),
						$rule_direction,
						$rule_amount,
						$day_name,
						wc_price($original_price)
					);
				} else {
					$explanation = sprintf(
						__('Price %s by %s for %s (Base: %s)', 'dpd'),
						$rule_direction,
						wc_price($rule_amount),
						$day_name,
						wc_price($original_price)
					);
				}
				
				return $explanation;
			}
		}
		
		return null;
	}

	public static function add_order_item_meta($item, $cart_item_key, $values, $order) {
		if (!empty($values[self::FIELD_KEY])) {
			$item->add_meta_data(__('Selected Date/Time', 'dpd'), self::format_datetime_display($values[self::FIELD_KEY]), true);
			
			// Add pricing explanation to order
			$pricing_explanation = self::get_pricing_explanation($values);
			if ($pricing_explanation) {
				$item->add_meta_data(__('Pricing Adjustment', 'dpd'), $pricing_explanation, true);
			}
		}
	}

	protected static function format_datetime_display(string $val): string {
		$ts = strtotime($val);
		if (!$ts) { return $val; }
		// Format as mm/dd/yyyy @ hh:mm (site timezone assumed by WP time functions)
		return date_i18n('m/d/Y \@ H:i', $ts);
	}
	
	public static function display_cart_pricing_notice(): void {
		if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
			return;
		}
		
		// Check if any cart items have dynamic pricing applied
		$has_dynamic_pricing = false;
		foreach (WC()->cart->get_cart() as $cart_item) {
			if (!empty($cart_item[self::FIELD_KEY])) {
				$pricing_explanation = self::get_pricing_explanation($cart_item);
				if ($pricing_explanation) {
					$has_dynamic_pricing = true;
					break;
				}
			}
		}
		
		if ($has_dynamic_pricing) {
			echo '<div class="woocommerce-info dpd-cart-notice">';
			echo '<strong>' . esc_html__('Dynamic Pricing Applied', 'dpd') . '</strong><br>';
			echo esc_html__('Prices shown below reflect dynamic pricing adjustments based on your selected dates and times. See individual item details for specific adjustments.', 'dpd');
			echo '</div>';
		}
	}

	public static function enqueue_assets(): void {
		// Enqueue CSS for cart and checkout pages
		if (is_cart() || is_checkout() || is_product()) {
			wp_enqueue_style('dpd-frontend', DPD_PLUGIN_URL . 'assets/dpd-frontend.css', [], DPD_VERSION);
		}
		
		if (!is_product()) {
			dpd_debug_log('DPD: Not on product page, skipping asset enqueue');
			return;
		}
		dpd_debug_log('DPD: Enqueuing frontend assets');
		wp_enqueue_script('dpd-frontend', DPD_PLUGIN_URL . 'assets/dpd-frontend.js', ['jquery'], DPD_VERSION, true);
		$nonce = wp_create_nonce(self::NONCE_KEY);
		wp_localize_script('dpd-frontend', 'DPD_FRONTEND', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'    => $nonce,
		]);
		dpd_debug_log('DPD: Frontend assets enqueued with nonce: ' . $nonce);
	}

	public static function ajax_get_price(): void {
		dpd_debug_log('DPD AJAX: Request received');
		dpd_debug_log('DPD AJAX: POST data: ' . print_r($_POST, true));
		
		check_ajax_referer(self::NONCE_KEY, 'nonce');
		$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
		$variation_id = isset($_POST['variation_id']) ? intval($_POST['variation_id']) : 0;
		$val = isset($_POST['datetime']) ? sanitize_text_field(wp_unslash($_POST['datetime'])) : '';
		
		dpd_debug_log('DPD AJAX: Product ID: ' . $product_id . ', Variation ID: ' . $variation_id . ', DateTime: ' . $val);
		
		if (!$product_id || empty($val)) { 
			dpd_debug_log('DPD AJAX: Bad request - missing product_id or datetime');
			wp_send_json_error(['message' => 'bad_request'], 400); 
		}
		
		// Use variation ID if provided, otherwise use product ID
		$target_id = $variation_id ? $variation_id : $product_id;
		$product = wc_get_product($target_id);
		if (!$product) { 
			dpd_debug_log('DPD AJAX: Product not found for ID: ' . $target_id);
			wp_send_json_error(['message' => 'not_found'], 404); 
		}
		
		$ts = 0;
		$dt = date_create_immutable_from_format('Y-m-d\TH:i', $val, wp_timezone());
		if ($dt instanceof DateTimeImmutable) { $ts = $dt->getTimestamp(); }
		$ctx = $ts ? [ 'dow' => (int)wp_date('w', $ts), 'date' => wp_date('Y-m-d', $ts) ] : DPD_Rules::today_context();
		
		dpd_debug_log('DPD AJAX: Context: ' . print_r($ctx, true));
		
		// Get the original price before any modifications
		// Temporarily remove our pricing filters to get the true original price
		remove_filter('woocommerce_product_get_price', [DPD_Pricing::class, 'filter_price'], 9999);
		remove_filter('woocommerce_product_get_regular_price', [DPD_Pricing::class, 'filter_price'], 9999);
		$price = $product->get_regular_price();
		if (empty($price)) {
			$price = $product->get_price();
		}
		// Re-add our filters
		add_filter('woocommerce_product_get_price', [DPD_Pricing::class, 'filter_price'], 9999, 2);
		add_filter('woocommerce_product_get_regular_price', [DPD_Pricing::class, 'filter_price'], 9999, 2);
		// Get all rules to debug
		$global_rules = DPD_Rules::get_global_rules();
		dpd_debug_log('DPD AJAX: All global rules: ' . print_r($global_rules, true));
		
		// Get product rules too
		$product_rules = DPD_Rules::get_product_rules($product_id);
		dpd_debug_log('DPD AJAX: Product rules for ID ' . $product_id . ': ' . print_r($product_rules, true));
		
		// Use the main product ID for rule lookup, not the variation ID
		$rule = DPD_Pricing::get_rule_for_context($product_id, $ctx);
		$adjusted = $rule ? DPD_Rules::apply_rule_to_price(floatval($price), $rule) : floatval($price);
		
		dpd_debug_log('DPD AJAX: Original price: ' . $price . ', Adjusted: ' . $adjusted . ', Rule: ' . ($rule ? 'Found' : 'None'));
		if ($rule) {
			dpd_debug_log('DPD AJAX: Rule details - DoW: ' . ($rule['dow'] ?? 'empty') . ', Date range: ' . ($rule['date_start'] ?? 'empty') . ' to ' . ($rule['date_end'] ?? 'empty'));
			dpd_debug_log('DPD AJAX: Full rule data: ' . print_r($rule, true));
		} else {
			dpd_debug_log('DPD AJAX: No rule found - Context DoW: ' . $ctx['dow'] . ', Date: ' . $ctx['date']);
			// Debug each rule individually
			$all_rules = array_merge($product_rules, $global_rules);
			foreach ($all_rules as $idx => $test_rule) {
				$matches = DPD_Rules::rule_matches($test_rule, $ctx['dow'], $ctx['date']);
				dpd_debug_log('DPD AJAX: Rule ' . $idx . ' match result: ' . ($matches ? 'YES' : 'NO') . ' - Rule: ' . print_r($test_rule, true));
			}
		}
		
		wp_send_json_success([
			'price' => wc_price($adjusted),
			'value' => $adjusted,
			'original_price' => $price,
			'rule_applied' => $rule ? 'yes' : 'no',
			'context' => $ctx,
			'variation_id' => $variation_id,
			'debug' => [
				'product_id' => $product_id,
				'target_id' => $target_id,
				'global_rules_count' => count($global_rules),
				'product_rules_count' => count($product_rules),
				'context_dow' => $ctx['dow'],
				'context_date' => $ctx['date'],
			],
		]);
	}

	public static function ajax_check_blackout(): void {
		dpd_debug_log('DPD Blackout Check: Request received');
		dpd_debug_log('DPD Blackout Check: POST data: ' . print_r($_POST, true));
		
		check_ajax_referer(self::NONCE_KEY, 'nonce');
		$date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '';
		
		dpd_debug_log('DPD Blackout Check: Date: ' . $date);
		
		if (empty($date)) { 
			dpd_debug_log('DPD Blackout Check: Bad request - missing date');
			wp_send_json_error(['message' => 'bad_request'], 400); 
		}
		
		// Validate date format
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
			dpd_debug_log('DPD Blackout Check: Invalid date format: ' . $date);
			wp_send_json_error(['message' => 'invalid_date'], 400);
		}
		
		$is_blacked_out = DPD_Rules::is_date_blacked_out($date);
		dpd_debug_log('DPD Blackout Check: Date ' . $date . ' is blacked out: ' . ($is_blacked_out ? 'YES' : 'NO'));
		
		wp_send_json_success([
			'is_blacked_out' => $is_blacked_out,
			'date' => $date,
			'message' => $is_blacked_out ? __('This date is not available', 'dpd') : __('Date is available', 'dpd'),
		]);
	}
}


