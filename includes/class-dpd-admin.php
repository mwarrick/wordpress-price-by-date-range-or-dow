<?php
if (!defined('ABSPATH')) {
	exit;
}

class DPD_Admin {
	public static function init(): void {
		add_action('admin_menu', [__CLASS__, 'add_menu']);
		add_action('add_meta_boxes', [__CLASS__, 'add_product_metabox']);
		add_action('save_post_product', [__CLASS__, 'save_product_rules']);
		add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
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
	}

	public static function enqueue_admin_assets($hook): void {
		if ($hook === 'woocommerce_page_dpd-settings' || $hook === 'post.php' || $hook === 'post-new.php') {
			wp_enqueue_style('dpd-admin', DPD_PLUGIN_URL . 'assets/dpd-admin.css', [], DPD_VERSION);
			wp_enqueue_script('dpd-admin', DPD_PLUGIN_URL . 'assets/dpd-admin.js', ['jquery'], DPD_VERSION, true);
		}
	}

	public static function render_settings_page(): void {
		if (!current_user_can('manage_woocommerce')) { return; }
		$notice = '';
		if (isset($_POST['dpd_save_global']) && check_admin_referer('dpd_save_global_action', 'dpd_nonce')) {
			$rules = isset($_POST['dpd_rules']) && is_array($_POST['dpd_rules']) ? $_POST['dpd_rules'] : [];
			$clean = DPD_Rules::sanitize_rules_array($rules);
			DPD_Rules::save_global_rules($clean);
			$notice = __('Saved.', 'dpd');
		}
		$rules = DPD_Rules::get_global_rules();
		?>
		<div class="wrap">
			<h1><?php esc_html_e('Dynamic Pricing by Date — Global Rules', 'dpd'); ?></h1>
			<?php if ($notice): ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
			<?php endif; ?>
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
									<option value="" <?php selected(($rule['dow'] ?? '') === ''); ?>><?php esc_html_e('Any', 'dpd'); ?></option>
									<?php $dows = ['0'=>__('Sunday','dpd'),'1'=>__('Monday','dpd'),'2'=>__('Tuesday','dpd'),'3'=>__('Wednesday','dpd'),'4'=>__('Thursday','dpd'),'5'=>__('Friday','dpd'),'6'=>__('Saturday','dpd')]; foreach ($dows as $val=>$label) { echo '<option value="'.esc_attr($val).'"'. selected(($rule['dow'] ?? '') === $val, true, false) .'>'. esc_html($label) .'</option>'; } ?>
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
		$rules = DPD_Rules::get_product_rules($post->ID);
		if (empty($rules)) {
			$rules = [[
				'enabled' => '0', 'dow' => '', 'date_start' => '', 'date_end' => '', 'type' => 'percent', 'direction' => 'increase', 'amount' => '10',
			]];
		}
		?>
		<div class="dpd-metabox">
			<p><?php esc_html_e('Per-product rules override global rules. Leave empty to use global.', 'dpd'); ?></p>
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
					<tr class="dpd-rule-row">
						<td><input type="checkbox" name="dpd_product_rules[<?php echo esc_attr($idx); ?>][enabled]" value="1" <?php checked($rule['enabled'] ?? '0','1'); ?> /></td>
						<td>
							<select name="dpd_product_rules[<?php echo esc_attr($idx); ?>][dow]">
								<option value="" <?php selected(($rule['dow'] ?? '') === ''); ?>><?php esc_html_e('Any', 'dpd'); ?></option>
								<?php $dows = ['0'=>__('Sun','dpd'),'1'=>__('Mon','dpd'),'2'=>__('Tue','dpd'),'3'=>__('Wed','dpd'),'4'=>__('Thu','dpd'),'5'=>__('Fri','dpd'),'6'=>__('Sat','dpd')]; foreach ($dows as $val=>$label) { echo '<option value="'.esc_attr($val).'"'. selected(($rule['dow'] ?? '') === $val, true, false) .'>'. esc_html($label) .'</option>'; } ?>
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
							<input type="number" step="0.01" min="0" name="dpd_product_rules[<?php echo esc_attr($idx); ?>][amount]" value="<?php echo esc_attr($rule['amount'] ?? ''); ?>" />
							<button type="button" class="button dpd-remove-row"><?php esc_html_e('Remove', 'dpd'); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="button" class="button button-secondary" id="dpd-add-product-row"><?php esc_html_e('Add Rule', 'dpd'); ?></button></p>
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
}
