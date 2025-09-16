<?php
/**
 * Uninstall cleanup for Dynamic Pricing by Date (Woo)
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

// Remove global option
delete_option('dpd_global_rules');

// Remove per-product meta across all products
global $wpdb;
$meta_key = '_dpd_product_rules';
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", $meta_key));

