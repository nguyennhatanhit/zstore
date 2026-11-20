<?php
/** ĐƠN HÀNG: trạng thái, lịch sử, hoàn kho, đổi trạng thái (khách hủy + admin cập nhật) */

/** Danh sách trạng thái đơn: mã => [tên hiển thị, màu badge] */
function order_statuses()
{
    return [
        'pending'   => ['Chờ xác nhận', 'warning'],
        'confirmed' => ['Đã xác nhận',  'info'],
        'shipping'  => ['Đang giao',    'info'],
        'completed' => ['Hoàn thành',   'success'],
        'cancelled' => ['Đã hủy',       'danger'],
    ];
}

/** <?= order_badge($order['status']) ?> */
function order_badge($status)
{
    $list = order_statuses();
    $s = isset($list[$status]) ? $list[$status] : [$status, ''];
    return '<span class="badge badge-' . $s[1] . '">' . e($s[0]) . '</span>';
}

/** Trạng thái tiếp theo được phép chuyển tới */
function order_next_statuses($status)
{
    $flow = [
        'pending'   => ['confirmed', 'cancelled'],
        'confirmed' => ['shipping', 'cancelled'],
        'shipping'  => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];
    return isset($flow[$status]) ? $flow[$status] : [];
}

/** Ghi 1 dòng lịch sử trạng thái */
function order_add_history($orderId, $status, $note = null)
{
    $user = current_user();
    db_run(
        "INSERT INTO order_status_history (order_id, status, note, changed_by, created_at) VALUES (?, ?, ?, ?, ?)",
        [$orderId, $status, $note ?: null, $user ? $user['id'] : null, db_now()]
    );
}

/** Hoàn lại tồn kho khi hủy đơn */
function order_restock($order)
{
    $items = db_all("SELECT * FROM order_items WHERE order_id = ? AND product_id IS NOT NULL", [$order['id']]);
    $user = current_user();
    $userId = $user ? $user['id'] : null;
    foreach ($items as $item) {
        db_run("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?", [$item['quantity'], $item['product_id']]);
        db_run(
            "INSERT INTO inventory_logs (product_id, change_qty, type, note, created_by, created_at) VALUES (?, ?, 'cancel', ?, ?, ?)",
            [$item['product_id'], $item['quantity'], 'Hoàn kho do hủy đơn ' . $order['order_code'], $userId, db_now()]
        );
    }
}

/** Đổi trạng thái đơn hàng (đã kiểm tra luồng hợp lệ). Trả về null nếu thành công, hoặc thông báo lỗi. */
function order_change_status($order, $newStatus, $note = '')
{
    global $pdo;
    if (!is_string($newStatus) || !isset(order_statuses()[$newStatus])) {
        return 'Trạng thái không hợp lệ.';
    }
    if (!in_array($newStatus, order_next_statuses($order['status']))) {
        return 'Không thể chuyển đơn từ "' . order_statuses()[$order['status']][0] . '" sang "' . order_statuses()[$newStatus][0] . '".';
    }
    if ($newStatus === 'cancelled' && trim($note) === '') {
        return 'Vui lòng nhập lý do hủy đơn.';
    }
    // Đơn trả trước (QR / chuyển khoản) phải nhận được tiền rồi mới giao hàng
    if (in_array($newStatus, ['shipping', 'completed'])) {
        $pay = db_one("SELECT method, status FROM payments WHERE order_id = ?", [$order['id']]);
        if ($pay && $pay['method'] !== 'cod' && $pay['status'] !== 'paid') {
            return 'Đơn ' . payment_short($pay['method']) . ' chưa nhận được tiền. Hãy kiểm tra và cập nhật "Đã thanh toán" trước khi giao hàng.';
        }
    }

    try {
        $pdo->beginTransaction();

        db_run("UPDATE orders SET status = ?, cancel_reason = ?, updated_at = ? WHERE id = ?",
            [$newStatus, $newStatus === 'cancelled' ? $note : $order['cancel_reason'], db_now(), $order['id']]);

        if ($newStatus === 'cancelled') {
            order_restock($order);
            db_run("UPDATE payments SET status = 'refunded' WHERE order_id = ? AND status = 'paid'", [$order['id']]);
            // Trả lại lượt dùng mã giảm giá cho đơn bị hủy
            if (!empty($order['promotion_id'])) {
                db_run("UPDATE promotions SET used_count = used_count - 1 WHERE id = ? AND used_count > 0", [$order['promotion_id']]);
            }
        }
        if ($newStatus === 'completed') {
            db_run("UPDATE payments SET status = 'paid', paid_at = ? WHERE order_id = ? AND method = 'cod' AND status = 'unpaid'",
                [db_now(), $order['id']]);
        }
        order_add_history($order['id'], $newStatus, $note);

        $pdo->commit();

        // Hủy đơn PayOS chưa trả tiền → hủy luôn mã QR để khách không chuyển tiền nhầm
        if ($newStatus === 'cancelled' && !empty($order['payos_order_code']) && payos_enabled()) {
            payos_cancel_link($order['payos_order_code'], 'Don ' . $order['order_code'] . ' da huy');
        }
        return null;
    } catch (Exception $ex) {
        $pdo->rollBack();
        return 'Có lỗi khi cập nhật đơn hàng: ' . $ex->getMessage();
    }
}

/** Sinh mã đơn: TS + ngày + số thứ tự trong ngày, VD TS20261115003 */
function new_order_code()
{
    $prefix = 'TS' . date('Ymd');
    $n = (int) db_value("SELECT COUNT(*) FROM orders WHERE order_code LIKE ?", [$prefix . '%']);
    do {
        $n++;
        $code = $prefix . str_pad($n, 3, '0', STR_PAD_LEFT);
    } while (db_value("SELECT COUNT(*) FROM orders WHERE order_code = ?", [$code]));
    return $code;
}

/** Tự hủy đơn QR / chuyển khoản còn "Chờ xác nhận" mà quá UNPAID_ORDER_HOURS giờ chưa thanh toán: */
function order_auto_cancel_expired()
{
    $deadline = date('Y-m-d H:i:s', time() - UNPAID_ORDER_HOURS * 3600);
    $orders = db_all("
        SELECT o.* FROM orders o JOIN payments p ON p.order_id = o.id
        WHERE o.status = 'pending' AND p.status = 'unpaid' AND p.method IN ('bank_transfer', 'payos')
          AND o.created_at < ?
        ORDER BY o.id LIMIT 10", [$deadline]);
    foreach ($orders as $order) {
        order_change_status($order, 'cancelled', 'Hệ thống tự hủy: quá ' . UNPAID_ORDER_HOURS . ' giờ chưa thanh toán');
    }
}
