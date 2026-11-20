<?php
/**
 * THANH TOÁN
 *   [CN 14] Khởi tạo đơn hàng
 *   [CN 15] Tạo thông tin thanh toán (COD / chuyển khoản)
 */
require '../../config.php';
require_login();

$cart = cart_totals();
if (!$cart['items']) {
    set_flash('warning', 'Giỏ hàng đang trống.');
    redirect('cart.php');
}

$user = db_one("SELECT * FROM users WHERE id = ?", [current_user()['id']]);
$errors = [];
$form = [
    'receiver_name'    => $user['full_name'],
    'receiver_phone'   => $user['phone'],
    'shipping_address' => $user['address'],
    'note'             => '',
    'payment_method'   => 'cod',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['receiver_name', 'receiver_phone', 'shipping_address', 'note', 'payment_method'] as $f) {
        $form[$f] = post_str($f);
    }

    if ($form['receiver_name'] === '') {
        $errors['receiver_name'] = 'Vui lòng nhập tên người nhận.';
    }
    if (!is_valid_phone($form['receiver_phone'])) {
        $errors['receiver_phone'] = 'Số điện thoại không hợp lệ (VD: 0912345678).';
    }
    if (mb_strlen($form['shipping_address']) < 10) {
        $errors['shipping_address'] = 'Vui lòng nhập địa chỉ giao hàng đầy đủ.';
    }
    if (!isset(payment_methods()[$form['payment_method']])) {
        $errors['payment_method'] = 'Vui lòng chọn phương thức thanh toán.';
    }
    if ($form['payment_method'] === 'payos' && !payos_enabled()) {
        $errors['payment_method'] = 'PayOS chưa được cấu hình. Vui lòng liên hệ cửa hàng.';
    }

    // Tổng tiền khách đã thấy khác tổng tính lại (mã giảm giá hết hạn/hết lượt, khuyến mãi kết thúc, đổi giá...)
    $expected = (int) post_str('expected_total', '-1');
    if ($expected !== (int) $cart['total']) {
        $errors['general'] = ($cart['coupon_error'] ? $cart['coupon_error'] . ' ' : '')
            . 'Tổng tiền đơn hàng đã thay đổi thành ' . money($cart['total']) . '. Vui lòng kiểm tra lại rồi bấm "Đặt hàng".';
    }

    if (!$errors) {
        $cart = cart_totals();
        try {
            $pdo->beginTransaction();

            $code = new_order_code();
            db_run("
                INSERT INTO orders (order_code, user_id, receiver_name, receiver_phone, shipping_address, note,
                                    promotion_id, subtotal, discount, shipping_fee, total, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)",
                [$code, $user['id'], $form['receiver_name'], $form['receiver_phone'], $form['shipping_address'], $form['note'] ?: null,
                 $cart['coupon'] ? $cart['coupon']['id'] : null, $cart['subtotal'], $cart['discount'], $cart['shipping'], $cart['total'],
                 db_now(), db_now()]);
            $orderId = db_last_id();

            foreach ($cart['items'] as $item) {
                // Trừ kho có điều kiện: chỉ trừ khi còn đủ hàng (tránh 2 người cùng mua món cuối)
                $ok = db_run("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?",
                    [$item['quantity'], $item['id'], $item['quantity']]);
                if (!$ok) {
                    throw new Exception('Sản phẩm "' . $item['name'] . '" không còn đủ hàng.');
                }
                db_run("INSERT INTO order_items (order_id, product_id, product_name, price, quantity) VALUES (?, ?, ?, ?, ?)",
                    [$orderId, $item['id'], $item['name'], $item['sale_price'], $item['quantity']]);
                db_run("INSERT INTO inventory_logs (product_id, change_qty, type, note, created_by, created_at) VALUES (?, ?, 'order', ?, ?, ?)",
                    [$item['id'], -$item['quantity'], 'Bán theo đơn ' . $code, $user['id'], db_now()]);
            }

            order_add_history($orderId, 'pending', 'Khách đặt hàng');
            db_run("INSERT INTO payments (order_id, method, amount, status, created_at) VALUES (?, ?, ?, 'unpaid', ?)",
                [$orderId, $form['payment_method'], $cart['total'], db_now()]);
            if ($cart['coupon']) {
                $ok = db_run("UPDATE promotions SET used_count = used_count + 1
                              WHERE id = ? AND (usage_limit IS NULL OR used_count < usage_limit)", [$cart['coupon']['id']]);
                if (!$ok) {
                    throw new Exception('Mã giảm giá ' . $cart['coupon']['code'] . ' vừa hết lượt sử dụng. Vui lòng gỡ mã và đặt lại.');
                }
            }

            $pdo->commit();
            cart_clear();
            set_flash('success', 'Đặt hàng thành công! Mã đơn của bạn là ' . $code . '.');
            if ($form['payment_method'] === 'payos') {
                redirect('payos-create.php?code=' . urlencode($code));
            }
            redirect('order.php?code=' . urlencode($code));
        } catch (Exception $ex) {
            $pdo->rollBack();
            $errors['general'] = $ex->getMessage();
        }
    }
}

