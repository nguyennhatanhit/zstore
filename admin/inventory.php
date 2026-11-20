<?php
/** KHO HÀNG - [Admin 5] Quản lý kho: nhập kho, xuất kho, điều chỉnh (kiểm kê), cảnh báo sắp hết, lịch sử kho */
require '../config.php';
require_admin();

$errors = [];
$form = ['product_id' => (get_str('product_id') !== '' ? (int) get_str('product_id') : ''), 'type' => 'import', 'quantity' => '', 'note' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'product_id' => (int) post_str('product_id', '0'),
        'type'       => post_str('type'),
        'quantity'   => (int) post_str('quantity', '0'),
        'note'       => post_str('note'),
    ];
    $product = db_one("SELECT * FROM products WHERE id = ?", [$form['product_id']]);

    if (!$product) {
        $errors['product_id'] = 'Vui lòng chọn sản phẩm.';
    }
    if (!in_array($form['type'], ['import', 'export', 'adjust'])) {
        $errors['type'] = 'Loại thao tác không hợp lệ.';
    }
    if ($form['type'] === 'adjust' ? $form['quantity'] < 0 : $form['quantity'] <= 0) {
        $errors['quantity'] = $form['type'] === 'adjust' ? 'Số lượng thực tế không được âm.' : 'Số lượng phải lớn hơn 0.';
    }

    if (!$errors) {
        // Tính số lượng thay đổi
        if ($form['type'] === 'import') {
            $change = $form['quantity'];
        } elseif ($form['type'] === 'export') {
            $change = -$form['quantity'];
        } else {
            $change = $form['quantity'] - $product['stock_quantity'];
        }

        if ($product['stock_quantity'] + $change < 0) {
            $errors['quantity'] = 'Không đủ hàng để xuất (tồn kho hiện tại: ' . $product['stock_quantity'] . ').';
        } elseif ($change === 0) {
            $errors['quantity'] = 'Số lượng thực tế bằng tồn kho hiện tại, không có gì thay đổi.';
        } else {
            try {
                $pdo->beginTransaction();
                db_run("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?", [$change, $product['id']]);
                db_run("INSERT INTO inventory_logs (product_id, change_qty, type, note, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)",
                    [$product['id'], $change, $form['type'], $form['note'] ?: null, current_user()['id'], db_now()]);
                $pdo->commit();
                set_flash('success', sprintf('Đã cập nhật kho "%s": %s%d → tồn kho %d.',
                    $product['name'], $change > 0 ? '+' : '', $change, $product['stock_quantity'] + $change));
                redirect('admin/inventory.php?product_id=' . $product['id']);
            } catch (Exception $ex) {
                $pdo->rollBack();
                $errors['quantity'] = 'Lỗi: ' . $ex->getMessage();
            }
        }
    }
}

