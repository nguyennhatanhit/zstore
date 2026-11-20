<?php
/** PayOS đưa khách về đây sau khi thanh toán hoặc bấm hủy. */
require __DIR__ . '/config.php';
require_login();

// Mã đơn của web nằm ở tham số "order". PayOS tự gắn thêm: code=00, id, cancel, status, orderCode
$code  = get_str('order');
$order = $code !== '' ? db_one(
    'SELECT * FROM orders WHERE order_code = ? AND user_id = ?',
    [$code, current_user()['id']]
) : null;
if (!$order && get_str('orderCode') !== '') {
    $order = db_one(
        'SELECT * FROM orders WHERE payos_order_code = ? AND user_id = ?',
        [(int) get_str('orderCode'), current_user()['id']]
    );
}
if (!$order) {
    set_flash('error', 'Không tìm thấy đơn hàng.');
    redirect('orders.php');
}
$payment = db_one('SELECT * FROM payments WHERE order_id = ?', [$order['id']]);

if (!$payment || $payment['method'] !== 'payos') {
    redirect('order.php?code=' . urlencode($order['order_code']));
}

if ($payment['status'] === 'paid') {
    set_flash('success', 'Đơn hàng đã được thanh toán.');
} elseif (!empty($order['payos_order_code'])) {
    $res = payos_get_status($order['payos_order_code']);
    $status = isset($res['data']['status']) ? $res['data']['status'] : '';

    if (isset($res['error'])) {
        set_flash('warning', 'Chưa kiểm tra được thanh toán: ' . $res['error']);
    } elseif ($status === 'PAID') {
        $ref = isset($res['data']['transactions'][0]['reference']) ? $res['data']['transactions'][0]['reference'] : null;
        if (payos_mark_paid($order, $payment, (int) $res['data']['amountPaid'], $ref)) {
            set_flash('success', 'Thanh toán thành công! Cảm ơn bạn đã mua hàng.');
        } else {
            set_flash('error', 'Số tiền nhận được chưa khớp với đơn hàng. Cửa hàng sẽ kiểm tra và liên hệ bạn.');
        }
    } elseif (get_str('tz_cancel') !== '' || $status === 'CANCELLED' || $status === 'EXPIRED') {
        set_flash('warning', 'Bạn chưa hoàn tất thanh toán. Đơn hàng vẫn được giữ, bấm "Tiếp tục thanh toán" để trả lại.');
    } else {
        set_flash('warning', 'Chưa nhận được tiền. Nếu đã chuyển khoản, đợi ít phút rồi bấm "Tôi đã thanh toán, kiểm tra lại".');
    }
}
redirect('order.php?code=' . urlencode($order['order_code']));
