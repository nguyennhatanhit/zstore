<?php
/** CHI TIẾT ĐƠN HÀNG (quản trị) */
require '../config.php';
require_admin();

$id = (int) get_str('id', '0');
$order = db_one("SELECT * FROM orders WHERE id = ?", [$id]);
if (!$order) {
    set_flash('error', 'Không tìm thấy đơn hàng.');
    redirect('admin/orders.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_str('action', '');

    if ($action === 'status') {
        $new = post_str('status');
        $note = post_str('note');
        $error = order_change_status($order, $new, $note);
        set_flash($error ? 'error' : 'success', $error ?: 'Đã chuyển đơn sang "' . order_statuses()[$new][0] . '".');
    }

    if ($action === 'payment') {
        $payment = db_one("SELECT * FROM payments WHERE order_id = ?", [$id]);
        $newStatus = post_str('payment_status');
        $allowed = ['unpaid' => ['paid'], 'paid' => ['refunded', 'unpaid'], 'refunded' => []];

        if (!$payment) {
            set_flash('error', 'Đơn hàng chưa có thông tin thanh toán.');
        } elseif ($newStatus !== $payment['status'] && !in_array($newStatus, $allowed[$payment['status']])) {
            set_flash('error', 'Không thể chuyển trạng thái thanh toán này.');
        } else {
            db_run("UPDATE payments SET status = ?, transaction_code = ?, note = ?, paid_at = ? WHERE id = ?", [
                $newStatus,
                post_str('transaction_code') ?: null,
                post_str('payment_note') ?: null,
                $newStatus === 'paid' ? ($payment['paid_at'] ?: db_now()) : ($newStatus === 'unpaid' ? null : $payment['paid_at']),
                $payment['id'],
            ]);
            set_flash('success', 'Đã cập nhật thông tin thanh toán.');
        }
    }
    redirect('admin/order_detail.php?id=' . $id);
}

$customer = db_one("SELECT * FROM users WHERE id = ?", [$order['user_id']]);
$items    = db_all("SELECT oi.*, p.sku, p.stock_quantity,
                           (SELECT image_url FROM product_images WHERE product_id = oi.product_id ORDER BY is_primary DESC LIMIT 1) AS image
                    FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?", [$id]);
$history  = db_all("SELECT h.*, u.full_name FROM order_status_history h LEFT JOIN users u ON u.id = h.changed_by
                    WHERE h.order_id = ? ORDER BY h.created_at DESC, h.id DESC", [$id]);
$payment  = db_one("SELECT * FROM payments WHERE order_id = ?", [$id]);
$promo    = $order['promotion_id'] ? db_one("SELECT code, name FROM promotions WHERE id = ?", [$order['promotion_id']]) : null;
$next     = order_next_statuses($order['status']);
$actionLabels = ['confirmed' => 'Xác nhận đơn', 'shipping' => 'Bắt đầu giao hàng', 'completed' => 'Giao thành công'];

$title = 'Đơn ' . $order['order_code'];
$breadcrumb = 'Chi tiết đơn hàng';
require 'layout/admin_header.php';
?>

<a href="<?= url('admin/orders.php') ?>" class="back-link"><i class="bi bi-arrow-left"></i> Quay lại danh sách</a>

<div class="page-head">
    <div>
        <h1 class="page-title">Đơn <?= e($order['order_code']) ?> <?= order_badge($order['status']) ?></h1>
        <p class="page-desc">Đặt lúc <?= format_date($order['created_at']) ?> · cập nhật <?= format_date($order['updated_at']) ?></p>
    </div>
</div>

<div class="detail-grid">
    <div class="stack">
        <section class="panel">
            <div class="panel-head"><h2 class="panel-title">Sản phẩm</h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Sản phẩm</th><th>Đơn giá</th><th class="num">SL</th><th>Thành tiền</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td>
                                <div class="cell-product">
                                    <img src="<?= img_url($it['image']) ?>" alt="" class="thumb" onerror="this.onerror=null;this.src='<?= img_url('') ?>'">
                                    <div><b><?= e($it['product_name']) ?></b>
                                        <div class="cell-sub"><?= $it['sku'] ? 'SKU ' . e($it['sku']) . ' · tồn kho ' . $it['stock_quantity'] : 'Sản phẩm đã bị xóa' ?></div></div>
                                </div>
                            </td>
                            <td><?= money($it['price']) ?></td>
                            <td class="num"><?= $it['quantity'] ?></td>
                            <td><b><?= money($it['price'] * $it['quantity']) ?></b></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="panel-body totals">
                <div class="summary-row"><span>Tạm tính</span><span><?= money($order['subtotal']) ?></span></div>
                <?php if ($order['discount'] > 0): ?>
                    <div class="summary-row text-success"><span>Giảm giá <?= $promo ? '(' . e($promo['code']) . ')' : '' ?></span><span>-<?= money($order['discount']) ?></span></div>
                <?php endif; ?>
                <div class="summary-row"><span>Phí giao hàng</span><span><?= $order['shipping_fee'] > 0 ? money($order['shipping_fee']) : 'Miễn phí' ?></span></div>
                <div class="summary-row summary-total"><span>Tổng cộng</span><b><?= money($order['total']) ?></b></div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2 class="panel-title">Lịch sử xử lý</h2></div>
            <div class="panel-body">
                <ul class="timeline">
                    <?php foreach ($history as $h): ?>
                        <li>
                            <?= order_badge($h['status']) ?>
                            <span class="text-muted"><?= format_date($h['created_at']) ?> · <?= e($h['full_name'] ?: 'Hệ thống') ?></span>
                            <?php if ($h['note']): ?><div><?= e($h['note']) ?></div><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    </div>

    <aside class="stack">
        <section class="panel">
            <div class="panel-head"><h2 class="panel-title">Xử lý đơn hàng</h2></div>
            <div class="panel-body">
                <?php if (!$next): ?>
                    <p class="text-muted">Đơn đã <?= $order['status'] === 'completed' ? 'hoàn thành' : 'bị hủy' ?>, không thể thay đổi.</p>
                    <?php if ($order['cancel_reason']): ?><div class="notice notice-danger">Lý do hủy: <?= e($order['cancel_reason']) ?></div><?php endif; ?>
                <?php endif; ?>

                <?php foreach ($next as $st): if ($st === 'cancelled') continue; ?>
                    <form method="post" class="form mb-12">
                        <input type="hidden" name="action" value="status">
                        <input type="hidden" name="status" value="<?= $st ?>">
                        <input name="note" class="input" placeholder="Ghi chú (không bắt buộc)">
                        <button class="btn btn-primary btn-block"><i class="bi bi-arrow-right-circle"></i> <?= $actionLabels[$st] ?></button>
                    </form>
                <?php endforeach; ?>

                <?php if (in_array('cancelled', $next)): ?>
                    <form method="post" class="form cancel-form" data-confirm="Hủy đơn hàng này? Tồn kho sẽ được hoàn lại.">
                        <input type="hidden" name="action" value="status">
                        <input type="hidden" name="status" value="cancelled">
                        <input name="note" class="input" placeholder="Lý do hủy (bắt buộc)" required>
                        <button class="btn btn-danger btn-block"><i class="bi bi-x-circle"></i> Hủy đơn hàng</button>
                    </form>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2 class="panel-title">Thanh toán</h2></div>
            <?php if ($payment): ?>
                <form method="post" class="panel-body form">
                    <input type="hidden" name="action" value="payment">
                    <dl class="info-list">
                        <dt>Phương thức</dt><dd><?= e(payment_methods()[$payment['method']]) ?></dd>
                        <dt>Số tiền</dt><dd><b><?= money($payment['amount']) ?></b></dd>
                        <dt>Trạng thái</dt><dd><?= payment_badge($payment['status']) ?></dd>
                        <?php if ($payment['paid_at']): ?><dt>Thanh toán lúc</dt><dd><?= format_date($payment['paid_at']) ?></dd><?php endif; ?>
                    </dl>
                    <div class="form-group">
                        <label for="payment_status">Cập nhật trạng thái</label>
                        <select id="payment_status" name="payment_status" class="select">
                            <option value="unpaid" <?= $payment['status'] === 'unpaid' ? 'selected' : '' ?>>Chưa thanh toán</option>
                            <option value="paid" <?= $payment['status'] === 'paid' ? 'selected' : '' ?>>Đã thanh toán</option>
                            <option value="refunded" <?= $payment['status'] === 'refunded' ? 'selected' : '' ?>>Đã hoàn tiền</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="transaction_code">Mã giao dịch</label>
                        <input id="transaction_code" name="transaction_code" class="input" value="<?= e($payment['transaction_code']) ?>" placeholder="VD: FT26320123456">
                    </div>
                    <div class="form-group">
                        <label for="payment_note">Ghi chú</label>
                        <input id="payment_note" name="payment_note" class="input" value="<?= e($payment['note']) ?>">
                    </div>
                    <button class="btn btn-soft btn-block">Lưu thanh toán</button>
                </form>
            <?php else: ?>
                <div class="panel-body text-muted">Chưa có thông tin thanh toán.</div>
            <?php endif; ?>
        </section>

        <section class="panel">
            <div class="panel-head"><h2 class="panel-title">Khách hàng &amp; giao hàng</h2></div>
            <div class="panel-body">
                <dl class="info-list">
                    <dt>Tài khoản</dt><dd><a href="<?= url('admin/customer_detail.php?id=' . $customer['id']) ?>" class="link"><?= e($customer['full_name']) ?></a><br><small><?= e($customer['email']) ?></small></dd>
                    <dt>Người nhận</dt><dd><?= e($order['receiver_name']) ?></dd>
                    <dt>Điện thoại</dt><dd><?= e($order['receiver_phone']) ?></dd>
                    <dt>Địa chỉ</dt><dd><?= e($order['shipping_address']) ?></dd>
                    <?php if ($order['note']): ?><dt>Ghi chú</dt><dd><?= e($order['note']) ?></dd><?php endif; ?>
                </dl>
            </div>
        </section>
    </aside>
</div>

<?php require 'layout/admin_footer.php'; ?>
