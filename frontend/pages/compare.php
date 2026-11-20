<?php
/** SO SÁNH SẢN PHẨM - [CN 9] Thêm, xóa sản phẩm trong danh sách so sánh (tối đa 4) */
require '../../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_str('action', '');
    $productId = (int) post_str('product_id', '0');

    if ($action === 'add') {
        $error = compare_add($productId);
        set_flash($error ? 'error' : 'success', $error ?: 'Đã thêm vào danh sách so sánh (' . compare_count() . '/4).');
    } elseif ($action === 'remove') {
        compare_remove($productId);
        set_flash('success', 'Đã bỏ sản phẩm khỏi danh sách so sánh.');
    } elseif ($action === 'clear') {
        unset($_SESSION['compare']);
        set_flash('success', 'Đã xóa danh sách so sánh.');
    }
    redirect_back('compare.php');
}

$ids = isset($_SESSION['compare']) ? $_SESSION['compare'] : [];
$products = [];
$rows = [];

if ($ids) {
    // Giữ đúng thứ tự người dùng thêm vào
    $found = db_all(product_sql() . " WHERE p.id IN (" . placeholders($ids) . ")", $ids);
    $byId = array_column($found, null, 'id');
    foreach ($ids as $pid) {
        if (isset($byId[$pid])) {
            $products[] = $byId[$pid];
        }
    }

    $specRows = db_all("
        SELECT ps.product_id, sd.name, sd.unit, ps.value
        FROM product_specs ps JOIN spec_definitions sd ON sd.id = ps.spec_id
        WHERE ps.product_id IN (" . placeholders($ids) . ")
        ORDER BY sd.category_id, sd.sort_order", $ids);
    foreach ($specRows as $s) {
        $rows[$s['name']][$s['product_id']] = $s['value'] . ($s['unit'] ? ' ' . $s['unit'] : '');
    }
}
$categories = array_unique(array_column($products, 'category_name'));

$title = 'So sánh sản phẩm';
require '../layout/header.php';
?>

<section class="container section">
    <div class="page-heading page-heading-row">
        <div>
            <h1>So sánh sản phẩm</h1>
            <p class="text-muted">So sánh tối đa 4 sản phẩm. Bấm "Thêm vào so sánh" ở trang chi tiết sản phẩm.</p>
        </div>
        <?php if ($products): ?>
            <form method="post" data-confirm="Xóa toàn bộ danh sách so sánh?">
                <input type="hidden" name="action" value="clear">
                <button class="btn btn-outline"><i class="bi bi-trash"></i> Xóa tất cả</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (!$products): ?>
        <div class="empty-state card">
            <i class="bi bi-arrow-left-right"></i>
            <h3>Chưa có sản phẩm để so sánh</h3>
            <a href="<?= url('products.php') ?>" class="btn btn-primary">Xem sản phẩm</a>
        </div>
    <?php else: ?>
        <?php if (count($categories) > 1): ?>
            <div class="notice notice-warning"><i class="bi bi-info-circle"></i> Các sản phẩm thuộc nhiều danh mục khác nhau (<?= e(implode(', ', $categories)) ?>), nên so sánh sản phẩm cùng loại để dễ đánh giá.</div>
        <?php endif; ?>

        <div class="card table-wrap no-pad">
            <table class="table compare-table">
                <tr>
                    <th></th>
                    <?php foreach ($products as $p): ?>
                        <td class="compare-product">
                            <form method="post" class="compare-remove">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <button class="icon-btn" title="Bỏ khỏi so sánh"><i class="bi bi-x-lg"></i></button>
                            </form>
                            <img src="<?= img_url($p['image']) ?>" alt="" onerror="this.onerror=null;this.src='<?= img_url('') ?>'">
                            <a href="<?= url('product.php?id=' . $p['id']) ?>" class="compare-name"><?= e($p['name']) ?></a>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <th>Giá</th>
                    <?php foreach ($products as $p): ?><td class="product-price"><?= money(sale_price($p)) ?></td><?php endforeach; ?>
                </tr>
                <tr><th>Thương hiệu</th><?php foreach ($products as $p): ?><td><?= e($p['brand_name']) ?></td><?php endforeach; ?></tr>
                <tr><th>Danh mục</th><?php foreach ($products as $p): ?><td><?= e($p['category_name']) ?></td><?php endforeach; ?></tr>
                <tr><th>Tồn kho</th><?php foreach ($products as $p): ?><td><?= stock_badge($p['stock_quantity']) ?></td><?php endforeach; ?></tr>
                <tr><th>Bảo hành</th><?php foreach ($products as $p): ?><td><?= (int) $p['warranty_months'] ?> tháng</td><?php endforeach; ?></tr>
                <?php foreach ($rows as $name => $values): ?>
                    <?php $distinct = count(array_unique($values)) > 1 || count($values) < count($products); ?>
                    <tr class="<?= $distinct ? 'row-diff' : '' ?>">
                        <th><?= e($name) ?></th>
                        <?php foreach ($products as $p): ?>
                            <td><?= isset($values[$p['id']]) ? e($values[$p['id']]) : '<span class="text-muted">–</span>' ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <th></th>
                    <?php foreach ($products as $p): ?>
                        <td>
                            <form action="<?= url('cart.php') ?>" method="post">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <?= back_field() ?>
                                <button class="btn btn-primary btn-sm" <?= $p['stock_quantity'] > 0 ? '' : 'disabled' ?>><i class="bi bi-cart-plus"></i> Thêm vào giỏ</button>
                            </form>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </table>
        </div>
        <p class="text-muted mt-8"><span class="legend-diff"></span> Dòng tô màu là thông số khác nhau giữa các sản phẩm.</p>
    <?php endif; ?>
</section>

<?php require '../layout/footer.php'; ?>