// Số liệu tổng
$stats = db_one("
    SELECT COUNT(*) AS products,
           COALESCE(SUM(stock_quantity), 0) AS units,
           COALESCE(SUM(price * stock_quantity), 0) AS value,
           SUM(CASE WHEN stock_quantity BETWEEN 1 AND " . LOW_STOCK . " THEN 1 ELSE 0 END) AS low,
           SUM(CASE WHEN stock_quantity <= 0 THEN 1 ELSE 0 END) AS out_of_stock
    FROM products WHERE status = 'active'");

// Bảng tồn kho
$filter = get_str('filter', '');
$q = get_str('q');
$where = ['1 = 1'];
$params = [];
if ($filter === 'low') {
    $where[] = "p.stock_quantity BETWEEN 1 AND " . LOW_STOCK;
} elseif ($filter === 'out') {
    $where[] = "p.stock_quantity <= 0";
}
if ($q !== '') {
    $where[] = "(p.name LIKE ? OR p.sku LIKE ?)";
    array_push($params, "%$q%", "%$q%");
}
$whereSql = implode(' AND ', $where);
$total = (int) db_value("SELECT COUNT(*) FROM products p WHERE $whereSql", $params);
$pg = paginate($total, 10);
$products = db_all(product_sql() . " WHERE $whereSql ORDER BY p.stock_quantity ASC, p.name LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params);

// Lịch sử kho
$logProduct = (int) get_str('product_id', '0');
$logSql = "SELECT l.*, p.name AS product_name, p.sku, u.full_name AS user_name
           FROM inventory_logs l JOIN products p ON p.id = l.product_id LEFT JOIN users u ON u.id = l.created_by";
$logs = $logProduct
    ? db_all($logSql . " WHERE l.product_id = ? ORDER BY l.created_at DESC, l.id DESC LIMIT 30", [$logProduct])
    : db_all($logSql . " ORDER BY l.created_at DESC, l.id DESC LIMIT 20");

$allProducts = db_all("SELECT id, name, sku, stock_quantity FROM products ORDER BY name");
$logTypes = [
    'import' => ['Nhập kho', 'success'], 'export' => ['Xuất kho', 'warning'], 'adjust' => ['Điều chỉnh', 'info'],
    'order'  => ['Bán hàng', 'info'],    'cancel' => ['Hoàn kho', 'success'],
];

$title = 'Kho hàng';
$breadcrumb = 'Kho hàng';
require 'layout/admin_header.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Kho hàng</h1>
        <p class="page-desc">Nhập, xuất, kiểm kê tồn kho và theo dõi lịch sử biến động.</p>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-head"><span>Sản phẩm đang bán</span><i class="bi bi-box-seam"></i></div><div class="stat-value"><?= (int) $stats['products'] ?></div><div class="stat-note"><?= number_format($stats['units'], 0, ',', '.') ?> sản phẩm trong kho</div></div>
    <div class="stat-card"><div class="stat-head"><span>Giá trị tồn kho</span><i class="bi bi-wallet2"></i></div><div class="stat-value"><?= money($stats['value']) ?></div><div class="stat-note">Tính theo giá bán</div></div>
    <div class="stat-card"><div class="stat-head"><span>Sắp hết hàng</span><i class="bi bi-exclamation-triangle"></i></div><div class="stat-value text-warning"><?= (int) $stats['low'] ?></div><div class="stat-note">Tồn kho 1–<?= LOW_STOCK ?></div></div>
    <div class="stat-card"><div class="stat-head"><span>Hết hàng</span><i class="bi bi-x-octagon"></i></div><div class="stat-value text-danger"><?= (int) $stats['out_of_stock'] ?></div><div class="stat-note">Cần nhập thêm</div></div>
</div>

<div class="detail-grid">
    <section class="panel">
        <div class="panel-head">
            <h2 class="panel-title">Tồn kho</h2>
        </div>
        <form method="get" class="filter-bar">
            <label class="search-input"><i class="bi bi-search"></i><input name="q" value="<?= e($q) ?>" placeholder="Tên hoặc SKU"></label>
            <select name="filter" class="select" data-autosubmit>
                <option value="">Tất cả</option>
                <option value="low" <?= $filter === 'low' ? 'selected' : '' ?>>Sắp hết</option>
                <option value="out" <?= $filter === 'out' ? 'selected' : '' ?>>Hết hàng</option>
            </select>
            <button class="btn btn-soft">Lọc</button>
        </form>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Sản phẩm</th><th>Danh mục</th><th class="num">Tồn kho</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($products as $p): ?>
                    <tr class="<?= $logProduct == $p['id'] ? 'row-editing' : '' ?>">
                        <td><b><?= e($p['name']) ?></b><div class="cell-sub"><?= e($p['sku']) ?><?= $p['status'] === 'hidden' ? ' · đang ẩn' : '' ?></div></td>
                        <td><?= e($p['category_name']) ?></td>
                        <td class="num"><b><?= $p['stock_quantity'] ?></b><div><?= stock_badge($p['stock_quantity']) ?></div></td>
                        <td><a href="?product_id=<?= $p['id'] ?>#stock-form" class="btn btn-sm btn-soft">Nhập / xuất</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$products): ?><tr><td colspan="4" class="empty">Không có sản phẩm.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="panel-foot"><span class="text-muted"><?= $total ?> sản phẩm</span><?= pagination_links($pg) ?></div>
    </section>

    <section class="panel" id="stock-form">
        <div class="panel-head"><h2 class="panel-title">Cập nhật tồn kho</h2></div>
        <form method="post" class="panel-body form" novalidate>
            <div class="form-group">
                <label for="product_id">Sản phẩm <span class="req">*</span></label>
                <select id="product_id" name="product_id" class="select <?= invalid($errors, 'product_id') ?>">
                    <option value="">— Chọn sản phẩm —</option>
                    <?php foreach ($allProducts as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $form['product_id'] == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?> (tồn: <?= $p['stock_quantity'] ?>)</option>
                    <?php endforeach; ?>
                </select>
                <?= show_error($errors, 'product_id') ?>
            </div>
            <div class="form-group">
                <label>Loại thao tác</label>
                <div class="segmented">
                    <?php foreach (['import' => 'Nhập kho', 'export' => 'Xuất kho', 'adjust' => 'Kiểm kê'] as $k => $label): ?>
                        <label><input type="radio" name="type" value="<?= $k ?>" <?= $form['type'] === $k ? 'checked' : '' ?>><span><?= $label ?></span></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <label for="quantity">Số lượng <span class="req">*</span></label>
                <input id="quantity" type="number" name="quantity" min="0" class="input <?= invalid($errors, 'quantity') ?>" value="<?= e($form['quantity']) ?>">
                <?= show_error($errors, 'quantity') ?: '<div class="form-hint">Kiểm kê: nhập số lượng thực tế đếm được, hệ thống tự tính chênh lệch.</div>' ?>
            </div>
            <div class="form-group">
                <label for="note">Ghi chú</label>
                <input id="note" name="note" class="input" value="<?= e($form['note']) ?>" placeholder="VD: Nhập lô hàng tháng 11, phiếu PN-0123">
            </div>
            <button class="btn btn-primary btn-block">Cập nhật kho</button>
        </form>
    </section>
</div>

<section class="panel mt-20">
    <div class="panel-head">
        <div>
            <h2 class="panel-title">Lịch sử kho</h2>
            <p class="panel-desc"><?= $logProduct ? '30 thay đổi gần nhất của sản phẩm đã chọn · <a href="' . url('admin/inventory.php') . '" class="link">Xem tất cả</a>' : '20 thay đổi gần nhất' ?></p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Thời gian</th><th>Sản phẩm</th><th>Loại</th><th class="num">Thay đổi</th><th>Ghi chú</th><th>Người thực hiện</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $l): $t = isset($logTypes[$l['type']]) ? $logTypes[$l['type']] : [$l['type'], '']; ?>
                <tr>
                    <td><?= format_date($l['created_at']) ?></td>
                    <td><?= e($l['product_name']) ?><div class="cell-sub"><?= e($l['sku']) ?></div></td>
                    <td><span class="badge badge-<?= $t[1] ?>"><?= $t[0] ?></span></td>
                    <td class="num"><b class="<?= $l['change_qty'] > 0 ? 'text-success' : 'text-danger' ?>"><?= $l['change_qty'] > 0 ? '+' : '' ?><?= $l['change_qty'] ?></b></td>
                    <td><?= e($l['note']) ?></td>
                    <td><?= e($l['user_name']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?><tr><td colspan="6" class="empty">Chưa có lịch sử.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require 'layout/admin_footer.php'; ?>
