<?php
/*
Plugin Name: Dynamic Pricing by Date (Woo)
Description: Adjust WooCommerce product prices by day-of-week and date range, with global and per-product rules.
Version: 1.5.0
Author: Mark Warrick
Text Domain: dpd
*/

if (!defined('ABSPATH')) {
	return;
}

define('DPD_VERSION', '1.5.0');
define('DPD_PLUGIN_FILE', __FILE__);
define('DPD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DPD_PLUGIN_URL', plugin_dir_url(__FILE__));

// Prevent double-initialization if another copy of the plugin is active
if (defined('DPD_PLUGIN_LOADED')) {
	return;
}
define('DPD_PLUGIN_LOADED', true);

// Check if WooCommerce is active (supports multisite/network)
$woo_active = in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins', [])));
if (!$woo_active && is_multisite()) {
	$woo_active = in_array('woocommerce/woocommerce.php', get_site_option('active_sitewide_plugins', []));
}
if (!$woo_active) {
	// Debug: Add admin notice if WooCommerce not found
	add_action('admin_notices', function() {
		echo '<div class="notice notice-error"><p>DPD Plugin: WooCommerce not found or not active</p></div>';
	});
	return;
}

require_once DPD_PLUGIN_DIR . 'includes/class-dpd-rules.php';
require_once DPD_PLUGIN_DIR . 'includes/class-dpd-admin.php';
require_once DPD_PLUGIN_DIR . 'includes/class-dpd-pricing.php';
require_once DPD_PLUGIN_DIR . 'includes/class-dpd-frontend.php';

add_action('plugins_loaded', function () {
	// Final check that WooCommerce is actually loaded
	if (!class_exists('WooCommerce')) {
		add_action('admin_notices', function() {
			echo '<div class="notice notice-error"><p>DPD Plugin: WooCommerce classes not loaded</p></div>';
		});
		return;
	}
	
	load_plugin_textdomain('dpd', false, dirname(plugin_basename(DPD_PLUGIN_FILE)) . '/languages');
	
	// Debug: Check if classes exist
	if (class_exists('DPD_Admin')) {
		DPD_Admin::init();
	} else {
		error_log('DPD_Admin class not found');
	}
	
	if (class_exists('DPD_Pricing')) {
		DPD_Pricing::init();
	} else {
		error_log('DPD_Pricing class not found');
	}
	
	if (class_exists('DPD_Frontend')) {
		DPD_Frontend::init();
	} else {
		error_log('DPD_Frontend class not found');
	}
});
