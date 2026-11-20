<?php
/** ĐƠN HÀNG CỦA TÔI - [CN 17] Tra cứu lịch sử đơn hàng (lọc theo trạng thái) */
require '../../config.php';
require_login();

$user = db_one("SELECT * FROM users WHERE id = ?", [current_user()['id']]);
$status = isset(order_statuses()[get_str('status')]) ? get_str('status') : '';

// Số đơn theo từng trạng thái (cho các tab)
$counts = array_column(db_all("SELECT status, COUNT(*) AS n FROM orders WHERE user_id = ? GROUP BY status", [$user['id']]), 'n', 'status');

$sql = "SELECT o.*, p.method AS payment_method, p.status AS payment_status,
               (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) AS item_count
        FROM orders o LEFT JOIN payments p ON p.order_id = o.id
        WHERE o.user_id = ?";
$params = [$user['id']];
if ($status) {
    $sql .= " AND o.status = ?";
    $params[] = $status;
}
$orders = db_all($sql . " ORDER BY o.created_at DESC, o.id DESC", $params);

// Tên sản phẩm của từng đơn (hiện tóm tắt)
$itemNames = [];
if ($orders) {
    $ids = array_column($orders, 'id');
    foreach (db_all("SELECT order_id, product_name, quantity FROM order_items WHERE order_id IN (" . placeholders($ids) . ")", $ids) as $it) {
        $itemNames[$it['order_id']][] = $it['product_name'] . ($it['quantity'] > 1 ? ' ×' . $it['quantity'] : '');
    }
}

$title = 'Đơn hàng của tôi';
require '../layout/header.php';
?>

<section class="container section">
    <div class="account-layout">
        <?php require '../layout/account_sidebar.php'; ?>

        <div class="card">
            <div class="card-head">
                <h1 class="card-title">Đơn hàng của tôi</h1>
                <p class="text-muted">Theo dõi trạng thái và lịch sử mua hàng.</p>
            </div>

            <nav class="tabs">
                <a href="<?= url('orders.php') ?>" class="<?= $status === '' ? 'active' : '' ?>">Tất cả <span><?= array_sum($counts) ?></span></a>
                <?php foreach (order_statuses() as $key => $s): ?>
                    <a href="<?= url('orders.php?status=' . $key) ?>" class="<?= $status === $key ? 'active' : '' ?>"><?= $s[0] ?> <span><?= isset($counts[$key]) ? $counts[$key] : 0 ?></span></a>
                <?php endforeach; ?>
            </nav>

            <?php if (!$orders): ?>
                <div class="empty-state">
                    <i class="bi bi-bag"></i>
                    <h3>Chưa có đơn hàng nào</h3>
                    <a href="<?= url('products.php') ?>" class="btn btn-primary">Mua sắm ngay</a>
                </div>
            <?php else: ?>
                <div class="order-list">
                    <?php foreach ($orders as $o): ?>
                        <a href="<?= url('order.php?code=' . $o['order_code']) ?>" class="order-card">
                            <div class="order-card-head">
                                <b><?= e($o['order_code']) ?></b>
                                <span class="text-muted"><?= format_date($o['created_at']) ?></span>
                                <?= order_badge($o['status']) ?>
                            </div>
                            <div class="order-card-body">
                                <span class="text-muted"><?= e(implode(', ', isset($itemNames[$o['id']]) ? $itemNames[$o['id']] : [])) ?></span>
                                <span><?= (int) $o['item_count'] ?> sản phẩm · <b class="text-blue"><?= money($o['total']) ?></b></span>
                            </div>
                            <div class="order-card-foot">
                                <span><?= e(payment_methods()[$o['payment_method']] ?? '') ?></span>
                                <?= payment_badge($o['payment_status']) ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require '../layout/footer.php'; ?>
