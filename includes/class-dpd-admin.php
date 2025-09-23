<?php
if (!defined('ABSPATH')) {
	exit;
}

class DPD_Admin {
	public static function init(): void {
		add_action('admin_menu', [__CLASS__, 'add_menu']);
		// Removed sidebar metabox to prevent duplication with product data tab
		// add_action('add_meta_boxes', [__CLASS__, 'add_product_metabox']);
		add_action('save_post_product', [__CLASS__, 'save_product_rules']);
		add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
		// WooCommerce product data tab (works with the standard product editor UI)
		add_filter('woocommerce_product_data_tabs', [__CLASS__, 'add_product_data_tab']);
		add_action('woocommerce_product_data_panels', [__CLASS__, 'render_product_data_panel']);
		add_action('woocommerce_admin_process_product_object', [__CLASS__, 'save_product_rules_from_product_object']);
		// AJAX endpoints for product rules operations
		add_action('wp_ajax_dpd_delete_all_product_rules', [__CLASS__, 'ajax_delete_all_product_rules']);
	}

	public static function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__('Dynamic Pricing by Date', 'dpd'),
			__('Dynamic Pricing by Date', 'dpd'),
			'manage_woocommerce',
			'dpd-settings',
			[__CLASS__, 'render_settings_page']
		);
		add_submenu_page(
			'woocommerce',
			__('DPD Diagnostics', 'dpd'),
			__('DPD Diagnostics', 'dpd'),
			'manage_woocommerce',
			'dpd-diagnostics',
			[__CLASS__, 'render_diagnostics_page']
		);
	}

	public static function enqueue_admin_assets($hook): void {
		if ($hook === 'woocommerce_page_dpd-settings' || $hook === 'woocommerce_page_dpd-diagnostics' || $hook === 'post.php' || $hook === 'post-new.php') {
			wp_enqueue_style('dpd-admin', DPD_PLUGIN_URL . 'assets/dpd-admin.css', [], DPD_VERSION);
			wp_enqueue_script('dpd-admin', DPD_PLUGIN_URL . 'assets/dpd-admin.js', ['jquery'], DPD_VERSION, true);
		}
	}

	public static function render_settings_page(): void {
		if (!current_user_can('manage_woocommerce')) { return; }
		$notice = '';
		// Handle time range settings form
		if (isset($_POST['dpd_save_time_settings']) && check_admin_referer('dpd_save_time_settings_action', 'dpd_time_nonce')) {
			$time_start = sanitize_text_field($_POST['dpd_time_start'] ?? '06:00');
			$time_end = sanitize_text_field($_POST['dpd_time_end'] ?? '20:00');
			
			// Debug: Log what we're trying to save
			dpd_debug_log('DPD Admin: Saving time settings - Start: ' . $time_start . ', End: ' . $time_end);
			
			update_option('dpd_time_start', $time_start);
			update_option('dpd_time_end', $time_end);
			
			// Debug: Verify what was actually saved
			$saved_start = get_option('dpd_time_start');
			$saved_end = get_option('dpd_time_end');
			dpd_debug_log('DPD Admin: Verified saved settings - Start: ' . $saved_start . ', End: ' . $saved_end);
			
			$notice = __('Time settings saved.', 'dpd');
		}
		
		// Handle pricing rules form
		if (isset($_POST['dpd_save_global']) && check_admin_referer('dpd_save_global_action', 'dpd_nonce')) {
			$rules = isset($_POST['dpd_rules']) && is_array($_POST['dpd_rules']) ? $_POST['dpd_rules'] : [];
			$clean = DPD_Rules::sanitize_rules_array($rules);
			DPD_Rules::save_global_rules($clean);
			$notice = __('Pricing rules saved.', 'dpd');
		}
		
		// Handle blackout dates form
		if (isset($_POST['dpd_save_blackouts']) && check_admin_referer('dpd_save_blackouts_action', 'dpd_blackouts_nonce')) {
			$blackouts = isset($_POST['dpd_blackouts']) && is_array($_POST['dpd_blackouts']) ? $_POST['dpd_blackouts'] : [];
			$clean = DPD_Rules::sanitize_blackout_array($blackouts);
			DPD_Rules::save_blackout_dates($clean);
			$notice = __('Blackout dates saved.', 'dpd');
		}
		$rules = DPD_Rules::get_global_rules();
		$blackouts = DPD_Rules::get_blackout_dates();
		$time_start = get_option('dpd_time_start', '06:00');
		$time_end = get_option('dpd_time_end', '20:00');
		
		// Debug: Show current values
		dpd_debug_log('DPD Admin: Current time settings - Start: ' . $time_start . ', End: ' . $time_end);
		?>
		<div class="wrap">
			<h1><?php esc_html_e('Dynamic Pricing by Date — Global Rules', 'dpd'); ?></h1>
			<?php if ($notice): ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
			<?php endif; ?>
			
			<h2><?php esc_html_e('Time Range Settings', 'dpd'); ?></h2>
			<p><?php esc_html_e('Set the available time range for tour bookings. Times outside this range will not be selectable.', 'dpd'); ?></p>
			<form method="post">
				<?php wp_nonce_field('dpd_save_time_settings_action', 'dpd_time_nonce'); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e('Start Time', 'dpd'); ?></th>
						<td>
							<select name="dpd_time_start">
								<?php
								for ($h = 0; $h < 24; $h++) {
									foreach (['00','30'] as $m) {
										$val = sprintf('%02d:%s', $h, $m);
										$selected = ($val === $time_start) ? ' selected' : '';
										echo '<option value="' . esc_attr($val) . '"' . $selected . '>' . esc_html($val) . '</option>';
									}
								}
								?>
							</select>
							<p class="description"><?php esc_html_e('Earliest time available for booking', 'dpd'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('End Time', 'dpd'); ?></th>
						<td>
							<select name="dpd_time_end">
								<?php
								for ($h = 0; $h < 24; $h++) {
									foreach (['00','30'] as $m) {
										$val = sprintf('%02d:%s', $h, $m);
										$selected = ($val === $time_end) ? ' selected' : '';
										echo '<option value="' . esc_attr($val) . '"' . $selected . '>' . esc_html($val) . '</option>';
									}
								}
								?>
							</select>
							<p class="description"><?php esc_html_e('Latest time available for booking', 'dpd'); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(__('Save Time Settings', 'dpd'), 'primary', 'dpd_save_time_settings'); ?>
			</form>
			
			<h2><?php esc_html_e('Blackout Dates', 'dpd'); ?></h2>
			<p><?php esc_html_e('Configure dates when products cannot be purchased. Customers will see "This date is not available" instead of the add to cart button.', 'dpd'); ?></p>
			<form method="post">
				<?php wp_nonce_field('dpd_save_blackouts_action', 'dpd_blackouts_nonce'); ?>
				<?php
					// Prepare separate lists for date range and day-of-week blackouts
					$date_range_blackouts = [];
					$dow_blackouts = [];
					$source_blackouts = is_array($blackouts) ? $blackouts : [];
					foreach ($source_blackouts as $b) {
						$type = $b['type'] ?? 'date_range';
						if ($type === 'day_of_week') { $dow_blackouts[] = $b; }
						else { $date_range_blackouts[] = $b; }
					}
					if (empty($date_range_blackouts)) { $date_range_blackouts = [[ 'enabled' => '0', 'type' => 'date_range', 'date_start' => '', 'date_end' => '' ]]; }
					if (empty($dow_blackouts)) { $dow_blackouts = [[ 'enabled' => '0', 'type' => 'day_of_week', 'dow' => '' ]]; }
					$global_idx = 0; // unique running index across both tables
				?>

				<h3><?php esc_html_e('Blackout by Date Range', 'dpd'); ?></h3>
				<table class="widefat dpd-blackouts-table">
					<thead>
						<tr>
							<th><?php esc_html_e('Enabled', 'dpd'); ?></th>
							<th><?php esc_html_e('Start Date', 'dpd'); ?></th>
							<th><?php esc_html_e('End Date', 'dpd'); ?></th>
							<th><?php esc_html_e('Actions', 'dpd'); ?></th>
						</tr>
					</thead>
					<tbody id="dpd-blackouts-body-date-range">
						<?php foreach ($date_range_blackouts as $blackout): ?>
						<tr class="dpd-blackout-row dpd-blackout-date-range-row">
							<td>
								<input type="checkbox" name="dpd_blackouts[<?php echo esc_attr($global_idx); ?>][enabled]" value="1" <?php checked($blackout['enabled'] ?? '0', '1'); ?> />
								<input type="hidden" name="dpd_blackouts[<?php echo esc_attr($global_idx); ?>][type]" value="date_range" />
							</td>
							<td class="dpd-date-start-field"><input type="date" name="dpd_blackouts[<?php echo esc_attr($global_idx); ?>][date_start]" value="<?php echo esc_attr($blackout['date_start'] ?? ''); ?>" /></td>
							<td class="dpd-date-end-field"><input type="date" name="dpd_blackouts[<?php echo esc_attr($global_idx); ?>][date_end]" value="<?php echo esc_attr($blackout['date_end'] ?? ''); ?>" /></td>
							<td><button type="button" class="button dpd-remove-blackout-row"><?php esc_html_e('Remove', 'dpd'); ?></button></td>
						</tr>
						<?php $global_idx++; endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button button-secondary" id="dpd-add-blackout-date-range-row"><?php esc_html_e('Add Date Range', 'dpd'); ?></button></p>

				<h3><?php esc_html_e('Blackout by Day of Week', 'dpd'); ?></h3>
				<table class="widefat dpd-blackouts-table">
					<thead>
						<tr>
							<th><?php esc_html_e('Enabled', 'dpd'); ?></th>
							<th><?php esc_html_e('Day of Week', 'dpd'); ?></th>
							<th><?php esc_html_e('Actions', 'dpd'); ?></th>
						</tr>
					</thead>
					<tbody id="dpd-blackouts-body-dow">
						<?php foreach ($dow_blackouts as $blackout): ?>
						<tr class="dpd-blackout-row dpd-blackout-dow-row">
							<td>
								<input type="checkbox" name="dpd_blackouts[<?php echo esc_attr($global_idx); ?>][enabled]" value="1" <?php checked($blackout['enabled'] ?? '0', '1'); ?> />
								<input type="hidden" name="dpd_blackouts[<?php echo esc_attr($global_idx); ?>][type]" value="day_of_week" />
							</td>
							<td class="dpd-dow-field">
								<select name="dpd_blackouts[<?php echo esc_attr($global_idx); ?>][dow]">
									<option value="" <?php selected(($blackout['dow'] ?? ''), ''); ?>><?php esc_html_e('Any', 'dpd'); ?></option>
									<?php $dows = ['0'=>__('Sunday','dpd'),'1'=>__('Monday','dpd'),'2'=>__('Tuesday','dpd'),'3'=>__('Wednesday','dpd'),'4'=>__('Thursday','dpd'),'5'=>__('Friday','dpd'),'6'=>__('Saturday','dpd')]; foreach ($dows as $val=>$label) { echo '<option value="'.esc_attr($val).'"'. selected(($blackout['dow'] ?? ''), $val, false) .'>'. esc_html($label) .'</option>'; } ?>
								</select>
							</td>
							<td><button type="button" class="button dpd-remove-blackout-row"><?php esc_html_e('Remove', 'dpd'); ?></button></td>
						</tr>
						<?php $global_idx++; endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button button-secondary" id="dpd-add-blackout-dow-row"><?php esc_html_e('Add Day of Week', 'dpd'); ?></button></p>

				<p><button type="submit" class="button button-primary" name="dpd_save_blackouts" value="1"><?php esc_html_e('Save Blackout Dates', 'dpd'); ?></button></p>
			</form>
			
			<h2><?php esc_html_e('Global Pricing Rules', 'dpd'); ?></h2>
			<form method="post">
				<?php wp_nonce_field('dpd_save_global_action', 'dpd_nonce'); ?>
				<table class="widefat dpd-rules-table">
					<thead>
						<tr>
							<th><?php esc_html_e('Enabled', 'dpd'); ?></th>
							<th><?php esc_html_e('Day of Week', 'dpd'); ?></th>
							<th><?php esc_html_e('Start Date', 'dpd'); ?></th>
							<th><?php esc_html_e('End Date', 'dpd'); ?></th>
							<th><?php esc_html_e('Type', 'dpd'); ?></th>
							<th><?php esc_html_e('Direction', 'dpd'); ?></th>
							<th><?php esc_html_e('Amount', 'dpd'); ?></th>
							<th><?php esc_html_e('Actions', 'dpd'); ?></th>
						</tr>
					</thead>
					<tbody id="dpd-rules-body">
						<?php
						if (empty($rules)) {
							$rules = [[
								'enabled' => '0',
								'dow' => '',
								'date_start' => '',
								'date_end' => '',
								'type' => 'percent',
								'direction' => 'increase',
								'amount' => '10',
							]];
						}
						foreach ($rules as $idx => $rule):
						?>
						<tr class="dpd-rule-row">
							<td><input type="checkbox" name="dpd_rules[<?php echo esc_attr($idx); ?>][enabled]" value="1" <?php checked($rule['enabled'] ?? '0', '1'); ?> /></td>
							<td>
								<select name="dpd_rules[<?php echo esc_attr($idx); ?>][dow]">
									<option value="" <?php selected(($rule['dow'] ?? ''), ''); ?>><?php esc_html_e('Any', 'dpd'); ?></option>
									<?php $dows = ['0'=>__('Sunday','dpd'),'1'=>__('Monday','dpd'),'2'=>__('Tuesday','dpd'),'3'=>__('Wednesday','dpd'),'4'=>__('Thursday','dpd'),'5'=>__('Friday','dpd'),'6'=>__('Saturday','dpd')]; foreach ($dows as $val=>$label) { echo '<option value="'.esc_attr($val).'"'. selected(($rule['dow'] ?? ''), $val, false) .'>'. esc_html($label) .'</option>'; } ?>
								</select>
							</td>
							<td><input type="date" name="dpd_rules[<?php echo esc_attr($idx); ?>][date_start]" value="<?php echo esc_attr($rule['date_start'] ?? ''); ?>" /></td>
							<td><input type="date" name="dpd_rules[<?php echo esc_attr($idx); ?>][date_end]" value="<?php echo esc_attr($rule['date_end'] ?? ''); ?>" /></td>
							<td>
								<select name="dpd_rules[<?php echo esc_attr($idx); ?>][type]">
									<option value="percent" <?php selected($rule['type'] ?? '', 'percent'); ?>><?php esc_html_e('Percent', 'dpd'); ?></option>
									<option value="fixed" <?php selected($rule['type'] ?? '', 'fixed'); ?>><?php esc_html_e('Fixed', 'dpd'); ?></option>
								</select>
							</td>
							<td>
								<select name="dpd_rules[<?php echo esc_attr($idx); ?>][direction]">
									<option value="increase" <?php selected($rule['direction'] ?? '', 'increase'); ?>><?php esc_html_e('Increase', 'dpd'); ?></option>
									<option value="decrease" <?php selected($rule['direction'] ?? '', 'decrease'); ?>><?php esc_html_e('Decrease', 'dpd'); ?></option>
								</select>
							</td>
							<td><input type="number" step="0.01" min="0" name="dpd_rules[<?php echo esc_attr($idx); ?>][amount]" value="<?php echo esc_attr($rule['amount'] ?? ''); ?>" /></td>
							<td><button type="button" class="button dpd-remove-row"><?php esc_html_e('Remove', 'dpd'); ?></button></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button button-secondary" id="dpd-add-row"><?php esc_html_e('Add Rule', 'dpd'); ?></button></p>
				<p><button type="submit" class="button button-primary" name="dpd_save_global" value="1"><?php esc_html_e('Save Changes', 'dpd'); ?></button></p>
			</form>
		</div>
		<?php
	}

	public static function render_diagnostics_page(): void {
		if (!current_user_can('manage_woocommerce')) { return; }
		
		$notice = '';
		
		// Handle debug toggle form
		if (isset($_POST['dpd_save_debug_settings']) && check_admin_referer('dpd_save_debug_settings_action', 'dpd_debug_nonce')) {
			$debug_enabled = isset($_POST['dpd_debug_enabled']) ? '1' : '0';
			update_option('dpd_debug_enabled', $debug_enabled);
			$notice = __('Debug settings saved.', 'dpd');
		}
		
		$debug_enabled = get_option('dpd_debug_enabled', false);
		
		// Test data
		$test_date = '2025-09-21'; // Sunday
		$test_time = '07:00';
		$test_datetime = $test_date . 'T' . $test_time;
		$test_product_id = 2118; // Use a real product ID
		
		// Test blackout dates
		$blackout_dates = DPD_Rules::get_blackout_dates();
		$is_test_date_blacked_out = DPD_Rules::is_date_blacked_out($test_date);
		
		// Get current context
		$current_context = DPD_Rules::today_context();
		
		// Test rule matching
		$global_rules = DPD_Rules::get_global_rules();
		$applicable_global_rules = DPD_Rules::filter_rules_for_apply($global_rules);
		
		// Debug rule data
		dpd_debug_log('DPD Diagnostics - All global rules: ' . print_r($global_rules, true));
		dpd_debug_log('DPD Diagnostics - Applicable rules: ' . print_r($applicable_global_rules, true));
		
		// Test specific date context - parse as site timezone
		$dt = date_create_immutable_from_format('Y-m-d\TH:i', $test_datetime, wp_timezone());
		$test_ts = $dt ? $dt->getTimestamp() : strtotime($test_datetime);
		$test_context = [
			'dow' => (int)wp_date('w', $test_ts),
			'date' => wp_date('Y-m-d', $test_ts)
		];
		
		// Test rule matching for specific date
		$matching_rules = [];
		foreach ($applicable_global_rules as $rule) {
			if (DPD_Rules::rule_matches($rule, $test_context['dow'], $test_context['date'])) {
				$matching_rules[] = $rule;
			}
		}
		
		// Test product pricing
		$test_product = wc_get_product($test_product_id);
		$original_price = $test_product ? $test_product->get_price() : 'N/A';
		
		// Test pricing logic
		$rule = DPD_Pricing::get_rule_for_context($test_product_id, $test_context);
		
		// Debug the rule data
		dpd_debug_log('DPD Diagnostics - Rule data: ' . print_r($rule, true));
		dpd_debug_log('DPD Diagnostics - Original price: ' . $original_price);
		dpd_debug_log('DPD Diagnostics - Test context: ' . print_r($test_context, true));
		
		$adjusted_price = $rule ? DPD_Rules::apply_rule_to_price(floatval($original_price), $rule) : floatval($original_price);
		
		?>
		<div class="wrap">
			<h1><?php esc_html_e('DPD Diagnostics', 'dpd'); ?></h1>
			<?php if ($notice): ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
			<?php endif; ?>
			
			<h2><?php esc_html_e('Debug Settings', 'dpd'); ?></h2>
			<p><?php esc_html_e('Control whether debug information is written to the debug.log file. When enabled, detailed logging will help troubleshoot issues but may create large log files.', 'dpd'); ?></p>
			<form method="post">
				<?php wp_nonce_field('dpd_save_debug_settings_action', 'dpd_debug_nonce'); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e('Debug Logging', 'dpd'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="dpd_debug_enabled" value="1" <?php checked($debug_enabled, '1'); ?> />
								<?php esc_html_e('Enable debug logging to debug.log', 'dpd'); ?>
							</label>
							<p class="description">
								<?php if ($debug_enabled): ?>
									<strong style="color: #d63638;"><?php esc_html_e('Debug logging is currently ENABLED', 'dpd'); ?></strong>
								<?php else: ?>
									<strong style="color: #00a32a;"><?php esc_html_e('Debug logging is currently DISABLED', 'dpd'); ?></strong>
								<?php endif; ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(__('Save Debug Settings', 'dpd'), 'primary', 'dpd_save_debug_settings'); ?>
			</form>
			
			<h2>System Information</h2>
			<table class="widefat">
				<tr><td><strong>Plugin Version:</strong></td><td><?php echo DPD_VERSION; ?></td></tr>
				<tr><td><strong>WordPress Version:</strong></td><td><?php echo get_bloginfo('version'); ?></td></tr>
				<tr><td><strong>WooCommerce Active:</strong></td><td><?php echo class_exists('WooCommerce') ? 'Yes' : 'No'; ?></td></tr>
				<tr><td><strong>Current Time:</strong></td><td><?php echo current_time('Y-m-d H:i:s'); ?></td></tr>
				<tr><td><strong>Current Context:</strong></td><td>DoW: <?php echo $current_context['dow']; ?>, Date: <?php echo $current_context['date']; ?></td></tr>
			</table>
			
			<h2>Test Configuration</h2>
			<table class="widefat">
				<tr><td><strong>Test Date:</strong></td><td><?php echo $test_date; ?> (Sunday)</td></tr>
				<tr><td><strong>Test Time:</strong></td><td><?php echo $test_time; ?></td></tr>
				<tr><td><strong>Test DateTime:</strong></td><td><?php echo $test_datetime; ?></td></tr>
				<tr><td><strong>Test Product ID:</strong></td><td><?php echo $test_product_id; ?></td></tr>
				<tr><td><strong>Test Context:</strong></td><td>DoW: <?php echo $test_context['dow']; ?>, Date: <?php echo $test_context['date']; ?></td></tr>
			</table>
			
			<h2>Global Rules</h2>
			<table class="widefat">
				<thead>
					<tr>
						<th>Enabled</th>
						<th>Day of Week</th>
						<th>Start Date</th>
						<th>End Date</th>
						<th>Type</th>
						<th>Direction</th>
						<th>Amount</th>
						<th>Matches Test Date?</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($global_rules as $rule): ?>
					<tr>
						<td><?php echo $rule['enabled'] ? 'Yes' : 'No'; ?></td>
						<td><?php echo $rule['dow'] ?: 'Any'; ?></td>
						<td><?php echo $rule['date_start'] ?: 'Any'; ?></td>
						<td><?php echo $rule['date_end'] ?: 'Any'; ?></td>
						<td><?php echo $rule['type']; ?></td>
						<td><?php echo $rule['direction']; ?></td>
						<td><?php echo $rule['amount']; ?></td>
						<td><?php echo DPD_Rules::rule_matches($rule, $test_context['dow'], $test_context['date']) ? 'YES' : 'No'; ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			
			<h2>Blackout Dates Test</h2>
			<table class="widefat">
				<tr><td><strong>Test Date:</strong></td><td><?php echo $test_date; ?></td></tr>
				<tr><td><strong>Is Blacked Out:</strong></td><td><?php echo $is_test_date_blacked_out ? 'YES' : 'No'; ?></td></tr>
				<tr><td><strong>Total Blackout Rules:</strong></td><td><?php echo count($blackout_dates); ?></td></tr>
			</table>
			
			<h3>Blackout Rules</h3>
			<table class="widefat">
				<thead>
					<tr>
						<th>Enabled</th>
						<th>Type</th>
						<th>Day of Week</th>
						<th>Start Date</th>
						<th>End Date</th>
						<th>Matches Test Date?</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($blackout_dates as $blackout): ?>
					<tr>
						<td><?php echo $blackout['enabled'] ? 'Yes' : 'No'; ?></td>
						<td><?php echo $blackout['type']; ?></td>
						<td><?php echo $blackout['dow'] ?? 'Any'; ?></td>
						<td><?php echo $blackout['date_start'] ?? 'Any'; ?></td>
						<td><?php echo $blackout['date_end'] ?? 'Any'; ?></td>
						<td>
							<?php 
							$matches = false;
							if ($blackout['enabled']) {
								if ($blackout['type'] === 'date_range') {
									$date_start = $blackout['date_start'] ?? '';
									$date_end = $blackout['date_end'] ?? '';
									$matches = (empty($date_start) || $test_date >= $date_start) && 
											  (empty($date_end) || $test_date <= $date_end);
								} else {
									$test_dow = (int)wp_date('w', strtotime($test_date));
									$dow = $blackout['dow'] ?? '';
									$matches = !empty($dow) && (int)$dow === $test_dow;
								}
							}
							echo $matches ? 'YES' : 'No'; 
							?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			
			<h2>Pricing Test</h2>
			<table class="widefat">
				<tr><td><strong>Test Product:</strong></td><td><?php echo $test_product ? $test_product->get_name() : 'Not found'; ?></td></tr>
				<tr><td><strong>Original Price:</strong></td><td><?php echo $original_price; ?></td></tr>
				<tr><td><strong>Matching Rule:</strong></td><td><?php echo $rule ? 'Yes' : 'No'; ?></td></tr>
				<?php if ($rule): ?>
				<tr><td><strong>Rule Details:</strong></td><td><?php echo $rule['type'] . ' ' . $rule['direction'] . ' by ' . $rule['amount']; ?></td></tr>
				<?php endif; ?>
				<tr><td><strong>Adjusted Price:</strong></td><td><?php echo $adjusted_price; ?></td></tr>
			</table>
			
			<h2>Blackout Date Testing</h2>
			<p>Test specific dates for blackout status:</p>
			<input type="date" id="test-blackout-date" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" />
			<button type="button" id="test-specific-blackout" class="button">Test This Date</button>
			<div id="blackout-test-result" style="margin-top: 10px; padding: 10px; background: #f0f0f0; display: none;"></div>
			
			<h3>Day of Week Reference</h3>
			<p>PHP Day of Week values:</p>
			<ul>
				<li>0 = Sunday</li>
				<li>1 = Monday</li>
				<li>2 = Tuesday</li>
				<li>3 = Wednesday</li>
				<li>4 = Thursday</li>
				<li>5 = Friday</li>
				<li>6 = Saturday</li>
			</ul>
			<p><strong>Test Date 2025-09-23:</strong> <?php 
				$test_ts = strtotime('2025-09-23');
				$test_dow = (int)wp_date('w', $test_ts);
				$day_names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
				echo $day_names[$test_dow] . ' (DOW: ' . $test_dow . ')';
			?></p>
			
			<h2>AJAX Test</h2>
			<p>Test the AJAX endpoints directly:</p>
			<button type="button" id="test-ajax" class="button">Test Price AJAX</button>
			<button type="button" id="test-blackout-ajax" class="button">Test Blackout AJAX</button>
			<div id="ajax-result" style="margin-top: 10px; padding: 10px; background: #f0f0f0; display: none;"></div>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			$('#test-specific-blackout').click(function() {
				var testDate = $('#test-blackout-date').val();
				$('#blackout-test-result').show().html('Testing date: ' + testDate + '...');
				
				// Calculate day of week
				var dateObj = new Date(testDate);
				var dow = dateObj.getDay(); // 0 = Sunday, 1 = Monday, etc.
				var dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
				
				$.post(ajaxurl, {
					action: 'dpd_check_blackout',
					nonce: '<?php echo wp_create_nonce('dpd_ajax_nonce'); ?>',
					date: testDate
				}, function(resp) {
					var result = '<strong>Test Date:</strong> ' + testDate + ' (' + dayNames[dow] + ', DOW: ' + dow + ')<br>';
					result += '<strong>Is Blacked Out:</strong> ' + (resp.data.is_blacked_out ? 'YES' : 'No') + '<br>';
					result += '<strong>Message:</strong> ' + resp.data.message + '<br>';
					result += '<pre>' + JSON.stringify(resp, null, 2) + '</pre>';
					$('#blackout-test-result').html(result);
				}).fail(function(xhr) {
					$('#blackout-test-result').html('<strong>Error:</strong> ' + xhr.responseText);
				});
			});
			
			$('#test-ajax').click(function() {
				$('#ajax-result').show().html('Testing price AJAX...');
				$.post(ajaxurl, {
					action: 'dpd_get_price',
					nonce: '<?php echo wp_create_nonce('dpd_ajax_nonce'); ?>',
					product_id: <?php echo $test_product_id; ?>,
					datetime: '<?php echo $test_datetime; ?>'
				}, function(resp) {
					$('#ajax-result').html('<pre>' + JSON.stringify(resp, null, 2) + '</pre>');
				}).fail(function(xhr) {
					$('#ajax-result').html('<strong>Error:</strong> ' + xhr.responseText);
				});
			});
			
			$('#test-blackout-ajax').click(function() {
				$('#ajax-result').show().html('Testing blackout AJAX...');
				$.post(ajaxurl, {
					action: 'dpd_check_blackout',
					nonce: '<?php echo wp_create_nonce('dpd_ajax_nonce'); ?>',
					date: '<?php echo $test_date; ?>'
				}, function(resp) {
					$('#ajax-result').html('<pre>' + JSON.stringify(resp, null, 2) + '</pre>');
				}).fail(function(xhr) {
					$('#ajax-result').html('<strong>Error:</strong> ' + xhr.responseText);
				});
			});
		});
		</script>
		<?php
	}

	public static function add_product_metabox(): void {
		add_meta_box(
			'dpd_product_rules',
			__('Dynamic Pricing by Date', 'dpd'),
			[__CLASS__, 'render_product_metabox'],
			'product',
			'side',
			'default'
		);
	}

	public static function render_product_metabox(WP_Post $post): void {
		wp_nonce_field('dpd_save_product_rules', 'dpd_product_nonce');
		// Provide product id for AJAX operations
		echo '<input type="hidden" id="dpd_admin_product_id" value="' . esc_attr($post->ID) . '" />';
		$rules = DPD_Rules::get_product_rules($post->ID);
		dpd_debug_log('DPD Metabox: Product ID ' . $post->ID . ' - Rules for display: ' . print_r($rules, true));
		if (empty($rules)) {
			$rules = [[
				'enabled' => '0', 'dow' => '', 'date_start' => '', 'date_end' => '', 'type' => 'percent', 'direction' => 'increase', 'amount' => '10',
			]];
			dpd_debug_log('DPD Metabox: Using default empty rule');
		}
		?>
		<div class="dpd-metabox">
			<p><?php esc_html_e('Per-product rules override global rules. Leave empty to use global.', 'dpd'); ?></p>
			<p><em><?php esc_html_e('Use the button below to save pricing rules without updating other product fields.', 'dpd'); ?></em></p>
			<!-- DEBUG: Rules count: <?php echo count($rules); ?> -->
			<input type="hidden" name="dpd_save_product_rules" value="1" />
			<table class="dpd-rules-table small">
				<thead>
					<tr>
						<th><?php esc_html_e('On', 'dpd'); ?></th>
						<th><?php esc_html_e('DoW', 'dpd'); ?></th>
						<th><?php esc_html_e('From', 'dpd'); ?></th>
						<th><?php esc_html_e('To', 'dpd'); ?></th>
					</tr>
				</thead>
				<tbody id="dpd-product-rules-body">
				<?php foreach ($rules as $idx => $rule): ?>
					<?php dpd_debug_log('DPD Metabox: Rendering rule ' . $idx . ' - ' . print_r($rule, true)); ?>
					<tr class="dpd-rule-row">
						<td><input type="checkbox" name="dpd_product_rules[<?php echo esc_attr($idx); ?>][enabled]" value="1" <?php checked($rule['enabled'] ?? '0','1'); ?> /> <!-- DEBUG: enabled=<?php echo $rule['enabled'] ?? '0'; ?> --></td>
						<td>
							<select name="dpd_product_rules[<?php echo esc_attr($idx); ?>][dow]">
								<option value="" <?php selected(($rule['dow'] ?? ''), ''); ?>><?php esc_html_e('Any', 'dpd'); ?></option>
								<?php $dows = ['0'=>__('Sun','dpd'),'1'=>__('Mon','dpd'),'2'=>__('Tue','dpd'),'3'=>__('Wed','dpd'),'4'=>__('Thu','dpd'),'5'=>__('Fri','dpd'),'6'=>__('Sat','dpd')]; foreach ($dows as $val=>$label) { echo '<option value="'.esc_attr($val).'"'. selected(($rule['dow'] ?? ''), $val, false) .'>'. esc_html($label) .'</option>'; } ?>
							</select>
						</td>
						<td><input type="date" name="dpd_product_rules[<?php echo esc_attr($idx); ?>][date_start]" value="<?php echo esc_attr($rule['date_start'] ?? ''); ?>" /></td>
						<td><input type="date" name="dpd_product_rules[<?php echo esc_attr($idx); ?>][date_end]" value="<?php echo esc_attr($rule['date_end'] ?? ''); ?>" /></td>
					</tr>
					<tr>
						<td colspan="4">
							<select name="dpd_product_rules[<?php echo esc_attr($idx); ?>][type]">
								<option value="percent" <?php selected($rule['type'] ?? '', 'percent'); ?>><?php esc_html_e('Percent', 'dpd'); ?></option>
								<option value="fixed" <?php selected($rule['type'] ?? '', 'fixed'); ?>><?php esc_html_e('Fixed', 'dpd'); ?></option>
							</select>
							<select name="dpd_product_rules[<?php echo esc_attr($idx); ?>][direction]">
								<option value="increase" <?php selected($rule['direction'] ?? '', 'increase'); ?>><?php esc_html_e('Increase', 'dpd'); ?></option>
								<option value="decrease" <?php selected($rule['direction'] ?? '', 'decrease'); ?>><?php esc_html_e('Decrease', 'dpd'); ?></option>
							</select>
							<input type="number" step="0.01" min="0" name="dpd_product_rules[<?php echo esc_attr($idx); ?>][amount]" value="<?php echo esc_attr($rule['amount'] ?? ''); ?>" /> <!-- DEBUG: amount=<?php echo $rule['amount'] ?? ''; ?> -->
							<button type="button" class="button dpd-remove-row"><?php esc_html_e('Remove', 'dpd'); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<button type="button" class="button button-secondary" id="dpd-add-product-row"><?php esc_html_e('Add Rule', 'dpd'); ?></button>
				<button type="button" class="button" id="dpd-delete-all-product-rules"><?php esc_html_e('Delete All Rules', 'dpd'); ?></button>
				<button type="submit" class="button" name="dpd_delete_all_product_rules" value="1"><?php esc_html_e('Delete All (Save)', 'dpd'); ?></button>
				<button type="submit" class="button button-primary" name="dpd_save_product_rules" value="1"><?php esc_html_e('Save Pricing Rules', 'dpd'); ?></button>
			</p>
		</div>
		<?php
	}

	public static function save_product_rules(int $post_id): void {
		if (!isset($_POST['dpd_product_nonce']) || !wp_verify_nonce($_POST['dpd_product_nonce'], 'dpd_save_product_rules')) { return; }
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return; }
		if (!current_user_can('edit_post', $post_id)) { return; }
		$rules = isset($_POST['dpd_product_rules']) && is_array($_POST['dpd_product_rules']) ? $_POST['dpd_product_rules'] : [];
		$clean = DPD_Rules::sanitize_rules_array($rules);
		if (!empty($clean)) { DPD_Rules::save_product_rules($post_id, $clean); }
		else { delete_post_meta($post_id, DPD_Rules::META_PRODUCT_RULES); }
	}

	public static function add_product_data_tab(array $tabs): array {
		$tabs['dpd_tab'] = [
			'label'    => __('Dynamic Pricing by Date', 'dpd'),
			'target'   => 'dpd_product_data',
			'class'    => ['show_if_simple','show_if_variable','show_if_grouped','show_if_external'],
			'priority' => 80,
		];
		return $tabs;
	}

	public static function render_product_data_panel(): void {
		echo '<div id="dpd_product_data" class="panel woocommerce_options_panel">';
		// Reuse the metabox UI inside the panel
		global $post;
		if ($post instanceof WP_Post) {
			self::render_product_metabox($post);
		}
		echo '</div>';
	}

	public static function save_product_rules_from_product_object(WC_Product $product): void {
		dpd_debug_log('DPD Product Save: Handler called for product ID ' . $product->get_id());
		
		// Only proceed if our nonce is present and valid
		if (!isset($_POST['dpd_product_nonce']) || !wp_verify_nonce($_POST['dpd_product_nonce'], 'dpd_save_product_rules')) { 
			dpd_debug_log('DPD Product Save: Nonce check failed or missing');
			return; 
		}
		
		dpd_debug_log('DPD Product Save: Nonce check passed');
		
		// If Delete All (Save) was clicked, delete and return
		if (isset($_POST['dpd_delete_all_product_rules'])) {
			dpd_debug_log('DPD Product Save: Delete All action detected');
			delete_post_meta($product->get_id(), DPD_Rules::META_PRODUCT_RULES);
			return;
		}
		
		$rules = isset($_POST['dpd_product_rules']) && is_array($_POST['dpd_product_rules']) ? $_POST['dpd_product_rules'] : [];
		dpd_debug_log('DPD Product Save: Raw rules posted: ' . print_r($rules, true));
		
		$clean = DPD_Rules::sanitize_rules_array($rules);
		dpd_debug_log('DPD Product Save: Cleaned rules: ' . print_r($clean, true));
		
		if (!empty($clean)) {
			DPD_Rules::save_product_rules($product->get_id(), $clean);
			dpd_debug_log('DPD Product Save: Rules saved successfully');
		} else {
			delete_post_meta($product->get_id(), DPD_Rules::META_PRODUCT_RULES);
			dpd_debug_log('DPD Product Save: No valid rules, deleted existing rules');
		}
	}

	public static function ajax_delete_all_product_rules(): void {
		if (!current_user_can('edit_products')) { wp_send_json_error(['message' => 'forbidden']); }
		$nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
		if (!$nonce || !wp_verify_nonce($nonce, 'dpd_save_product_rules')) { wp_send_json_error(['message' => 'bad_nonce']); }
		$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
		if ($product_id <= 0) { wp_send_json_error(['message' => 'bad_product']); }
		delete_post_meta($product_id, DPD_Rules::META_PRODUCT_RULES);
		wp_send_json_success(['message' => 'deleted']);
	}
}
