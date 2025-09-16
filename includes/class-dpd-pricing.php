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
		$product_rules = DPD_Rules::get_product_rules($product_id);
		$rule = DPD_Rules::pick_applicable_rule($product_rules);
		if (!$rule) {
			$global_rules = DPD_Rules::get_global_rules();
			$rule = DPD_Rules::pick_applicable_rule($global_rules);
		}
		if ($rule) { return DPD_Rules::apply_rule_to_price($price_float, $rule); }
		return $price;
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
		$min = current($prices['price']);
		$max = end($prices['price']);
		if ($min === $max) { return wc_price($min); }
		return wc_price($min) . ' - ' . wc_price($max);
	}

	public static function maybe_show_adjusted_suffix($price_html, $product) {
		return $price_html;
	}
}
