<?php
if (!defined('ABSPATH')) {
	exit;
}

class DPD_Rules {
	const OPTION_GLOBAL_RULES = 'dpd_global_rules';
	const META_PRODUCT_RULES  = '_dpd_product_rules';

	public static function get_global_rules(): array {
		$rules = get_option(self::OPTION_GLOBAL_RULES, []);
		return is_array($rules) ? array_values($rules) : [];
	}

	public static function save_global_rules(array $rules): void {
		update_option(self::OPTION_GLOBAL_RULES, array_values($rules), false);
	}

	public static function get_product_rules(int $product_id): array {
		$rules = get_post_meta($product_id, self::META_PRODUCT_RULES, true);
		dpd_debug_log('DPD Rules Load: Product ID ' . $product_id . ' - Raw meta: ' . print_r($rules, true));
		$result = is_array($rules) ? array_values($rules) : [];
		dpd_debug_log('DPD Rules Load: Product ID ' . $product_id . ' - Processed rules: ' . print_r($result, true));
		return $result;
	}

	public static function save_product_rules(int $product_id, array $rules): void {
		update_post_meta($product_id, self::META_PRODUCT_RULES, array_values($rules));
	}

	public static function sanitize_rule(array $rule): array {
		$enabled    = isset($rule['enabled']) && (string)$rule['enabled'] === '1' ? '1' : '0';
		$dow_raw    = isset($rule['dow']) ? trim((string)$rule['dow']) : '';
		$dow        = ($dow_raw === '' || $dow_raw === 'any') ? '' : preg_replace('/[^0-6]/', '', $dow_raw);
		$date_start = isset($rule['date_start']) ? sanitize_text_field($rule['date_start']) : '';
		$date_end   = isset($rule['date_end']) ? sanitize_text_field($rule['date_end']) : '';
		$type       = isset($rule['type']) && in_array($rule['type'], ['percent','fixed'], true) ? $rule['type'] : 'percent';
		$direction  = isset($rule['direction']) && in_array($rule['direction'], ['increase','decrease'], true) ? $rule['direction'] : 'increase';
		$amount     = isset($rule['amount']) ? wc_clean($rule['amount']) : '0';

		// Validate date ranges - prevent past dates
		$today = current_time('Y-m-d');
		if (!empty($date_start) && $date_start < $today) {
			$date_start = $today; // Set to today if past date
		}
		if (!empty($date_end) && $date_end < $today) {
			$date_end = ''; // Clear end date if it's in the past
		}
		
		// Validate date range logic - start should not be after end
		if (!empty($date_start) && !empty($date_end) && $date_start > $date_end) {
			$date_end = $date_start; // Set end date to start date if invalid range
		}

		return [
			'enabled'    => $enabled,
			'dow'        => $dow,
			'date_start' => $date_start,
			'date_end'   => $date_end,
			'type'       => $type,
			'direction'  => $direction,
			'amount'     => $amount,
		];
	}

	public static function sanitize_rules_array(array $rules): array {
		$clean = [];
		foreach ($rules as $rule) {
			if (!is_array($rule)) { continue; }
			$r = self::sanitize_rule($rule);
			// Drop fully-empty rows (all fields blank/zero and not enabled)
			$allBlank = ($r['enabled'] !== '1')
				&& ($r['dow'] === '')
				&& ($r['date_start'] === '')
				&& ($r['date_end'] === '')
				&& ($r['amount'] === '0' || $r['amount'] === '');
			if ($allBlank) { continue; }
			$clean[] = $r;
		}
		return $clean;
	}

	public static function filter_rules_for_apply(array $rules): array {
		// At runtime, only enabled rules with valid amounts are considered
		$applicable = [];
		foreach ($rules as $rule) {
			if (!is_array($rule)) { continue; }
			$sr = self::sanitize_rule($rule);
			if ($sr['enabled'] !== '1') { continue; }
			if ($sr['type'] === 'percent') {
				$val = floatval($sr['amount']);
				if ($val <= 0) { continue; }
				$sr['amount'] = (string)$val;
			} else {
				$val = wc_format_decimal($sr['amount']);
				if (floatval($val) <= 0) { continue; }
				$sr['amount'] = $val;
			}
			$applicable[] = $sr;
		}
		return $applicable;
	}

	public static function today_context(): array {
		$ts = current_time('timestamp');
		return [
			'dow'  => (int)wp_date('w', $ts),
			'date' => wp_date('Y-m-d', $ts),
		];
	}

	public static function rule_matches(array $rule, int $dow, string $date): bool {
		if (($rule['enabled'] ?? '0') !== '1') { 
			dpd_debug_log('DPD Rule Match: Rule disabled');
			return false; 
		}
		if (isset($rule['dow']) && $rule['dow'] !== '') { 
			$rule_dow = (int)$rule['dow'];
			dpd_debug_log('DPD Rule Match: Checking DoW - Rule DoW: ' . $rule_dow . ' (type: ' . gettype($rule_dow) . '), Context DoW: ' . $dow . ' (type: ' . gettype($dow) . ')');
			if ($rule_dow !== $dow) { 
				dpd_debug_log('DPD Rule Match: DoW mismatch - returning false');
				return false; 
			}
			dpd_debug_log('DPD Rule Match: DoW match - continuing');
		}
		if (!empty($rule['date_start'])) { 
			dpd_debug_log('DPD Rule Match: Checking date_start - Date: ' . $date . ', Start: ' . $rule['date_start']);
			if ($date < $rule['date_start']) { 
				dpd_debug_log('DPD Rule Match: Date before start - returning false');
				return false; 
			}
		}
		if (!empty($rule['date_end'])) { 
			dpd_debug_log('DPD Rule Match: Checking date_end - Date: ' . $date . ', End: ' . $rule['date_end']);
			if ($date > $rule['date_end']) { 
				dpd_debug_log('DPD Rule Match: Date after end - returning false');
				return false; 
			}
		}
		dpd_debug_log('DPD Rule Match: All checks passed - returning true');
		return true;
	}

	public static function pick_applicable_rule(array $rules): ?array {
		$ctx = self::today_context();
		$match = null;
		foreach ($rules as $rule) {
			if (self::rule_matches($rule, $ctx['dow'], $ctx['date'])) { $match = $rule; }
		}
		return $match;
	}

	public static function apply_rule_to_price(float $price, array $rule): float {
		$amount    = floatval($rule['amount']);
		$direction = $rule['direction'];
		$type      = $rule['type'];
		
		if ($type === 'percent') {
			// For percent: amount is the percentage change (e.g., 10 = 10% increase, -10 = 10% decrease)
			if ($direction === 'increase') {
				$adjusted = $price * (1 + ($amount / 100.0));
			} else {
				$adjusted = $price * (1 - ($amount / 100.0));
			}
		} else {
			// For fixed: amount is added/subtracted from original price
			$adjusted = ($direction === 'decrease') ? ($price - $amount) : ($price + $amount);
		}
		
		$adjusted = max(0.0, $adjusted);
		$decimals = wc_get_price_decimals();
		
		// Debug logging
		dpd_debug_log("DPD Price Calculation: Original=$price, Amount=$amount, Type=$type, Direction=$direction, Adjusted=$adjusted");
		
		return round($adjusted, $decimals);
	}
}
