<?php
if (!defined('ABSPATH')) {
	exit;
}

class DPD_Rules {
	const OPTION_GLOBAL_RULES = 'dpd_global_rules';
	const META_PRODUCT_RULES  = '_dpd_product_rules';
	const OPTION_BLACKOUT_DATES = 'dpd_blackout_dates';

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

	// Blackout Date Management Methods
	public static function get_blackout_dates(): array {
		$blackouts = get_option(self::OPTION_BLACKOUT_DATES, []);
		dpd_debug_log('DPD Rules: Retrieved blackout dates: ' . print_r($blackouts, true));
		return is_array($blackouts) ? array_values($blackouts) : [];
	}

	public static function save_blackout_dates(array $blackouts): void {
		dpd_debug_log('DPD Rules: Saving blackout dates: ' . print_r($blackouts, true));
		update_option(self::OPTION_BLACKOUT_DATES, array_values($blackouts), false);
		dpd_debug_log('DPD Rules: Blackout dates saved successfully');
	}

	public static function sanitize_blackout_rule(array $blackout): array {
		$enabled = isset($blackout['enabled']) && (string)$blackout['enabled'] === '1' ? '1' : '0';
		$type = isset($blackout['type']) && in_array($blackout['type'], ['date_range', 'day_of_week'], true) ? $blackout['type'] : 'date_range';
		
		$sanitized = [
			'enabled' => $enabled,
			'type' => $type,
		];

		if ($type === 'date_range') {
			$date_start = isset($blackout['date_start']) ? sanitize_text_field($blackout['date_start']) : '';
			$date_end = isset($blackout['date_end']) ? sanitize_text_field($blackout['date_end']) : '';
			
			// Validate date ranges - prevent past dates
			$today = current_time('Y-m-d');
			if (!empty($date_start) && $date_start < $today) {
				$date_start = $today;
			}
			if (!empty($date_end) && $date_end < $today) {
				$date_end = '';
			}
			
			// Validate date range logic
			if (!empty($date_start) && !empty($date_end) && $date_start > $date_end) {
				$date_end = $date_start;
			}
			
			$sanitized['date_start'] = $date_start;
			$sanitized['date_end'] = $date_end;
			$sanitized['dow'] = ''; // Clear DOW for date range type
		} else {
			// Day of week blackout
			$dow = isset($blackout['dow']) ? trim((string)$blackout['dow']) : '';
			$sanitized['dow'] = ($dow === '' || $dow === 'any') ? '' : preg_replace('/[^0-6]/', '', $dow);
			$sanitized['date_start'] = ''; // Clear date fields for DOW type
			$sanitized['date_end'] = '';
		}

		return $sanitized;
	}

	public static function sanitize_blackout_array(array $blackouts): array {
		$clean = [];
		dpd_debug_log('DPD Blackout: Processing ' . count($blackouts) . ' blackout rules');
		
		foreach ($blackouts as $idx => $blackout) {
			dpd_debug_log('DPD Blackout: Processing rule ' . $idx . ': ' . print_r($blackout, true));
			
			if (!is_array($blackout)) { 
				dpd_debug_log('DPD Blackout: Rule ' . $idx . ' is not an array, skipping');
				continue; 
			}
			
			$b = self::sanitize_blackout_rule($blackout);
			dpd_debug_log('DPD Blackout: Sanitized rule ' . $idx . ': ' . print_r($b, true));
			
			// Drop fully-empty rows
			$allBlank = ($b['enabled'] !== '1');
			if ($b['type'] === 'date_range') {
				$allBlank = $allBlank && ($b['date_start'] === '') && ($b['date_end'] === '');
			} else {
				$allBlank = $allBlank && ($b['dow'] === '');
			}
			
			dpd_debug_log('DPD Blackout: Rule ' . $idx . ' is blank: ' . ($allBlank ? 'Yes' : 'No'));
			
			if ($allBlank) { 
				dpd_debug_log('DPD Blackout: Skipping blank rule ' . $idx);
				continue; 
			}
			
			$clean[] = $b;
			dpd_debug_log('DPD Blackout: Added rule ' . $idx . ' to clean array');
		}
		
		dpd_debug_log('DPD Blackout: Final clean rules count: ' . count($clean));
		return $clean;
	}

