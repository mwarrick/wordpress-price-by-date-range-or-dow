<?php
/*
Plugin Name: Dynamic Pricing by Date (Woo)
Description: Adjust WooCommerce product prices by day-of-week and date range, with global and per-product rules.
Version: 1.0.0
Author: You
Text Domain: dpd
*/

if (!defined('ABSPATH')) {
	return;
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins', [])))) {
	return;
}

define('DPD_VERSION', '1.0.0');
define('DPD_PLUGIN_FILE', __FILE__);
define('DPD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DPD_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once DPD_PLUGIN_DIR . 'includes/class-dpd-rules.php';
require_once DPD_PLUGIN_DIR . 'includes/class-dpd-admin.php';
require_once DPD_PLUGIN_DIR . 'includes/class-dpd-pricing.php';

add_action('plugins_loaded', function () {
	DPD_Admin::init();
	DPD_Pricing::init();
});
