<?php
/** TỔNG QUAN & THỐNG KÊ - [Admin 13] Thống kê doanh thu, đơn hàng, tồn kho */
require '../config.php';
require_admin();

$periods = ['7d' => '7 ngày', '30d' => '30 ngày', '12m' => '12 tháng'];
$period = isset($periods[get_str('period')]) ? get_str('period') : '7d';

// Mốc bắt đầu + các nhãn của biểu đồ (điền đủ cả ngày/tháng không có doanh thu)
$labels = [];
if ($period === '12m') {
    $from = date('Y-m-01', strtotime('-11 months'));
    for ($i = 11; $i >= 0; $i--) {
        $labels[date('Y-m', strtotime("-$i months", strtotime(date('Y-m-01'))))] = 0;
    }
    $groupExpr = "SUBSTR(created_at, 1, 7)";
} else {
    $days = $period === '30d' ? 30 : 7;
    $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    for ($i = $days - 1; $i >= 0; $i--) {
        $labels[date('Y-m-d', strtotime("-$i days"))] = 0;
    }
    $groupExpr = "SUBSTR(created_at, 1, 10)";
}

// Thẻ số liệu trong kỳ
$revenue   = (float) db_value("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status = 'completed' AND created_at >= ?", [$from]);
$orderCnt  = (int) db_value("SELECT COUNT(*) FROM orders WHERE created_at >= ?", [$from]);
$completed = (int) db_value("SELECT COUNT(*) FROM orders WHERE status = 'completed' AND created_at >= ?", [$from]);
$newCust   = (int) db_value("SELECT COUNT(*) FROM users WHERE role = 'customer' AND created_at >= ?", [$from]);
$pending   = (int) db_value("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
$unpaidBank = (int) db_value("SELECT COUNT(*) FROM payments pm JOIN orders o ON o.id = pm.order_id
                              WHERE pm.method IN ('bank_transfer', 'payos') AND pm.status = 'unpaid' AND o.status <> 'cancelled'");
$lowStock  = (int) db_value("SELECT COUNT(*) FROM products WHERE status = 'active' AND stock_quantity <= " . LOW_STOCK);

// Doanh thu theo thời gian
$revenueRows = db_all("SELECT $groupExpr AS k, SUM(total) AS revenue, COUNT(*) AS n
                       FROM orders WHERE status = 'completed' AND created_at >= ? GROUP BY $groupExpr", [$from]);
$revenueData = $labels;
$orderData = $labels;
foreach ($revenueRows as $r) {
    if (isset($revenueData[$r['k']])) {
        $revenueData[$r['k']] = (float) $r['revenue'];
        $orderData[$r['k']] = (int) $r['n'];
    }
}
$chartLabels = array_map(function ($k) use ($period) {
    return $period === '12m' ? date('m/Y', strtotime($k . '-01')) : date('d/m', strtotime($k));
}, array_keys($labels));

// Đơn theo trạng thái (trong kỳ)
$statusCounts = array_column(db_all("SELECT status, COUNT(*) AS n FROM orders WHERE created_at >= ? GROUP BY status", [$from]), 'n', 'status');

// Top sản phẩm bán chạy (trong kỳ, đơn hoàn thành)
$topProducts = db_all("
    SELECT oi.product_id, oi.product_name, SUM(oi.quantity) AS qty, SUM(oi.price * oi.quantity) AS amount
    FROM order_items oi JOIN orders o ON o.id = oi.order_id
    WHERE o.status = 'completed' AND o.created_at >= ?
    GROUP BY oi.product_id, oi.product_name ORDER BY qty DESC, amount DESC LIMIT 5", [$from]);

// Doanh thu theo danh mục (trong kỳ)
$byCategory = db_all("
    SELECT COALESCE(c.name, 'Khác') AS name, SUM(oi.price * oi.quantity) AS amount
    FROM order_items oi JOIN orders o ON o.id = oi.order_id
    LEFT JOIN products p ON p.id = oi.product_id LEFT JOIN categories c ON c.id = p.category_id
    WHERE o.status = 'completed' AND o.created_at >= ?
    GROUP BY c.name ORDER BY amount DESC LIMIT 6", [$from]);
$maxCat = $byCategory ? max(array_column($byCategory, 'amount')) : 0;

// Tồn kho
$lowProducts = db_all(product_sql() . " WHERE p.status = 'active' AND p.stock_quantity <= " . LOW_STOCK . " ORDER BY p.stock_quantity, p.name LIMIT 6");
$stockValue = (float) db_value("SELECT COALESCE(SUM(price * stock_quantity), 0) FROM products WHERE status = 'active'");

$recentOrders = db_all("SELECT o.*, u.email FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC, o.id DESC LIMIT 5");

$title = 'Tổng quan';
$breadcrumb = 'Tổng quan & Thống kê';
require 'layout/admin_header.php';
?>

<div class="page-head">
    <div>
        <div class="page-date"><?= date('d/m/Y') ?></div>
        <h1 class="page-title">Tổng quan vận hành</h1>
        <p class="page-desc">Doanh thu, đơn hàng, khách hàng và tồn kho trong <?= $periods[$period] ?> gần nhất.</p>
    </div>
    <div class="page-actions">
        <div class="segmented">
            <?php foreach ($periods as $k => $label): ?>
                <a href="?period=<?= $k ?>" class="<?= $k === $period ? 'active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-head"><span>Doanh thu</span><i class="bi bi-wallet2"></i></div>
        <div class="stat-value"><?= money($revenue) ?></div>
        <div class="stat-note">Từ <?= $completed ?> đơn hoàn thành</div>
    </div>
    <div class="stat-card">
        <div class="stat-head"><span>Đơn hàng</span><i class="bi bi-bag"></i></div>
        <div class="stat-value"><?= $orderCnt ?></div>
        <div class="stat-note">Giá trị TB: <?= money($completed ? $revenue / $completed : 0) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-head"><span>Khách hàng mới</span><i class="bi bi-person-plus"></i></div>
        <div class="stat-value"><?= $newCust ?></div>
        <div class="stat-note">Tài khoản đăng ký trong kỳ</div>
    </div>
    <div class="stat-card">
        <div class="stat-head"><span>Giá trị tồn kho</span><i class="bi bi-boxes"></i></div>
        <div class="stat-value"><?= money($stockValue) ?></div>
        <div class="stat-note"><?= $lowStock ?> sản phẩm sắp hết</div>
    </div>
</div>

<?php if ($pending || $unpaidBank || $lowStock): ?>
    <div class="ops-alert">
        <i class="bi bi-exclamation-circle"></i>
        <b>Cảnh báo vận hành</b>
        <a href="<?= url('admin/orders.php?status=pending') ?>"><strong class="text-warning"><?= $pending ?></strong> đơn chờ xác nhận</a>
        <a href="<?= url('admin/orders.php?payment=unpaid') ?>"><strong class="text-warning"><?= $unpaidBank ?></strong> đơn chuyển khoản / QR chưa thanh toán</a>
        <a href="<?= url('admin/inventory.php?filter=low') ?>"><strong class="text-warning"><?= $lowStock ?></strong> sản phẩm sắp hết hàng</a>
    </div>
<?php endif; ?>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2 class="panel-title">Doanh thu theo <?= $period === '12m' ? 'tháng' : 'ngày' ?></h2>
            <p class="panel-desc">Chỉ tính đơn đã hoàn thành</p>
        </div>
    </div>
    <div class="panel-body"><div class="chart-box"><canvas id="revenueChart"></canvas></div></div>
</section>

<div class="grid-2">
    <section class="panel">
        <div class="panel-head"><h2 class="panel-title">Sản phẩm bán chạy</h2></div>
        <div class="panel-body">
            <?php if (!$topProducts): ?><p class="text-muted">Chưa có đơn hoàn thành trong kỳ.</p><?php endif; ?>
            <ul class="rank-list">
                <?php foreach ($topProducts as $i => $tp): ?>
                    <li><span class="rank"><?= $i + 1 ?></span><span><?= e($tp['product_name']) ?><small>Đã bán <?= $tp['qty'] ?></small></span><b><?= money($tp['amount']) ?></b></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head"><h2 class="panel-title">Doanh thu theo danh mục</h2></div>
        <div class="panel-body">
            <?php if (!$byCategory): ?><p class="text-muted">Chưa có dữ liệu trong kỳ.</p><?php endif; ?>
            <?php foreach ($byCategory as $c): ?>
                <div class="bar-row">
                    <span><?= e($c['name']) ?></span>
                    <div class="bar"><span style="width: <?= $maxCat ? round($c['amount'] / $maxCat * 100) : 0 ?>%"></span></div>
                    <b><?= money($c['amount']) ?></b>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head"><h2 class="panel-title">Đơn hàng theo trạng thái</h2></div>
        <div class="panel-body">
            <?php $maxSt = $statusCounts ? max($statusCounts) : 0; ?>
            <?php foreach (order_statuses() as $key => $s): $n = isset($statusCounts[$key]) ? $statusCounts[$key] : 0; ?>
                <div class="bar-row">
                    <span><?= $s[0] ?></span>
                    <div class="bar bar-<?= $s[1] ?>"><span style="width: <?= $maxSt ? round($n / $maxSt * 100) : 0 ?>%"></span></div>
                    <b><?= $n ?></b>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h2 class="panel-title">Sắp hết hàng</h2>
            <a href="<?= url('admin/inventory.php?filter=low') ?>" class="btn btn-soft btn-sm">Quản lý kho</a>
        </div>
        <div class="table-wrap">
            <table class="table">
                <tbody>
                <?php foreach ($lowProducts as $p): ?>
                    <tr><td><?= e($p['name']) ?><div class="cell-sub"><?= e($p['category_name']) ?></div></td><td class="num"><?= stock_badge($p['stock_quantity']) ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$lowProducts): ?><tr><td class="empty">Tồn kho ổn định.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<section class="panel">
    <div class="panel-head">
        <div><h2 class="panel-title">Đơn hàng gần đây</h2><p class="panel-desc">5 đơn mới nhất</p></div>
        <a href="<?= url('admin/orders.php') ?>" class="btn btn-soft btn-sm">Xem tất cả</a>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Ngày đặt</th><th>Giá trị</th><th>Trạng thái</th></tr></thead>
            <tbody>
            <?php foreach ($recentOrders as $o): ?>
                <tr>
                    <td><a href="<?= url('admin/order_detail.php?id=' . $o['id']) ?>"><b><?= e($o['order_code']) ?></b></a></td>
                    <td><?= e($o['receiver_name']) ?><div class="cell-sub"><?= e($o['email']) ?></div></td>
                    <td><?= format_date($o['created_at']) ?></td>
                    <td><?= money($o['total']) ?></td>
                    <td><?= order_badge($o['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recentOrders): ?><tr><td colspan="5" class="empty">Chưa có đơn hàng.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    (function () {
        var el = document.getElementById('revenueChart');
        if (!el || typeof Chart === 'undefined') return;
        new Chart(el, {
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [
                    { type: 'line', label: 'Doanh thu (₫)', data: <?= json_encode(array_values($revenueData)) ?>, yAxisID: 'y',
                      borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.12)', fill: true, tension: .3, pointRadius: 3 },
                    { type: 'bar', label: 'Số đơn', data: <?= json_encode(array_values($orderData)) ?>, yAxisID: 'y1',
                      backgroundColor: 'rgba(148,163,184,.35)', borderRadius: 4 }
                ]
            },
            options: {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { tooltip: { callbacks: { label: function (c) {
                    return c.dataset.yAxisID === 'y' ? ' ' + c.parsed.y.toLocaleString('vi-VN') + ' ₫' : ' ' + c.parsed.y + ' đơn';
                } } } },
                scales: {
                    y:  { beginAtZero: true, ticks: { callback: function (v) { return (v / 1e6).toLocaleString('vi-VN') + 'tr'; } } },
                    y1: { beginAtZero: true, position: 'right', grid: { display: false }, ticks: { precision: 0 } }
                }
            }
        });
    })();
</script>

<?php require 'layout/admin_footer.php'; ?>
