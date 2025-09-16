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
		return is_array($rules) ? array_values($rules) : [];
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
			$clean[] = $sr;
		}
		return $clean;
	}

	public static function today_context(): array {
		$ts = current_time('timestamp');
		return [
			'dow'  => (int)gmdate('w', $ts),
			'date' => gmdate('Y-m-d', $ts),
		];
	}

	public static function rule_matches(array $rule, int $dow, string $date): bool {
		if (!empty($rule['dow'])) { if ((int)$rule['dow'] !== $dow) { return false; } }
		if (!empty($rule['date_start'])) { if ($date < $rule['date_start']) { return false; } }
		if (!empty($rule['date_end'])) { if ($date > $rule['date_end']) { return false; } }
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
		$delta     = ($type === 'percent') ? ($price * ($amount / 100.0)) : $amount;
		$adjusted  = ($direction === 'decrease') ? ($price - $delta) : ($price + $delta);
		$adjusted  = max(0.0, $adjusted);
		$decimals  = wc_get_price_decimals();
		return round($adjusted, $decimals);
	}
}
