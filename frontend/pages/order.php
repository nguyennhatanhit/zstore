<?php
/**
 * CHI TIẾT ĐƠN HÀNG
 *   [CN 16] Theo dõi trạng thái đơn hàng (thanh tiến trình + lịch sử)
 */
require '../../config.php';
require_login();

$code = get_str('code', '');
// Luôn kèm user_id: khách chỉ xem được đơn của chính mình
$order = db_one("SELECT * FROM orders WHERE order_code = ? AND user_id = ?", [$code, current_user()['id']]);

if (!$order) {
    set_flash('error', 'Không tìm thấy đơn hàng.');
    redirect('orders.php');
}

// Khách hủy đơn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'cancel') {
    $paidStatus = db_value("SELECT status FROM payments WHERE order_id = ?", [$order['id']]);
    if ($order['status'] !== 'pending') {
        set_flash('error', 'Chỉ hủy được đơn đang chờ xác nhận. Vui lòng liên hệ cửa hàng.');
    } elseif ($paidStatus === 'paid') {
        // Đã trả tiền: phải để cửa hàng hủy và hoàn tiền thật, không cho khách tự hủy
        set_flash('error', 'Đơn hàng đã được thanh toán nên không thể tự hủy. Vui lòng liên hệ cửa hàng để hủy và hoàn tiền.');
    } else {
        $reason = post_str('reason') ?: 'Khách không nêu lý do';
        $error = order_change_status($order, 'cancelled', 'Khách hủy: ' . $reason);
        set_flash($error ? 'error' : 'success', $error ?: 'Đã hủy đơn hàng ' . $order['order_code'] . '.');
    }
    redirect('order.php?code=' . $order['order_code']);
}

