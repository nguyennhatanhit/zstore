<?php
/** GIỎ HÀNG  ($_SESSION['cart'] = [product_id => số_lượng]) */

/** Tổng số sản phẩm trong giỏ (hiện trên header) */
function cart_count()
{
    return isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
}

/** [CN 10] Thêm vào giỏ: cộng dồn số lượng, không vượt tồn kho */
function cart_add($productId, $qty = 1)
{
    $p = db_one("SELECT id, name, stock_quantity FROM products WHERE id = ? AND status = 'active'", [(int) $productId]);
    if (!$p) {
        return 'Sản phẩm không tồn tại.';
    }
    if ($p['stock_quantity'] <= 0) {
        return 'Sản phẩm "' . $p['name'] . '" đã hết hàng.';
    }
    $current = isset($_SESSION['cart'][$p['id']]) ? $_SESSION['cart'][$p['id']] : 0;
    $new = $current + max(1, (int) $qty);

    if ($new > $p['stock_quantity']) {
        $_SESSION['cart'][$p['id']] = (int) $p['stock_quantity'];
        return 'Chỉ còn ' . $p['stock_quantity'] . ' sản phẩm "' . $p['name'] . '", giỏ hàng đã có số lượng tối đa.';
    }
    $_SESSION['cart'][$p['id']] = $new;
    return null;
}

/** [CN 10] Xóa khỏi giỏ */
function cart_remove($productId)
{
    unset($_SESSION['cart'][(int) $productId]);
}

/** [CN 11] Đổi số lượng (<= 0 thì xóa, vượt tồn kho thì giảm về tối đa) */
function cart_update($productId, $qty)
{
    $productId = (int) $productId;
    $qty = (int) $qty;
    if ($qty <= 0) {
        cart_remove($productId);
        return null;
    }
    $stock = (int) db_value("SELECT stock_quantity FROM products WHERE id = ?", [$productId]);
    if ($qty > $stock) {
        $_SESSION['cart'][$productId] = $stock;
        if ($stock <= 0) {
            cart_remove($productId);
        }
        return "Một số sản phẩm chỉ còn $stock trong kho, đã điều chỉnh số lượng.";
    }
    $_SESSION['cart'][$productId] = $qty;
    return null;
}

function cart_clear()
{
    unset($_SESSION['cart'], $_SESSION['coupon']);
}

/** [CN 12] Chi tiết giỏ hàng lấy từ CSDL (giá luôn mới nhất) */
function cart_details()
{
    $result = ['items' => [], 'subtotal' => 0, 'saved' => 0, 'count' => 0];
    $cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
    if (!$cart) {
        return $result;
    }

    $ids = array_keys($cart);
    $rows = db_all(product_sql() . " WHERE p.id IN (" . placeholders($ids) . ")", $ids);

    foreach ($rows as $p) {
        // Sản phẩm bị ẩn hoặc hết hàng thì bỏ khỏi giỏ
        if ($p['status'] !== 'active' || $p['stock_quantity'] <= 0) {
            cart_remove($p['id']);
            continue;
        }
        $qty = min($cart[$p['id']], (int) $p['stock_quantity']);
        $_SESSION['cart'][$p['id']] = $qty;

        $p['sale_price'] = sale_price($p);
        $p['quantity']   = $qty;
        $p['line_total'] = $p['sale_price'] * $qty;

        $result['items'][]   = $p;
        $result['subtotal'] += $p['line_total'];
        $result['saved']    += ($p['price'] - $p['sale_price']) * $qty;
        $result['count']    += $qty;
    }
    return $result;
}

/** Phí giao hàng theo tạm tính */
function shipping_fee($subtotal)
{
    return ($subtotal <= 0 || $subtotal >= FREE_SHIPPING_FROM) ? 0 : SHIPPING_FEE;
}

/** Tổng tiền đầy đủ của giỏ: dùng ở trang giỏ hàng và thanh toán */
function cart_totals()
{
    $t = cart_details();
    $t['coupon'] = null;
    $t['discount'] = 0;
    $t['coupon_error'] = null;

    if (!empty($_SESSION['coupon']) && $t['items']) {
        list($promo, $error) = find_coupon($_SESSION['coupon'], $t);
        if ($error) {
            $t['coupon_error'] = $error;
            unset($_SESSION['coupon']);
        } else {
            $t['coupon'] = $promo;
            $t['discount'] = coupon_discount($promo, $t);
        }
    }
    $t['shipping'] = shipping_fee($t['subtotal']);
    $t['total'] = max(0, $t['subtotal'] - $t['discount']) + $t['shipping'];
    return $t;
}
