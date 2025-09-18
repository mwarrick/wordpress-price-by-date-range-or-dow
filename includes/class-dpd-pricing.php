<?php
if (!defined('ABSPATH')) {
	exit;
}

class DPD_Pricing {
	public static function init(): void {
		$filters = [
			'woocommerce_product_get_price',
			'woocommerce_product_get_regular_price',
			'woocommerce_variation_get_price',
			'woocommerce_variation_get_regular_price',
		];
		foreach ($filters as $filter) {
			add_filter($filter, [__CLASS__, 'filter_price'], 9999, 2);
		}
		add_filter('woocommerce_variable_price_html', [__CLASS__, 'variable_price_html'], 9999, 2);
		add_filter('woocommerce_get_price_html', [__CLASS__, 'maybe_show_adjusted_suffix'], 9999, 2);
		
		// Add cart-specific pricing hooks
		add_filter('woocommerce_cart_item_price', [__CLASS__, 'filter_cart_item_price'], 9999, 3);
		add_filter('woocommerce_cart_item_subtotal', [__CLASS__, 'filter_cart_item_subtotal'], 9999, 3);
		add_action('woocommerce_add_to_cart', [__CLASS__, 'apply_cart_item_pricing'], 10, 6);
	}

	public static function filter_price($price, $product) {
		if ($price === '' || $price === null) { return $price; }
		$price_float = floatval($price);
		if ($price_float <= 0) { return $price; }
		
		$product_id = self::resolve_product_id($product);
		if (!$product_id) { return $price; }
		
		dpd_debug_log('DPD Cart Filter: Product ID ' . $product_id . ', Price: ' . $price);
		
		// Only apply pricing in cart/checkout context where date/time is selected
		$selected = self::get_selected_datetime_context($product_id);
		dpd_debug_log('DPD Cart Filter: Selected context: ' . print_r($selected, true));
		
		if (!$selected) { 
			dpd_debug_log('DPD Cart Filter: No selected context, returning original price');
			return $price; 
		}
		
		$product_rules = DPD_Rules::filter_rules_for_apply(DPD_Rules::get_product_rules($product_id));
		$rule = self::pick_rule_with_context($product_rules, $selected);
		if (!$rule) {
			$global_rules = DPD_Rules::filter_rules_for_apply(DPD_Rules::get_global_rules());
			$rule = self::pick_rule_with_context($global_rules, $selected);
		}
		
		if ($rule) {
			$adjusted = DPD_Rules::apply_rule_to_price($price_float, $rule);
			dpd_debug_log('DPD Cart Filter: Applied rule, adjusted price from ' . $price . ' to ' . $adjusted);
			return $adjusted;
		}
		
		dpd_debug_log('DPD Cart Filter: No rule found, returning original price');
		return $price;
	}

