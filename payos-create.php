<?php
/** Tạo/mở lại Hosted Checkout PayOS cho đơn hàng của khách đang đăng nhập. */
require __DIR__ . '/config.php';
require_login();

$code = get_str('code');
$order = db_one(
    'SELECT * FROM orders WHERE order_code = ? AND user_id = ?',
    [$code, current_user()['id']]
);

if (!$order) {
    set_flash('error', 'Không tìm thấy đơn hàng.');
    redirect('orders.php');
}

$payment = db_one('SELECT * FROM payments WHERE order_id = ?', [$order['id']]);
if (!$payment || $payment['method'] !== 'payos') {
    set_flash('error', 'Đơn hàng không sử dụng phương thức PayOS.');
    redirect('order.php?code=' . urlencode($code));
}

if ($payment['status'] === 'paid') {
    set_flash('success', 'Đơn hàng đã được thanh toán.');
    redirect('order.php?code=' . urlencode($code));
}

$result = payos_checkout_for_order($order, $payment);
if (!empty($result['paid'])) {
    redirect('payos-return.php?order=' . urlencode($code));
}
if (!empty($result['url'])) {
    header('Location: ' . $result['url'], true, 303);
    exit;
}

set_flash('error', isset($result['error']) ? $result['error'] : 'Không thể tạo thanh toán PayOS.');
redirect('order.php?code=' . urlencode($code));
