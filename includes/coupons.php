<?php
/**
 * Coupons Management Functions
 */

/**
 * Get all coupons
 */
function get_all_coupons($active_only = false) {
    $coupons_data = read_json(__DIR__ . '/../data/coupons.json');
    $coupons = $coupons_data['coupons'] ?? [];

    if ($active_only) {
        $coupons = array_filter($coupons, fn($c) => $c['active']);
    }

    return $coupons;
}

/**
 * Get coupon by ID
 */
function get_coupon_by_id($coupon_id) {
    $coupons = get_all_coupons(false);

    foreach ($coupons as $coupon) {
        if ($coupon['id'] === $coupon_id) {
            return $coupon;
        }
    }

    return null;
}

/**
 * Get coupon by code
 */
function get_coupon_by_code($code) {
    $coupons = get_all_coupons(false);

    foreach ($coupons as $coupon) {
        if (strtoupper($coupon['code']) === strtoupper($code)) {
            return $coupon;
        }
    }

    return null;
}

/**
 * Create new coupon
 */
function create_coupon($coupon_data) {
    $file_path = __DIR__ . '/../data/coupons.json';
    $coupons_data = read_json($file_path);

    // Check if code already exists
    if (get_coupon_by_code($coupon_data['code'])) {
        return [
            'success' => false,
            'error' => 'Ya existe un cupón con ese código'
        ];
    }

    // Generate unique ID
    $coupon_id = 'coupon-' . uniqid('', true) . '-' . bin2hex(random_bytes(4));

    // Prepare coupon
    $new_coupon = [
        'id' => $coupon_id,
        'code' => strtoupper($coupon_data['code']),
        'type' => $coupon_data['type'], // percentage or fixed
        'value' => floatval($coupon_data['value']),
        'min_purchase' => floatval($coupon_data['min_purchase'] ?? 0),
        'max_uses' => intval($coupon_data['max_uses'] ?? 0),
        'uses_count' => 0,
        'one_per_user' => $coupon_data['one_per_user'] ?? false,
        'start_date' => $coupon_data['start_date'],
        'end_date' => $coupon_data['end_date'],
        'applicable_to' => $coupon_data['applicable_to'] ?? 'all', // all or specific
        'products' => $coupon_data['products'] ?? [],
        'not_combinable' => $coupon_data['not_combinable'] ?? false,
        'active' => $coupon_data['active'] ?? true,
        'created_by' => $_SESSION['username'] ?? 'admin',
        'created_at' => gmdate('Y-m-d\TH:i:s\Z')
    ];

    // Add to array
    $coupons_data['coupons'][] = $new_coupon;

    // Save
    if (write_json($file_path, $coupons_data)) {
        return [
            'success' => true,
            'coupon' => $new_coupon
        ];
    }

    return [
        'success' => false,
        'error' => 'Error al crear el cupón'
    ];
}

/**
 * Update coupon
 */
function update_coupon($coupon_id, $coupon_data) {
    $file_path = __DIR__ . '/../data/coupons.json';
    $coupons_data = read_json($file_path);

    $found = false;
    foreach ($coupons_data['coupons'] as &$coupon) {
        if ($coupon['id'] === $coupon_id) {
            // Check if changing code and if it already exists
            if (isset($coupon_data['code']) && $coupon_data['code'] !== $coupon['code']) {
                $existing = get_coupon_by_code($coupon_data['code']);
                if ($existing && $existing['id'] !== $coupon_id) {
                    return false; // Code already exists
                }
            }

            // Update fields
            if (isset($coupon_data['code'])) {
                $coupon['code'] = strtoupper($coupon_data['code']);
            }
            if (isset($coupon_data['type'])) {
                $coupon['type'] = $coupon_data['type'];
            }
            if (isset($coupon_data['value'])) {
                $coupon['value'] = floatval($coupon_data['value']);
            }
            if (isset($coupon_data['min_purchase'])) {
                $coupon['min_purchase'] = floatval($coupon_data['min_purchase']);
            }
            if (isset($coupon_data['max_uses'])) {
                $coupon['max_uses'] = intval($coupon_data['max_uses']);
            }
            if (isset($coupon_data['one_per_user'])) {
                $coupon['one_per_user'] = $coupon_data['one_per_user'];
            }
            if (isset($coupon_data['start_date'])) {
                $coupon['start_date'] = $coupon_data['start_date'];
            }
            if (isset($coupon_data['end_date'])) {
                $coupon['end_date'] = $coupon_data['end_date'];
            }
            if (isset($coupon_data['applicable_to'])) {
                $coupon['applicable_to'] = $coupon_data['applicable_to'];
            }
            if (isset($coupon_data['products'])) {
                $coupon['products'] = $coupon_data['products'];
            }
            if (isset($coupon_data['not_combinable'])) {
                $coupon['not_combinable'] = $coupon_data['not_combinable'];
            }
            if (isset($coupon_data['active'])) {
                $coupon['active'] = $coupon_data['active'];
            }

            $found = true;
            break;
        }
    }

    if (!$found) {
        return false;
    }

    return write_json($file_path, $coupons_data);
}

/**
 * Delete coupon
 */
function delete_coupon($coupon_id) {
    $file_path = __DIR__ . '/../data/coupons.json';
    $coupons_data = read_json($file_path);

    $coupons_data['coupons'] = array_filter($coupons_data['coupons'], function($c) use ($coupon_id) {
        return $c['id'] !== $coupon_id;
    });
    $coupons_data['coupons'] = array_values($coupons_data['coupons']);

    return write_json($file_path, $coupons_data);
}

/**
 * Increment coupon usage
 */
function increment_coupon_usage($coupon_id) {
    $file_path = __DIR__ . '/../data/coupons.json';
    $coupons_data = read_json($file_path);

    foreach ($coupons_data['coupons'] as &$coupon) {
        if ($coupon['id'] === $coupon_id) {
            $coupon['uses_count']++;
            break;
        }
    }

    return write_json($file_path, $coupons_data);
}
