<?php
/** KIỂM TRA TƯƠNG THÍCH LINH KIỆN - [CN 13] */

/** Các vị trí linh kiện trong Build PC: mã danh mục (categories.code) => tên */
function build_slots()
{
    return [
        'cpu'       => 'CPU - Bộ vi xử lý',
        'mainboard' => 'Mainboard',
        'ram'       => 'RAM',
        'vga'       => 'Card đồ họa',
        'storage'   => 'Ổ cứng',
        'psu'       => 'Nguồn',
        'case'      => 'Vỏ case',
        'cooler'    => 'Tản nhiệt',
    ];
}

/** Thông số 1 sản phẩm dạng ['socket' => 'LGA1700', 'tdp' => '65', ...] */
function product_spec_map($productId)
{
    $rows = db_all("
        SELECT sd.code, ps.value
        FROM product_specs ps JOIN spec_definitions sd ON sd.id = ps.spec_id
        WHERE ps.product_id = ?", [$productId]);
    return array_column($rows, 'value', 'code');
}

function check_compatibility(array $build)
{
    $errors = [];
    $warnings = [];
    $specs = [];
    foreach ($build as $code => $productId) {
        $specs[$code] = product_spec_map($productId);
    }

    // 1. Luật trong bảng compatibility_rules
    $rules = db_all("SELECT * FROM compatibility_rules WHERE is_active = 1");
    foreach ($rules as $r) {
        if (!isset($specs[$r['category_a']][$r['spec_code_a']], $specs[$r['category_b']][$r['spec_code_b']])) {
            continue;
        }
        $a = trim($specs[$r['category_a']][$r['spec_code_a']]);
        $b = trim($specs[$r['category_b']][$r['spec_code_b']]);

        switch ($r['operator']) {
            case 'eq':  $ok = strcasecmp($a, $b) === 0; break;
            case 'lte': $ok = (float) $a <= (float) $b; break;
            case 'gte': $ok = (float) $a >= (float) $b; break;
            case 'in':  $ok = in_array(strtolower($a), array_map('trim', explode(',', strtolower($b)))); break;
            default:    $ok = true;
        }
        if (!$ok) {
            $errors[] = $r['message'] . " ($a ↔ $b)";
        }
    }

    // 2. Công suất nguồn
    $power = 0;
    if ($build) {
        $power = (float) (isset($specs['cpu']['tdp']) ? $specs['cpu']['tdp'] : 0)
               + (float) (isset($specs['vga']['tdp']) ? $specs['vga']['tdp'] : 0)
               + 100;
    }
    $watt = isset($specs['psu']['wattage']) ? (float) $specs['psu']['wattage'] : 0;
    if ($watt > 0) {
        if ($power > $watt) {
            $errors[] = "Nguồn {$watt}W không đủ, cấu hình cần khoảng {$power}W.";
        } elseif ($power > $watt * 0.8) {
            $warnings[] = "Cấu hình dùng khoảng {$power}W, gần mức tối đa của nguồn {$watt}W. Nên chọn nguồn dư 20–30%.";
        }
    }

    // 3. Thiếu linh kiện bắt buộc
    $slots = build_slots();
    foreach (['cpu', 'mainboard', 'ram', 'storage', 'psu'] as $code) {
        if (!isset($build[$code])) {
            $warnings[] = 'Chưa chọn ' . $slots[$code] . '.';
        }
    }
    if (isset($build['cpu']) && !isset($build['vga'])) {
        $warnings[] = 'Chưa chọn card đồ họa: chỉ dùng được nếu CPU có đồ họa tích hợp (CPU đuôi F không có).';
    }

    return ['ok' => !$errors, 'errors' => $errors, 'warnings' => $warnings, 'total_power' => $power, 'psu_watt' => $watt];
}

/** Dữ liệu hiển thị Build PC: sản phẩm từng vị trí, linh kiện đã chọn, tổng tiền, kết quả kiểm tra */
function build_view(array $build)
{
    $slots = build_slots();
    // Danh sách sản phẩm cho từng vị trí (còn hàng)
    $options = [];
    foreach ($slots as $code => $name) {
        $options[$code] = db_all(product_sql() . " WHERE c.code = ? AND p.status = 'active' AND p.stock_quantity > 0 ORDER BY p.price", [$code]);
    }

    // Sản phẩm đã chọn
    $selected = [];
    $total = 0;
    foreach ($build as $code => $productId) {
        $p = db_one(product_sql() . " WHERE p.id = ?", [$productId]);
        if ($p) {
            $p['sale_price'] = sale_price($p);
            $p['specs'] = product_spec_map($p['id']);
            $selected[$code] = $p;
            $total += $p['sale_price'];
        }
    }
    $compat = check_compatibility($build);
    // Thông số chính hiển thị dưới tên linh kiện
    $keySpecs = ['socket', 'ram_type', 'form_factor', 'capacity', 'tdp', 'wattage', 'vram', 'length', 'cores', 'speed'];

    return ['options' => $options, 'selected' => $selected, 'total' => $total, 'compat' => $compat, 'keySpecs' => $keySpecs];
}
