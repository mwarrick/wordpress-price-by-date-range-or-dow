<?php
if (!defined('ABSPATH')) {
	exit;
}

class DPD_Frontend {
	const FIELD_KEY = 'dpd_selected_datetime';
	const NONCE_KEY = 'dpd_ajax_nonce';

	public static function init(): void {
		add_action('woocommerce_before_add_to_cart_button', [__CLASS__, 'render_datetime_field']);
		add_filter('woocommerce_add_to_cart_validation', [__CLASS__, 'validate_datetime_field'], 10, 3);
		add_filter('woocommerce_add_cart_item_data', [__CLASS__, 'add_cart_item_data'], 10, 3);
		add_filter('woocommerce_get_item_data', [__CLASS__, 'display_cart_item_data'], 10, 2);
		add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'add_order_item_meta'], 10, 4);
		add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
		add_action('wp_ajax_dpd_get_price', [__CLASS__, 'ajax_get_price']);
		add_action('wp_ajax_nopriv_dpd_get_price', [__CLASS__, 'ajax_get_price']);
	}

	public static function render_datetime_field(): void {
		global $product;
		$product_id = is_object($product) && method_exists($product, 'get_id') ? intval($product->get_id()) : 0;
		echo '<div class="dpd-datetime-field">';
		echo '<label>' . esc_html__('Select date and time', 'dpd') . '</label><br />';
		echo '<input type="date" id="dpd_date" /> ';
		echo '<input type="time" id="dpd_time" />';
		echo '<input type="hidden" id="dpd_selected_datetime" name="' . esc_attr(self::FIELD_KEY) . '" />';
		echo '<input type="hidden" id="dpd_product_id" value="' . esc_attr($product_id) . '" />';
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
			$formatted = self::format_datetime_display($cart_item[self::FIELD_KEY]);
			$item_data[] = [
				'key'   => __('Selected Date/Time', 'dpd'),
				'value' => esc_html($formatted),
			];
		}
		return $item_data;
	}

	public static function add_order_item_meta($item, $cart_item_key, $values, $order) {
		if (!empty($values[self::FIELD_KEY])) {
			$item->add_meta_data(__('Selected Date/Time', 'dpd'), self::format_datetime_display($values[self::FIELD_KEY]), true);
		}
	}

	protected static function format_datetime_display(string $val): string {
		$ts = strtotime($val);
		if (!$ts) { return $val; }
		// Format as mm/dd/yyyy @ hh:mm (site timezone assumed by WP time functions)
		return date_i18n('m/d/Y \@ H:i', $ts);
	}

	public static function enqueue_assets(): void {
		if (!is_product()) { return; }
		wp_enqueue_script('dpd-frontend', DPD_PLUGIN_URL . 'assets/dpd-frontend.js', ['jquery'], DPD_VERSION, true);
		$nonce = wp_create_nonce(self::NONCE_KEY);
		wp_localize_script('dpd-frontend', 'DPD_FRONTEND', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'    => $nonce,
		]);
	}

	public static function ajax_get_price(): void {
		check_ajax_referer(self::NONCE_KEY, 'nonce');
		$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
		$val = isset($_POST['datetime']) ? sanitize_text_field(wp_unslash($_POST['datetime'])) : '';
		if (!$product_id || empty($val)) { wp_send_json_error(['message' => 'bad_request'], 400); }
		$product = wc_get_product($product_id);
		if (!$product) { wp_send_json_error(['message' => 'not_found'], 404); }
		$ts = strtotime($val);
		$ctx = $ts ? [ 'dow' => (int)gmdate('w', $ts), 'date' => gmdate('Y-m-d', $ts) ] : DPD_Rules::today_context();
		$price = $product->get_price();
		$rule = DPD_Pricing::get_rule_for_context($product_id, $ctx);
		$adjusted = $rule ? DPD_Rules::apply_rule_to_price(floatval($price), $rule) : floatval($price);
		wp_send_json_success([
			'price' => wc_price($adjusted),
			'value' => $adjusted,
		]);
	}
}


