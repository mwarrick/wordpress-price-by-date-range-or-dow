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
	}

	public static function filter_price($price, $product) {
		if ($price === '' || $price === null) { return $price; }
		$price_float = floatval($price);
		if ($price_float <= 0) { return $price; }
		$product_id = self::resolve_product_id($product);
		if (!$product_id) { return $price; }
		$product_rules = DPD_Rules::filter_rules_for_apply(DPD_Rules::get_product_rules($product_id));
		// Check if a cart context selected datetime exists for this product
		$selected = self::get_selected_datetime_context($product_id);
		$rule = self::pick_rule_with_context($product_rules, $selected);
		if (!$rule) {
			$global_rules = DPD_Rules::filter_rules_for_apply(DPD_Rules::get_global_rules());
			$rule = self::pick_rule_with_context($global_rules, $selected);
		}
		if ($rule) { return DPD_Rules::apply_rule_to_price($price_float, $rule); }
		return $price;
	}

	protected static function get_selected_datetime_context(int $product_id): ?array {
		// Default to "today" if nothing selected
		$ts = current_time('timestamp');
		$use_ts = $ts;
		if (function_exists('WC') && WC()->cart) {
			foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
				if (!empty($cart_item['product_id']) && intval($cart_item['product_id']) === $product_id && !empty($cart_item[DPD_Frontend::FIELD_KEY])) {
					$val = $cart_item[DPD_Frontend::FIELD_KEY];
					// Expecting site-local YYYY-MM-DDTHH:MM
					$dt = date_create_immutable_from_format('Y-m-d\TH:i', $val, wp_timezone());
					if ($dt instanceof DateTimeImmutable) { $use_ts = $dt->getTimestamp(); break; }
				}
			}
		}
		return [
			'dow'  => (int)wp_date('w', $use_ts),
			'date' => wp_date('Y-m-d', $use_ts),
		];
	}

	protected static function pick_rule_with_context(array $rules, ?array $ctx): ?array {
		if (empty($rules)) { 
			error_log('DPD Pick Rule: No rules to check');
			return null; 
		}
		if (!$ctx) { 
			error_log('DPD Pick Rule: No context, using pick_applicable_rule');
			return DPD_Rules::pick_applicable_rule($rules); 
		}
		error_log('DPD Pick Rule: Checking ' . count($rules) . ' rules with context: DoW=' . $ctx['dow'] . ', Date=' . $ctx['date']);
		$match = null;
		foreach ($rules as $rule) {
			error_log('DPD Pick Rule: Checking rule - DoW: ' . ($rule['dow'] ?? 'empty') . ', Date range: ' . ($rule['date_start'] ?? 'empty') . ' to ' . ($rule['date_end'] ?? 'empty'));
			if (DPD_Rules::rule_matches($rule, $ctx['dow'], $ctx['date'])) { 
				error_log('DPD Pick Rule: Rule matched!');
				$match = $rule; 
			}
		}
		error_log('DPD Pick Rule: Final result: ' . ($match ? 'Found match' : 'No match'));
		return $match;
	}

	public static function get_rule_for_context(int $product_id, array $ctx): ?array {
		$product_rules = DPD_Rules::filter_rules_for_apply(DPD_Rules::get_product_rules($product_id));
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
		if (!$product instanceof WC_Product_Variable) { return $price_html; }
		$prices = $product->get_variation_prices(true);
		if (empty($prices['price'])) { return $price_html; }
		$vals = array_values($prices['price']);
		$min = min($vals);
		$max = max($vals);
		if ($min === $max) { return wc_price($min); }
		return wc_price($min) . ' - ' . wc_price($max);
	}

	public static function maybe_show_adjusted_suffix($price_html, $product) {
		return $price_html;
	}
}