$title = 'Thanh toán';
require '../layout/header.php';
?>

<section class="container section">
    <div class="page-heading">
        <h1>Thanh toán</h1>
        <p class="text-muted">Kiểm tra thông tin nhận hàng và chọn phương thức thanh toán.</p>
    </div>

    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i><span><?= e($errors['general']) ?> <a href="<?= url('cart.php') ?>" class="link">Về giỏ hàng</a></span></div>
    <?php endif; ?>

    <form method="post" class="cart-layout" novalidate>
        <input type="hidden" name="expected_total" value="<?= (int) $cart['total'] ?>">
        <div class="stack">
            <section class="card">
                <h2 class="card-title"><span class="step-no">1</span>Thông tin nhận hàng</h2>
                <div class="form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="receiver_name">Người nhận <span class="req">*</span></label>
                            <input id="receiver_name" name="receiver_name" class="input <?= invalid($errors, 'receiver_name') ?>" value="<?= e($form['receiver_name']) ?>">
                            <?= show_error($errors, 'receiver_name') ?>
                        </div>
                        <div class="form-group">
                            <label for="receiver_phone">Số điện thoại <span class="req">*</span></label>
                            <input id="receiver_phone" name="receiver_phone" class="input <?= invalid($errors, 'receiver_phone') ?>" value="<?= e($form['receiver_phone']) ?>">
                            <?= show_error($errors, 'receiver_phone') ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="shipping_address">Địa chỉ giao hàng <span class="req">*</span></label>
                        <textarea id="shipping_address" name="shipping_address" rows="2" class="textarea <?= invalid($errors, 'shipping_address') ?>"
                                  placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành"><?= e($form['shipping_address']) ?></textarea>
                        <?= show_error($errors, 'shipping_address') ?>
                    </div>
                    <div class="form-group">
                        <label for="note">Ghi chú</label>
                        <input id="note" name="note" class="input" value="<?= e($form['note']) ?>" placeholder="VD: Giao giờ hành chính">
                    </div>
                </div>
            </section>

            <section class="card">
                <h2 class="card-title"><span class="step-no">2</span>Phương thức thanh toán</h2>
                <div class="radio-list">
                    <?php foreach (payment_methods() as $key => $label): ?>
                        <label class="radio-card">
                            <input type="radio" name="payment_method" value="<?= $key ?>" <?= $form['payment_method'] === $key ? 'checked' : '' ?>>
                            <i class="bi <?= $key === 'cod' ? 'bi-cash-coin' : ($key === 'payos' ? 'bi-qr-code-scan' : 'bi-bank') ?>"></i>
                            <span>
                                <b><?= e($label) ?></b>
                                <small><?php
                                if ($key === 'cod') {
                                    echo 'Trả tiền mặt khi nhận hàng.';
                                } elseif ($key === 'bank_transfer') {
                                    echo 'Chuyển khoản tới ' . e(BANK_NAME) . ' sau khi đặt, nội dung là mã đơn hàng.';
                                } else {
                                    echo 'Quét mã QR hoặc thanh toán trực tuyến qua PayOS.';
                                }
                            ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?= show_error($errors, 'payment_method') ?>
            </section>
        </div>

        <aside class="card summary">
            <h2 class="card-title">Đơn hàng (<?= $cart['count'] ?> sản phẩm)</h2>
            <ul class="mini-items">
                <?php foreach ($cart['items'] as $item): ?>
                    <li>
                        <img src="<?= img_url($item['image']) ?>" alt="" onerror="this.onerror=null;this.src='<?= img_url('') ?>'">
                        <span><?= e($item['name']) ?><small>× <?= $item['quantity'] ?></small></span>
                        <b><?= money($item['line_total']) ?></b>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="summary-row"><span>Tạm tính</span><b><?= money($cart['subtotal']) ?></b></div>
            <?php if ($cart['discount'] > 0): ?>
                <div class="summary-row text-success"><span>Mã <?= e($cart['coupon']['code']) ?></span><span>-<?= money($cart['discount']) ?></span></div>
            <?php endif; ?>
            <div class="summary-row"><span>Phí giao hàng</span><span><?= $cart['shipping'] ? money($cart['shipping']) : 'Miễn phí' ?></span></div>
            <div class="summary-row summary-total"><span>Tổng thanh toán</span><b><?= money($cart['total']) ?></b></div>
            <button class="btn btn-primary btn-block btn-lg">Đặt hàng</button>
            <a href="<?= url('cart.php') ?>" class="btn btn-outline btn-block mt-8">Quay lại giỏ hàng</a>
        </aside>
    </form>
</section>

<?php require '../layout/footer.php'; ?>