	protected static function get_selected_datetime_context(int $product_id): ?array {
		// Default to "today" if nothing selected
		$ts = current_time('timestamp');
		$use_ts = $ts;
		dpd_debug_log('DPD Cart Context: Looking for product ID ' . $product_id);
		
		if (function_exists('WC') && WC()->cart) {
			$cart_items = WC()->cart->get_cart();
			dpd_debug_log('DPD Cart Context: Cart has ' . count($cart_items) . ' items');
			
			foreach ($cart_items as $cart_item_key => $cart_item) {
				dpd_debug_log('DPD Cart Context: Checking cart item - product_id: ' . ($cart_item['product_id'] ?? 'none') . ', variation_id: ' . ($cart_item['variation_id'] ?? 'none') . ', datetime: ' . ($cart_item[DPD_Frontend::FIELD_KEY] ?? 'none'));
				
				// Check if this cart item matches the product_id (for simple products) or variation_id (for variations)
				$matches = false;
				if (!empty($cart_item['product_id']) && intval($cart_item['product_id']) === $product_id) {
					$matches = true;
					dpd_debug_log('DPD Cart Context: Matched by product_id');
				} elseif (!empty($cart_item['variation_id']) && intval($cart_item['variation_id']) === $product_id) {
					$matches = true;
					dpd_debug_log('DPD Cart Context: Matched by variation_id');
				}
				
				if ($matches && !empty($cart_item[DPD_Frontend::FIELD_KEY])) {
					$val = $cart_item[DPD_Frontend::FIELD_KEY];
					dpd_debug_log('DPD Cart Context: Found datetime value: ' . $val);
					// Expecting site-local YYYY-MM-DDTHH:MM
					$dt = date_create_immutable_from_format('Y-m-d\TH:i', $val, wp_timezone());
					if ($dt instanceof DateTimeImmutable) { 
						$use_ts = $dt->getTimestamp(); 
						dpd_debug_log('DPD Cart Context: Using datetime timestamp: ' . $use_ts);
						break; 
					}
				}
			}
		} else {
			dpd_debug_log('DPD Cart Context: No cart available');
		}
		
		$context = [
			'dow'  => (int)wp_date('w', $use_ts),
			'date' => wp_date('Y-m-d', $use_ts),
		];
		dpd_debug_log('DPD Cart Context: Final context: ' . print_r($context, true));
		return $context;
	}

	protected static function pick_rule_with_context(array $rules, ?array $ctx): ?array {
		if (empty($rules)) { 
			dpd_debug_log('DPD Pick Rule: No rules to check');
			return null; 
		}
		if (!$ctx) { 
			dpd_debug_log('DPD Pick Rule: No context, using pick_applicable_rule');
			return DPD_Rules::pick_applicable_rule($rules); 
		}
		dpd_debug_log('DPD Pick Rule: Checking ' . count($rules) . ' rules with context: DoW=' . $ctx['dow'] . ', Date=' . $ctx['date']);
		$match = null;
		foreach ($rules as $rule) {
			dpd_debug_log('DPD Pick Rule: Checking rule - DoW: ' . ($rule['dow'] ?? 'empty') . ', Date range: ' . ($rule['date_start'] ?? 'empty') . ' to ' . ($rule['date_end'] ?? 'empty'));
			if (DPD_Rules::rule_matches($rule, $ctx['dow'], $ctx['date'])) { 
				dpd_debug_log('DPD Pick Rule: Rule matched!');
				$match = $rule; 
			}
		}
		dpd_debug_log('DPD Pick Rule: Final result: ' . ($match ? 'Found match' : 'No match'));
		return $match;
	}

	public static function get_rule_for_context(int $product_id, array $ctx): ?array {
		// For variations, get the main product ID for rule lookup
		$main_product_id = $product_id;
		$product = wc_get_product($product_id);
		if ($product && $product->is_type('variation')) {
			$main_product_id = $product->get_parent_id();
		}
		
		$product_rules = DPD_Rules::filter_rules_for_apply(DPD_Rules::get_product_rules($main_product_id));
		$rule = self::pick_rule_with_context($product_rules, $ctx);
		if ($rule) { return $rule; }
		$global_rules = DPD_Rules::filter_rules_for_apply(DPD_Rules::get_global_rules());
		return self::pick_rule_with_context($global_rules, $ctx);
	}

	protected static function resolve_product_id($product): ?int {
		if (is_numeric($product)) { return intval($product); }
		if (is_object($product) && method_exists($product, 'get_id')) { return intval($product->get_id()); }
		return null;
	}

	public static function variable_price_html($price_html, $product) {
		// Don't interfere with WooCommerce's normal variation price display
		// Let WooCommerce handle the variation pricing display naturally
		return $price_html;
	}

	public static function maybe_show_adjusted_suffix($price_html, $product) {
		return $price_html;
	}
	
