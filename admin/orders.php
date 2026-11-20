<?php
/** ĐƠN HÀNG - [Admin 7] Tiếp nhận và xác nhận đơn hàng */
require '../config.php';
require_admin();

// Xác nhận nhanh đơn chờ xác nhận
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'confirm') {
    $order = db_one("SELECT * FROM orders WHERE id = ?", [(int) post_str('id', '0')]);
    if ($order) {
        $error = order_change_status($order, 'confirmed', 'Xác nhận đơn hàng');
        set_flash($error ? 'error' : 'success', $error ?: 'Đã xác nhận đơn ' . $order['order_code'] . '.');
    }
    redirect_back('admin/orders.php');
}

$status = isset(order_statuses()[get_str('status')]) ? get_str('status') : '';
$q = get_str('q');
$payment = get_str('payment', '');

$counts = array_column(db_all("SELECT status, COUNT(*) AS n FROM orders GROUP BY status"), 'n', 'status');

$where = ['1 = 1'];
$params = [];
if ($status) {
    $where[] = "o.status = ?";
    $params[] = $status;
}
if ($q !== '') {
    $where[] = "(o.order_code LIKE ? OR o.receiver_name LIKE ? OR o.receiver_phone LIKE ? OR u.email LIKE ?)";
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
if (in_array($payment, ['unpaid', 'paid', 'refunded'])) {
    $where[] = "pm.status = ?";
    $params[] = $payment;
}
$whereSql = implode(' AND ', $where);
$from = "FROM orders o JOIN users u ON u.id = o.user_id LEFT JOIN payments pm ON pm.order_id = o.id WHERE $whereSql";

$total = (int) db_value("SELECT COUNT(*) $from", $params);
$pg = paginate($total, 15);
$orders = db_all("
    SELECT o.*, u.full_name, u.email, pm.method AS payment_method, pm.status AS payment_status,
           (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) AS item_count
    $from ORDER BY o.created_at DESC, o.id DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params);

$title = 'Đơn hàng';
$breadcrumb = 'Đơn hàng';
require 'layout/admin_header.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Đơn hàng</h1>
        <p class="page-desc">Tiếp nhận, xác nhận và theo dõi đơn hàng của khách.</p>
    </div>
</div>

<?php if (!empty($counts['pending'])): ?>
    <div class="ops-alert">
        <i class="bi bi-exclamation-circle"></i><b>Cần xử lý</b>
        <span><strong class="text-warning"><?= $counts['pending'] ?></strong> đơn đang chờ xác nhận</span>
        <a href="?status=pending" class="link">Xem ngay</a>
    </div>
<?php endif; ?>

<section class="panel">
    <nav class="tabs tabs-pad">
        <a href="<?= url('admin/orders.php') ?>" class="<?= $status === '' ? 'active' : '' ?>">Tất cả <span><?= array_sum($counts) ?></span></a>
        <?php foreach (order_statuses() as $key => $s): ?>
            <a href="?status=<?= $key ?>" class="<?= $status === $key ? 'active' : '' ?>"><?= $s[0] ?> <span><?= isset($counts[$key]) ? $counts[$key] : 0 ?></span></a>
        <?php endforeach; ?>
    </nav>

    <form method="get" class="filter-bar">
        <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <label class="search-input"><i class="bi bi-search"></i><input name="q" value="<?= e($q) ?>" placeholder="Mã đơn, tên, SĐT hoặc email"></label>
        <select name="payment" class="select">
            <option value="">Mọi thanh toán</option>
            <option value="unpaid" <?= $payment === 'unpaid' ? 'selected' : '' ?>>Chưa thanh toán</option>
            <option value="paid" <?= $payment === 'paid' ? 'selected' : '' ?>>Đã thanh toán</option>
            <option value="refunded" <?= $payment === 'refunded' ? 'selected' : '' ?>>Đã hoàn tiền</option>
        </select>
        <button class="btn btn-soft">Lọc</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Ngày đặt</th><th class="num">SL</th><th>Tổng tiền</th><th>Thanh toán</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><a href="<?= url('admin/order_detail.php?id=' . $o['id']) ?>"><b><?= e($o['order_code']) ?></b></a></td>
                    <td><?= e($o['receiver_name']) ?><div class="cell-sub"><?= e($o['receiver_phone']) ?> · <?= e($o['email']) ?></div></td>
                    <td><?= format_date($o['created_at']) ?></td>
                    <td class="num"><?= (int) $o['item_count'] ?></td>
                    <td><b><?= money($o['total']) ?></b></td>
                    <td><?= payment_badge($o['payment_status']) ?><div class="cell-sub"><?= e(payment_short($o['payment_method'])) ?></div></td>
                    <td><?= order_badge($o['status']) ?></td>
                    <td>
                        <div class="row-actions">
                            <?php if ($o['status'] === 'pending'): ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="confirm">
                                    <input type="hidden" name="id" value="<?= $o['id'] ?>">
                                    <?= back_field() ?>
                                    <button class="btn btn-sm btn-primary">Xác nhận</button>
                                </form>
                            <?php endif; ?>
                            <a href="<?= url('admin/order_detail.php?id=' . $o['id']) ?>" class="btn btn-sm btn-soft">Chi tiết</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?><tr><td colspan="8" class="empty">Không có đơn hàng.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="panel-foot"><span class="text-muted"><?= $total ?> đơn hàng</span><?= pagination_links($pg) ?></div>
</section>

<?php require 'layout/admin_footer.php'; ?>
