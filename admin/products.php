<?php
/** SẢN PHẨM - [Admin 1] Danh sách, tìm kiếm, lọc, xóa sản phẩm */
require '../config.php';
require_admin();

// Xóa / ẩn / hiện
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) post_str('id', '0');
    $product = db_one("SELECT * FROM products WHERE id = ?", [$id]);

    if ($product && post_str('action') === 'delete') {
        $sold = db_value("SELECT COUNT(*) FROM order_items WHERE product_id = ?", [$id]);
        if ($sold > 0) {
            // Đã có trong đơn hàng: chỉ ẩn để giữ lịch sử
            db_run("UPDATE products SET status = 'hidden' WHERE id = ?", [$id]);
            set_flash('warning', 'Sản phẩm đã có trong đơn hàng nên được chuyển sang "Ẩn" thay vì xóa.');
        } else {
            foreach (db_all("SELECT image_url FROM product_images WHERE product_id = ?", [$id]) as $img) {
                $file = ROOT . '/' . $img['image_url'];
                if (strpos($img['image_url'], 'uploads/') === 0 && is_file($file)) {
                    unlink($file);
                }
            }
            db_run("DELETE FROM inventory_logs WHERE product_id = ?", [$id]);
            db_run("DELETE FROM products WHERE id = ?", [$id]);
            set_flash('success', 'Đã xóa sản phẩm "' . $product['name'] . '".');
        }
    }
    if ($product && post_str('action') === 'toggle') {
        $new = $product['status'] === 'active' ? 'hidden' : 'active';
        db_run("UPDATE products SET status = ? WHERE id = ?", [$new, $id]);
        set_flash('success', ($new === 'active' ? 'Đã hiện' : 'Đã ẩn') . ' sản phẩm "' . $product['name'] . '".');
    }
    redirect_back('admin/products.php');
}

// Lọc
$q        = get_str('q');
$catId    = (int) get_str('category', '0');
$brandId  = (int) get_str('brand', '0');
$status   = get_str('status', '');
$stock    = get_str('stock', '');

$where = ['1 = 1'];
$params = [];
if ($q !== '') {
    $where[] = "(p.name LIKE ? OR p.sku LIKE ?)";
    array_push($params, "%$q%", "%$q%");
}
if ($catId) {
    $where[] = "(p.category_id = ? OR c.parent_id = ?)";
    array_push($params, $catId, $catId);
}
if ($brandId) {
    $where[] = "p.brand_id = ?";
    $params[] = $brandId;
}
if (in_array($status, ['active', 'hidden'])) {
    $where[] = "p.status = ?";
    $params[] = $status;
}
if ($stock === 'low') {
    $where[] = "p.stock_quantity BETWEEN 1 AND " . LOW_STOCK;
} elseif ($stock === 'out') {
    $where[] = "p.stock_quantity <= 0";
}
$whereSql = implode(' AND ', $where);

$total = (int) db_value("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE $whereSql", $params);
$pg = paginate($total, 15);
$products = db_all(product_sql() . " WHERE $whereSql ORDER BY p.id DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params);

$categories = db_all("SELECT * FROM categories ORDER BY parent_id IS NOT NULL, sort_order, name");
$brands = db_all("SELECT * FROM brands ORDER BY name");

$title = 'Sản phẩm';
$breadcrumb = 'Sản phẩm';
require 'layout/admin_header.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Sản phẩm</h1>
        <p class="page-desc">Quản lý thông tin, giá, thông số và hình ảnh sản phẩm.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('admin/product_form.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Thêm sản phẩm</a>
    </div>
</div>

<section class="panel">
    <form method="get" class="filter-bar">
        <label class="search-input"><i class="bi bi-search"></i><input name="q" value="<?= e($q) ?>" placeholder="Tìm theo tên hoặc SKU"></label>
        <select name="category" class="select">
            <option value="">Tất cả danh mục</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $catId == $c['id'] ? 'selected' : '' ?>><?= $c['parent_id'] ? '— ' : '' ?><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="brand" class="select">
            <option value="">Tất cả thương hiệu</option>
            <?php foreach ($brands as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $brandId == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="select">
            <option value="">Mọi trạng thái</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Đang bán</option>
            <option value="hidden" <?= $status === 'hidden' ? 'selected' : '' ?>>Đang ẩn</option>
        </select>
        <select name="stock" class="select">
            <option value="">Mọi tồn kho</option>
            <option value="low" <?= $stock === 'low' ? 'selected' : '' ?>>Sắp hết (≤ <?= LOW_STOCK ?>)</option>
            <option value="out" <?= $stock === 'out' ? 'selected' : '' ?>>Hết hàng</option>
        </select>
        <button class="btn btn-soft">Lọc</button>
        <?php if ($_GET): ?><a href="<?= url('admin/products.php') ?>" class="btn btn-outline">Xóa lọc</a><?php endif; ?>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Sản phẩm</th><th>Danh mục</th><th>Thương hiệu</th><th>Giá</th><th class="num">Tồn kho</th><th>Trạng thái</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td>
                        <div class="cell-product">
                            <img src="<?= img_url($p['image']) ?>" alt="" class="thumb" onerror="this.onerror=null;this.src='<?= img_url('') ?>'">
                            <div>
                                <a href="<?= url('admin/product_form.php?id=' . $p['id']) ?>"><b><?= e($p['name']) ?></b></a>
                                <div class="cell-sub">SKU: <?= e($p['sku']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= e($p['category_name']) ?></td>
                    <td><?= e($p['brand_name']) ?></td>
                    <td><?= money($p['price']) ?><?php $sp = sale_price($p); if ($sp < $p['price']): ?><div class="cell-sub text-success">KM: <?= money($sp) ?></div><?php endif; ?></td>
                    <td class="num"><?= stock_badge($p['stock_quantity']) ?><div class="cell-sub"><?= $p['stock_quantity'] ?></div></td>
                    <td><?= $p['status'] === 'active' ? '<span class="badge badge-success">Đang bán</span>' : '<span class="badge">Đang ẩn</span>' ?></td>
                    <td>
                        <div class="row-actions">
                            <a href="<?= url('admin/product_form.php?id=' . $p['id']) ?>" class="btn btn-sm btn-soft">Sửa</a>
                            <form method="post">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <?= back_field() ?>
                                <button class="btn btn-sm btn-outline" title="<?= $p['status'] === 'active' ? 'Ẩn khỏi cửa hàng' : 'Hiện lên cửa hàng' ?>">
                                    <i class="bi <?= $p['status'] === 'active' ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
                                </button>
                            </form>
                            <form method="post" data-confirm="Xóa sản phẩm &quot;<?= e($p['name']) ?>&quot;?">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <?= back_field() ?>
                                <button class="btn btn-sm btn-outline" title="Xóa"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$products): ?>
                <tr><td colspan="7" class="empty">Không có sản phẩm phù hợp.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="panel-foot">
        <span class="text-muted">Hiển thị <?= count($products) ?> / <?= $total ?> sản phẩm</span>
        <?= pagination_links($pg) ?>
    </div>
</section>

<?php require 'layout/admin_footer.php'; ?>