	public static function is_date_blacked_out(string $date, ?int $dow = null): bool {
		$blackouts = self::get_blackout_dates();
		$applicable_blackouts = [];
		
		dpd_debug_log('DPD Blackout Check: Checking date ' . $date . ' for blackouts');
		dpd_debug_log('DPD Blackout Check: Total blackout rules: ' . count($blackouts));
		
		// Filter for enabled blackouts only
		foreach ($blackouts as $blackout) {
			if (!is_array($blackout)) { continue; }
			$sb = self::sanitize_blackout_rule($blackout);
			dpd_debug_log('DPD Blackout Check: Sanitized blackout rule: ' . print_r($sb, true));
			if ($sb['enabled'] !== '1') { 
				dpd_debug_log('DPD Blackout Check: Rule disabled, skipping');
				continue; 
			}
			$applicable_blackouts[] = $sb;
		}
		
		dpd_debug_log('DPD Blackout Check: Applicable blackout rules: ' . count($applicable_blackouts));
		
		// If no DOW provided, calculate it
		if ($dow === null) {
			$ts = strtotime($date . ' 12:00:00'); // Use noon to avoid timezone issues
			$dow = (int)wp_date('w', $ts);
			dpd_debug_log('DPD Blackout Check: Date string: ' . $date . ', Timestamp: ' . $ts . ', wp_date result: ' . $dow);
		}
		
		$day_names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
		dpd_debug_log('DPD Blackout Check: Date ' . $date . ' is day of week: ' . $dow . ' (' . $day_names[$dow] . ')');
		dpd_debug_log('DPD Blackout Check: Raw timestamp: ' . $ts . ', wp_date result: ' . wp_date('w', $ts));
		
		foreach ($applicable_blackouts as $blackout) {
			dpd_debug_log('DPD Blackout Check: Checking rule type: ' . $blackout['type']);
			
			if ($blackout['type'] === 'date_range') {
				// Check if date falls within the range
				dpd_debug_log('DPD Blackout Check: Date range rule - start: ' . $blackout['date_start'] . ', end: ' . $blackout['date_end']);
				if (!empty($blackout['date_start']) && $date < $blackout['date_start']) {
					dpd_debug_log('DPD Blackout Check: Date before start range, continuing');
					continue;
				}
				if (!empty($blackout['date_end']) && $date > $blackout['date_end']) {
					dpd_debug_log('DPD Blackout Check: Date after end range, continuing');
					continue;
				}
				// Date is within range, so it's blacked out
				dpd_debug_log('DPD Blackout Check: Date is within range - BLACKED OUT');
				return true;
			} else {
				// Day of week blackout
				$rule_dow_name = !empty($blackout['dow']) ? $day_names[(int)$blackout['dow']] : 'Any';
				$test_dow_name = $day_names[$dow];
				dpd_debug_log('DPD Blackout Check: Day of week rule - Rule DOW: ' . $blackout['dow'] . ' (' . $rule_dow_name . '), checking against: ' . $dow . ' (' . $test_dow_name . ')');
				dpd_debug_log('DPD Blackout Check: Comparison: (int)' . $blackout['dow'] . ' === ' . $dow . ' = ' . ((int)$blackout['dow'] === $dow ? 'TRUE' : 'FALSE'));
				if (!empty($blackout['dow']) && (int)$blackout['dow'] === $dow) {
					dpd_debug_log('DPD Blackout Check: Day of week matches - BLACKED OUT');
					return true;
				} else {
					dpd_debug_log('DPD Blackout Check: Day of week does not match, continuing');
				}
			}
		}
		
		dpd_debug_log('DPD Blackout Check: Date is NOT blacked out');
		return false;
	}
}
