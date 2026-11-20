<?php
/** KHUYẾN MÃI / MÃ GIẢM GIÁ  ($_SESSION['coupon']) */

/** Điều kiện SQL của khuyến mãi đang hiệu lực (dùng kèm tham số db_now()) */
function promo_active_sql($alias = 'pr')
{
    return "$alias.status = 'active' AND ? BETWEEN $alias.start_date AND $alias.end_date
            AND ($alias.usage_limit IS NULL OR $alias.used_count < $alias.usage_limit)";
}

/** Khuyến mãi tự động (không có mã) đang chạy, gom theo sản phẩm: [product_id => [promo, ...]] */
function auto_promotions()
{
    static $map = null;
    if ($map === null) {
        $map = [];
        $rows = db_all("
            SELECT pp.product_id, pr.*
            FROM promotions pr JOIN promotion_products pp ON pp.promotion_id = pr.id
            WHERE pr.code IS NULL AND " . promo_active_sql(), [db_now()]);
        foreach ($rows as $r) {
            $map[$r['product_id']][] = $r;
        }
    }
    return $map;
}

/** Số tiền giảm của 1 khuyến mãi trên số tiền $amount */
function promo_amount($promo, $amount)
{
    if ($promo['discount_type'] === 'percent') {
        $d = $amount * $promo['discount_value'] / 100;
    } else {
        $d = $promo['discount_value'];
    }
    if ($promo['max_discount'] !== null && $promo['max_discount'] > 0) {
        $d = min($d, $promo['max_discount']);
    }
    return round(min($d, $amount));
}

/** Giá sau khuyến mãi tự động tốt nhất (không có thì trả về giá gốc) */
function sale_price($product)
{
    $best = $product['price'];
    $map = auto_promotions();
    if (isset($map[$product['id']])) {
        foreach ($map[$product['id']] as $promo) {
            $best = min($best, $product['price'] - promo_amount($promo, $product['price']));
        }
    }
    return $best;
}

/** Kiểm tra mã giảm giá với giỏ hàng hiện tại */
function find_coupon($code, $details)
{
    $promo = db_one("SELECT * FROM promotions pr WHERE pr.code = ? AND " . promo_active_sql(), [strtoupper(trim($code)), db_now()]);
    if (!$promo) {
        return [null, 'Mã giảm giá không tồn tại hoặc đã hết hạn.'];
    }
    if ($details['subtotal'] < $promo['min_order_value']) {
        return [null, 'Mã chỉ áp dụng cho đơn từ ' . money($promo['min_order_value']) . '.'];
    }
    if (coupon_discount($promo, $details) <= 0) {
        return [null, 'Mã không áp dụng cho sản phẩm trong giỏ.'];
    }
    return [$promo, null];
}

/** Tiền giảm của mã: nếu mã gắn sản phẩm cụ thể thì chỉ tính trên các sản phẩm đó */
function coupon_discount($promo, $details)
{
    $productIds = array_column(db_all("SELECT product_id FROM promotion_products WHERE promotion_id = ?", [$promo['id']]), 'product_id');
    $base = 0;
    foreach ($details['items'] as $item) {
        if (!$productIds || in_array($item['id'], $productIds)) {
            $base += $item['line_total'];
        }
    }
    return $base > 0 ? promo_amount($promo, $base) : 0;
}