	public static function filter_cart_item_price($price_html, $cart_item, $cart_item_key) {
		dpd_debug_log('DPD Cart Item Price: Called for cart item key ' . $cart_item_key);
		
		if (empty($cart_item[DPD_Frontend::FIELD_KEY])) {
			dpd_debug_log('DPD Cart Item Price: No datetime data, returning original price');
			return $price_html;
		}
		
		$product_id = $cart_item['variation_id'] ?: $cart_item['product_id'];
		$product = wc_get_product($product_id);
		if (!$product) {
			dpd_debug_log('DPD Cart Item Price: Product not found for ID ' . $product_id);
			return $price_html;
		}
		
		$original_price = $product->get_price();
		$val = $cart_item[DPD_Frontend::FIELD_KEY];
		
		// Parse datetime
		$dt = date_create_immutable_from_format('Y-m-d\TH:i', $val, wp_timezone());
		if (!$dt) {
			dpd_debug_log('DPD Cart Item Price: Invalid datetime format: ' . $val);
			return $price_html;
		}
		
		$ctx = [
			'dow' => (int)wp_date('w', $dt->getTimestamp()),
			'date' => wp_date('Y-m-d', $dt->getTimestamp())
		];
		
		dpd_debug_log('DPD Cart Item Price: Context: ' . print_r($ctx, true));
		
		// Get rules and apply pricing
		$main_product_id = $cart_item['product_id'];
		$rule = self::get_rule_for_context($main_product_id, $ctx);
		
		if ($rule) {
			$adjusted_price = DPD_Rules::apply_rule_to_price(floatval($original_price), $rule);
			dpd_debug_log('DPD Cart Item Price: Applied rule, adjusted from ' . $original_price . ' to ' . $adjusted_price);
			return wc_price($adjusted_price);
		}
		
		dpd_debug_log('DPD Cart Item Price: No rule found, returning original price');
		return $price_html;
	}
	
	public static function filter_cart_item_subtotal($subtotal_html, $cart_item, $cart_item_key) {
		dpd_debug_log('DPD Cart Item Subtotal: Called for cart item key ' . $cart_item_key);
		
		if (empty($cart_item[DPD_Frontend::FIELD_KEY])) {
			return $subtotal_html;
		}
		
		$product_id = $cart_item['variation_id'] ?: $cart_item['product_id'];
		$product = wc_get_product($product_id);
		if (!$product) {
			return $subtotal_html;
		}
		
		$original_price = $product->get_price();
		$quantity = $cart_item['quantity'];
		$val = $cart_item[DPD_Frontend::FIELD_KEY];
		
		// Parse datetime
		$dt = date_create_immutable_from_format('Y-m-d\TH:i', $val, wp_timezone());
		if (!$dt) {
			return $subtotal_html;
		}
		
		$ctx = [
			'dow' => (int)wp_date('w', $dt->getTimestamp()),
			'date' => wp_date('Y-m-d', $dt->getTimestamp())
		];
		
		// Get rules and apply pricing
		$main_product_id = $cart_item['product_id'];
		$rule = self::get_rule_for_context($main_product_id, $ctx);
		
		if ($rule) {
			$adjusted_price = DPD_Rules::apply_rule_to_price(floatval($original_price), $rule);
			$adjusted_subtotal = $adjusted_price * $quantity;
			dpd_debug_log('DPD Cart Item Subtotal: Adjusted subtotal from ' . ($original_price * $quantity) . ' to ' . $adjusted_subtotal);
			return wc_price($adjusted_subtotal);
		}
		
		return $subtotal_html;
	}
	
	public static function apply_cart_item_pricing($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
		dpd_debug_log('DPD Add to Cart: Item added with key ' . $cart_item_key . ', product_id ' . $product_id . ', variation_id ' . $variation_id);
		dpd_debug_log('DPD Add to Cart: Cart item data: ' . print_r($cart_item_data, true));
		
		// This hook is called after the item is added to cart
		// We can use this to trigger cart recalculation if needed
		if (!empty($cart_item_data[DPD_Frontend::FIELD_KEY])) {
			dpd_debug_log('DPD Add to Cart: Datetime data found, item should be priced correctly');
		}
	}
}