$items   = db_all("SELECT oi.*, (SELECT image_url FROM product_images WHERE product_id = oi.product_id ORDER BY is_primary DESC LIMIT 1) AS image
                   FROM order_items oi WHERE oi.order_id = ?", [$order['id']]);
$history = db_all("SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at, id", [$order['id']]);
$payment = db_one("SELECT * FROM payments WHERE order_id = ?", [$order['id']]);
$promo   = $order['promotion_id'] ? db_one("SELECT code, name FROM promotions WHERE id = ?", [$order['promotion_id']]) : null;

// Thanh tiến trình
$steps = ['pending' => 'Đặt hàng', 'confirmed' => 'Đã xác nhận', 'shipping' => 'Đang giao', 'completed' => 'Hoàn thành'];
$reached = array_column($history, 'created_at', 'status');
$stepKeys = array_keys($steps);
$currentIndex = array_search($order['status'], $stepKeys);

$title = 'Đơn hàng ' . $order['order_code'];
require '../layout/header.php';
?>

<section class="container section">
    <nav class="breadcrumbs">
        <a href="<?= url('orders.php') ?>"><i class="bi bi-arrow-left"></i> Đơn hàng của tôi</a>
    </nav>

    <div class="page-heading page-heading-row">
        <div>
            <h1>Đơn hàng <?= e($order['order_code']) ?></h1>
            <p class="text-muted">Đặt lúc <?= format_date($order['created_at']) ?></p>
        </div>
        <?= order_badge($order['status']) ?>
    </div>

    <div class="card">
        <?php if ($order['status'] === 'cancelled'): ?>
            <div class="notice notice-danger"><i class="bi bi-x-circle"></i> Đơn hàng đã bị hủy. Lý do: <?= e($order['cancel_reason']) ?></div>
        <?php else: ?>
            <ol class="steps">
                <?php foreach ($stepKeys as $i => $key): ?>
                    <li class="<?= $i <= $currentIndex ? 'done' : '' ?> <?= $i === $currentIndex ? 'current' : '' ?>">
                        <span class="step-dot"><i class="bi bi-check-lg"></i></span>
                        <b><?= $steps[$key] ?></b>
                        <small><?= isset($reached[$key]) ? format_date($reached[$key]) : '' ?></small>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>

    <div class="cart-layout mt-20">
        <div class="stack">
            <section class="card no-pad">
                <h2 class="card-title pad">Sản phẩm</h2>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Sản phẩm</th><th>Đơn giá</th><th>SL</th><th>Thành tiền</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td>
                                    <div class="cart-product">
                                        <img src="<?= img_url($it['image']) ?>" alt="" onerror="this.onerror=null;this.src='<?= img_url('') ?>'">
                                        <?php if ($it['product_id']): ?>
                                            <a href="<?= url('product.php?id=' . $it['product_id']) ?>"><?= e($it['product_name']) ?></a>
                                        <?php else: ?>
                                            <span><?= e($it['product_name']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?= money($it['price']) ?></td>
                                <td><?= $it['quantity'] ?></td>
                                <td><b><?= money($it['price'] * $it['quantity']) ?></b></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card">
                <h2 class="card-title">Lịch sử đơn hàng</h2>
                <ul class="timeline">
                    <?php foreach (array_reverse($history) as $h): ?>
                        <li>
                            <?= order_badge($h['status']) ?>
                            <span class="text-muted"><?= format_date($h['created_at']) ?></span>
                            <?php if ($h['note']): ?><div><?= e($h['note']) ?></div><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </div>

        <aside class="stack">
            <section class="card summary">
                <h2 class="card-title">Thanh toán</h2>
                <div class="summary-row"><span>Tạm tính</span><span><?= money($order['subtotal']) ?></span></div>
                <?php if ($order['discount'] > 0): ?>
                    <div class="summary-row text-success"><span>Giảm giá <?= $promo ? '(' . e($promo['code']) . ')' : '' ?></span><span>-<?= money($order['discount']) ?></span></div>
                <?php endif; ?>
                <div class="summary-row"><span>Phí giao hàng</span><span><?= $order['shipping_fee'] > 0 ? money($order['shipping_fee']) : 'Miễn phí' ?></span></div>
                <div class="summary-row summary-total"><span>Tổng cộng</span><b><?= money($order['total']) ?></b></div>
                <?php if ($payment): ?>
                    <div class="summary-row"><span>Phương thức</span><span><?= e(payment_methods()[$payment['method']]) ?></span></div>
                    <div class="summary-row"><span>Trạng thái</span><?= payment_badge($payment['status']) ?></div>

                    <?php if ($payment['method'] === 'payos' && $payment['status'] === 'unpaid' && $order['status'] !== 'cancelled'): ?>
                        <div class="bank-box">
                            <b>Thanh toán qua PayOS</b>
                            <p>Đơn hàng chưa được xác nhận thanh toán.</p>
                            <a class="btn btn-primary btn-block" href="<?= url('payos-create.php?code=' . urlencode($order['order_code'])) ?>">
                                <i class="bi bi-qr-code"></i> Tiếp tục thanh toán
                            </a>
                            <?php if ($order['payos_order_code']): ?>
                                <a class="btn btn-outline btn-block" href="<?= url('payos-return.php?order=' . urlencode($order['order_code'])) ?>">
                                    Tôi đã thanh toán, kiểm tra lại
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($payment['method'] === 'bank_transfer' && $payment['status'] === 'unpaid' && $order['status'] !== 'cancelled'): ?>
                        <div class="bank-box">
                            <b>Thông tin chuyển khoản</b>
                            <div>Ngân hàng: <b><?= BANK_NAME ?></b></div>
                            <div>Số tài khoản: <b><?= BANK_ACCOUNT ?></b></div>
                            <div>Chủ tài khoản: <b><?= BANK_OWNER ?></b></div>
                            <div>Số tiền: <b><?= money($payment['amount']) ?></b></div>
                            <div>Nội dung: <b><?= e($order['order_code']) ?></b></div>
                            <small>Cửa hàng xác nhận sau khi nhận được tiền.</small>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <section class="card">
                <h2 class="card-title">Giao hàng</h2>
                <dl class="info-list">
                    <dt>Người nhận</dt><dd><?= e($order['receiver_name']) ?></dd>
                    <dt>Điện thoại</dt><dd><?= e($order['receiver_phone']) ?></dd>
                    <dt>Địa chỉ</dt><dd><?= e($order['shipping_address']) ?></dd>
                    <?php if ($order['note']): ?><dt>Ghi chú</dt><dd><?= e($order['note']) ?></dd><?php endif; ?>
                </dl>
            </section>

            <?php if ($order['status'] === 'pending' && (!$payment || $payment['status'] !== 'paid')): ?>
                <form method="post" class="card" data-confirm="Bạn chắc chắn muốn hủy đơn hàng này?">
                    <input type="hidden" name="action" value="cancel">
                    <h2 class="card-title">Hủy đơn hàng</h2>
                    <div class="form">
                        <input name="reason" class="input" placeholder="Lý do hủy (không bắt buộc)">
                        <button class="btn btn-danger">Hủy đơn</button>
                    </div>
                </form>
            <?php endif; ?>
        </aside>
    </div>
</section>

<?php require '../layout/footer.php'; ?>
