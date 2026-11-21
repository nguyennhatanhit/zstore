<?php
/**
 * GIỎ HÀNG AJAX - nhận POST từ JavaScript, trả JSON (trang không tải lại)
 *   [CN 10] Thêm, xóa sản phẩm   [CN 11] Đổi số lượng   [CN 12] Tính lại tổng tiền, áp mã giảm giá
 */
define('SKIP_CSRF', true);
require '../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Chỉ nhận POST'], 405);
}
if (!csrf_valid()) {
    json_response(['ok' => false, 'message' => 'Phiên làm việc đã hết hạn, hãy tải lại trang.'], 403);
}

$action = post_str('action', 'add');
$id     = (int) post_str('product_id', '0');
$error  = 'Thao tác không hợp lệ.';

if ($action === 'add') {
    $error = cart_add($id, post_str('quantity', '1'));
} elseif ($action === 'update') {
    $error = cart_update($id, post_str('quantity', '0'));
} elseif ($action === 'remove') {
    cart_remove($id);
    $error = null;
} elseif ($action === 'apply_coupon') {
    $code = strtoupper(post_str('coupon'));
    list($promo, $error) = find_coupon($code, cart_details());
    if (!$error) {
        $_SESSION['coupon'] = $code;
    }
} elseif ($action === 'remove_coupon') {
    unset($_SESSION['coupon']);
    $error = null;
}

$cart = cart_totals();
$line = 0;
foreach ($cart['items'] as $item) {
    if ($item['id'] == $id) {
        $line = $item['line_total'];
    }
}
ob_start();
require __DIR__ . '/../layout/cart_summary.php';
$summary = ob_get_clean();
ob_start();
require __DIR__ . '/../layout/cart_coupon.php';
$couponHtml = csrf_inject(ob_get_clean());

$messages = [
    'add'           => 'Đã thêm sản phẩm vào giỏ hàng.',
    'update'        => 'Đã cập nhật giỏ hàng.',
    'remove'        => 'Đã xóa sản phẩm khỏi giỏ hàng.',
    'apply_coupon'  => 'Đã áp dụng mã ' . ($code ?? '') . '.',
    'remove_coupon' => 'Đã bỏ mã giảm giá.',
];
json_response([
    'ok'           => $error === null,
    'message'      => $error ?: $messages[$action],
    'cart_count'   => cart_count(),
    'item_count'   => $cart['count'],
    'quantity'     => $_SESSION['cart'][$id] ?? 0,
    'line_total'   => money($line),
    'summary_html' => $summary,
    'coupon_html'  => $couponHtml,
    'coupon_error' => $cart['coupon_error'],
], $error === null ? 200 : 422);
