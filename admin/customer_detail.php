<?php
/** CHI TIẾT KHÁCH HÀNG - [Admin 12] Theo dõi lịch sử mua hàng của khách hàng */
require '../config.php';
require_admin();

$id = (int) get_str('id', '0');
$customer = db_one("SELECT * FROM users WHERE id = ? AND role = 'customer'", [$id]);
if (!$customer) {
    set_flash('error', 'Không tìm thấy khách hàng.');
    redirect('admin/customers.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = $customer['status'] === 'active' ? 'locked' : 'active';
    db_run("UPDATE users SET status = ? WHERE id = ?", [$new, $id]);
    set_flash('success', $new === 'locked' ? 'Đã khóa tài khoản.' : 'Đã mở khóa tài khoản.');
    redirect('admin/customer_detail.php?id=' . $id);
}

$stats = db_one("
    SELECT COUNT(*) AS orders,
           COALESCE(SUM(CASE WHEN status = 'completed' THEN total ELSE 0 END), 0) AS spent,
           SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
           SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled
    FROM orders WHERE user_id = ?", [$id]);

$orders = db_all("
    SELECT o.*, pm.method AS payment_method, pm.status AS payment_status
    FROM orders o LEFT JOIN payments pm ON pm.order_id = o.id
    WHERE o.user_id = ? ORDER BY o.created_at DESC, o.id DESC", [$id]);
$itemNames = [];
if ($orders) {
    $ids = array_column($orders, 'id');
    foreach (db_all("SELECT order_id, product_name, quantity FROM order_items WHERE order_id IN (" . placeholders($ids) . ")", $ids) as $it) {
        $itemNames[$it['order_id']][] = $it['product_name'] . ($it['quantity'] > 1 ? ' ×' . $it['quantity'] : '');
    }
}

// Sản phẩm khách mua nhiều nhất
$topProducts = db_all("
    SELECT oi.product_name, SUM(oi.quantity) AS qty, SUM(oi.price * oi.quantity) AS amount
    FROM order_items oi JOIN orders o ON o.id = oi.order_id
    WHERE o.user_id = ? AND o.status <> 'cancelled'
    GROUP BY oi.product_name ORDER BY qty DESC LIMIT 5", [$id]);

$title = $customer['full_name'];
$breadcrumb = 'Chi tiết khách hàng';
require 'layout/admin_header.php';
?>

<a href="<?= url('admin/customers.php') ?>" class="back-link"><i class="bi bi-arrow-left"></i> Quay lại danh sách</a>

<section class="panel profile-card">
    <span class="avatar avatar-xl"><?= e(initials($customer['full_name'])) ?></span>
    <div class="profile-main">
        <h1 class="page-title"><?= e($customer['full_name']) ?></h1>
        <div class="text-muted">Mã KH<?= str_pad($customer['id'], 5, '0', STR_PAD_LEFT) ?> · Thành viên từ <?= format_date($customer['created_at'], 'd/m/Y') ?></div>
        <dl class="info-inline">
            <div><dt>Email</dt><dd><?= e($customer['email']) ?></dd></div>
            <div><dt>Điện thoại</dt><dd><?= e($customer['phone'] ?: '—') ?></dd></div>
            <div><dt>Địa chỉ</dt><dd><?= e($customer['address'] ?: '—') ?></dd></div>
        </dl>
    </div>
    <div class="profile-actions">
        <?= $customer['status'] === 'active' ? '<span class="badge badge-success">Hoạt động</span>' : '<span class="badge badge-danger">Đã khóa</span>' ?>
        <form method="post" data-confirm="<?= $customer['status'] === 'active' ? 'Khóa' : 'Mở khóa' ?> tài khoản này?">
            <button class="btn <?= $customer['status'] === 'active' ? 'btn-outline' : 'btn-primary' ?>">
                <i class="bi <?= $customer['status'] === 'active' ? 'bi-lock' : 'bi-unlock' ?>"></i> <?= $customer['status'] === 'active' ? 'Khóa tài khoản' : 'Mở khóa' ?>
            </button>
        </form>
    </div>
</section>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-head"><span>Tổng đơn hàng</span><i class="bi bi-bag"></i></div><div class="stat-value"><?= (int) $stats['orders'] ?></div><div class="stat-note"><?= (int) $stats['completed'] ?> hoàn thành · <?= (int) $stats['cancelled'] ?> đã hủy</div></div>
    <div class="stat-card"><div class="stat-head"><span>Tổng chi tiêu</span><i class="bi bi-wallet2"></i></div><div class="stat-value"><?= money($stats['spent']) ?></div><div class="stat-note">Từ đơn hoàn thành</div></div>
    <div class="stat-card"><div class="stat-head"><span>Giá trị TB / đơn</span><i class="bi bi-graph-up"></i></div><div class="stat-value"><?= money($stats['completed'] ? $stats['spent'] / $stats['completed'] : 0) ?></div><div class="stat-note">Đơn hoàn thành</div></div>
    <div class="stat-card"><div class="stat-head"><span>Đơn gần nhất</span><i class="bi bi-clock-history"></i></div><div class="stat-value stat-small"><?= $orders ? format_date($orders[0]['created_at'], 'd/m/Y') : '—' ?></div><div class="stat-note"><?= $orders ? e($orders[0]['order_code']) : 'Chưa mua hàng' ?></div></div>
</div>

<div class="detail-grid">
    <section class="panel">
        <div class="panel-head">
            <div>
                <h2 class="panel-title">Lịch sử mua hàng</h2>
                <p class="panel-desc">Tất cả <?= count($orders) ?> đơn hàng của khách</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Mã đơn</th><th>Ngày đặt</th><th>Sản phẩm</th><th>Giá trị</th><th>Thanh toán</th><th>Trạng thái</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><a href="<?= url('admin/order_detail.php?id=' . $o['id']) ?>"><b><?= e($o['order_code']) ?></b></a></td>
                        <td><?= format_date($o['created_at']) ?></td>
                        <td class="cell-wrap"><?= e(implode(', ', isset($itemNames[$o['id']]) ? $itemNames[$o['id']] : [])) ?></td>
                        <td><b><?= money($o['total']) ?></b></td>
                        <td><?= payment_badge($o['payment_status']) ?><div class="cell-sub"><?= e(payment_short($o['payment_method'])) ?></div></td>
                        <td><?= order_badge($o['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$orders): ?><tr><td colspan="6" class="empty">Khách chưa có đơn hàng.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head"><h2 class="panel-title">Sản phẩm mua nhiều</h2></div>
        <div class="panel-body">
            <?php if (!$topProducts): ?><p class="text-muted">Chưa có dữ liệu.</p><?php endif; ?>
            <ul class="rank-list">
                <?php foreach ($topProducts as $i => $tp): ?>
                    <li><span class="rank"><?= $i + 1 ?></span><span><?= e($tp['product_name']) ?><small><?= $tp['qty'] ?> sản phẩm</small></span><b><?= money($tp['amount']) ?></b></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
</div>

<?php require 'layout/admin_footer.php'; ?>
