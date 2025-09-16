<?php
if (!defined('ABSPATH')) {
	exit;
}

class DPD_Frontend {
	const FIELD_KEY = 'dpd_selected_datetime';

	public static function init(): void {
		add_action('woocommerce_before_add_to_cart_button', [__CLASS__, 'render_datetime_field']);
		add_filter('woocommerce_add_to_cart_validation', [__CLASS__, 'validate_datetime_field'], 10, 3);
		add_filter('woocommerce_add_cart_item_data', [__CLASS__, 'add_cart_item_data'], 10, 3);
		add_filter('woocommerce_get_item_data', [__CLASS__, 'display_cart_item_data'], 10, 2);
		add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'add_order_item_meta'], 10, 4);
	}

	public static function render_datetime_field(): void {
		echo '<div class="dpd-datetime-field">';
		echo '<label for="dpd_selected_datetime">' . esc_html__('Select date and time', 'dpd') . '</label> ';
		echo '<input type="datetime-local" id="dpd_selected_datetime" name="' . esc_attr(self::FIELD_KEY) . '" required />';
		echo '</div>';
	}

	public static function validate_datetime_field($passed, $product_id, $quantity) {
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
			$item_data[] = [
				'key'   => __('Selected Date/Time', 'dpd'),
				'value' => esc_html($cart_item[self::FIELD_KEY]),
			];
		}
		return $item_data;
	}

	public static function add_order_item_meta($item, $cart_item_key, $values, $order) {
		if (!empty($values[self::FIELD_KEY])) {
			$item->add_meta_data(__('Selected Date/Time', 'dpd'), $values[self::FIELD_KEY], true);
		}
	}
}


