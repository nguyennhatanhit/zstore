<?php
/**
 * CHI TIẾT SẢN PHẨM
 *   [CN 7] Xem chi tiết sản phẩm và bảng thông số kỹ thuật
 *   [CN 8] Xem tình trạng tồn kho
 */
require '../../config.php';

$id = (int) get_str('id', '0');
$product = db_one(product_sql() . " WHERE p.id = ? AND p.status = 'active'", [$id]);

if (!$product) {
    http_response_code(404);
    $title = 'Không tìm thấy sản phẩm';
    require '../layout/header.php';
    echo '<section class="container section"><div class="empty-state card"><i class="bi bi-box"></i>'
       . '<h3>Không tìm thấy sản phẩm</h3><p class="text-muted">Sản phẩm không tồn tại hoặc đã ngừng kinh doanh.</p>'
       . '<a href="' . url('products.php') . '" class="btn btn-primary">Xem sản phẩm khác</a></div></section>';
    require '../layout/footer.php';
    exit;
}

$images = db_all("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order, id", [$id]);
$specs  = db_all("
    SELECT sd.name, sd.unit, ps.value
    FROM product_specs ps JOIN spec_definitions sd ON sd.id = ps.spec_id
    WHERE ps.product_id = ? ORDER BY sd.sort_order", [$id]);
// Sản phẩm cùng danh mục, giá gần nhất
$related = db_all(product_sql() . " WHERE p.status = 'active' AND p.category_id = ? AND p.id <> ? ORDER BY ABS(p.price - ?) LIMIT 4",
    [$product['category_id'], $id, $product['price']]);

$salePrice = sale_price($product);
$inCompare = in_array($id, isset($_SESSION['compare']) ? $_SESSION['compare'] : []);

$title = $product['name'];
require '../layout/header.php';
?>

<section class="container section">
    <nav class="breadcrumbs">
        <a href="<?= url('index.php') ?>">Trang chủ</a> <i class="bi bi-chevron-right"></i>
        <a href="<?= url('products.php?category=' . $product['category_slug']) ?>"><?= e($product['category_name']) ?></a>
        <i class="bi bi-chevron-right"></i> <span><?= e($product['name']) ?></span>
    </nav>

    <div class="pd-layout">
        <div class="card pd-gallery" data-gallery>
            <div class="gallery-main">
                <img src="<?= img_url($images ? $images[0]['image_url'] : '') ?>" alt="<?= e($product['name']) ?>"
                     onerror="this.onerror=null;this.src='<?= img_url('') ?>'" data-gallery-main>
            </div>
            <?php if (count($images) > 1): ?>
                <div class="gallery-thumbs">
                    <?php foreach ($images as $i => $img): ?>
                        <img src="<?= img_url($img['image_url']) ?>" alt="" class="<?= $i === 0 ? 'active' : '' ?>"
                             onerror="this.onerror=null;this.src='<?= img_url('') ?>'" data-gallery-thumb>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="pd-info">
            <div class="product-meta"><?= $product['brand_name'] ? e($product['brand_name']) . ' · ' : '' ?>SKU: <?= e($product['sku']) ?></div>
            <h1><?= e($product['name']) ?></h1>
            <?php if ($product['short_desc']): ?><p class="text-muted"><?= e($product['short_desc']) ?></p><?php endif; ?>

            <div class="pd-price">
                <span class="price-now"><?= money($salePrice) ?></span>
                <?php if ($salePrice < $product['price']): ?>
                    <span class="price-old"><?= money($product['price']) ?></span>
                    <span class="badge badge-danger">-<?= round(($product['price'] - $salePrice) / $product['price'] * 100) ?>%</span>
                <?php endif; ?>
            </div>

            <ul class="pd-meta">
                <li><i class="bi bi-box-seam"></i> Tình trạng: <?= stock_badge($product['stock_quantity']) ?>
                    <?php if ($product['stock_quantity'] > 0): ?><span class="text-muted">(còn <?= $product['stock_quantity'] ?> sản phẩm)</span><?php endif; ?></li>
                <li><i class="bi bi-shield-check"></i> Bảo hành: <?= (int) $product['warranty_months'] ?> tháng</li>
                <li><i class="bi bi-truck"></i> Miễn phí giao hàng cho đơn từ <?= money(FREE_SHIPPING_FROM) ?></li>
            </ul>

            <?php if ($product['stock_quantity'] > 0): ?>
                <form action="<?= url('cart.php') ?>" method="post" class="pd-buy add-to-cart">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= $id ?>">
                    <?= back_field() ?>
                    <input type="number" name="quantity" value="1" min="1" max="<?= $product['stock_quantity'] ?>" class="input qty-input" aria-label="Số lượng">
                    <button class="btn btn-primary btn-lg"><i class="bi bi-cart-plus"></i> Thêm vào giỏ</button>
                    <button class="btn btn-soft btn-lg" name="buy_now" value="1">Mua ngay</button>
                </form>
            <?php else: ?>
                <div class="notice notice-warning"><i class="bi bi-exclamation-circle"></i> Sản phẩm tạm hết hàng.</div>
            <?php endif; ?>

            <form action="<?= url('compare.php') ?>" method="post" class="mt-8 compare-toggle">
                <input type="hidden" name="action" value="<?= $inCompare ? 'remove' : 'add' ?>">
                <input type="hidden" name="product_id" value="<?= $id ?>">
                <?= back_field() ?>
                <button class="btn btn-outline"><i class="bi bi-arrow-left-right"></i> <span><?= $inCompare ? 'Bỏ khỏi so sánh' : 'Thêm vào so sánh' ?></span></button>
            </form>
        </div>
    </div>

    <div class="pd-detail<?= $product['description'] ? '' : ' pd-detail-single' ?>">
        <section class="card">
            <h2 class="card-title">Thông số kỹ thuật</h2>
            <?php if ($specs): ?>
                <table class="spec-table">
                    <?php foreach ($specs as $s): ?>
                        <tr><th><?= e($s['name']) ?></th><td><?= e($s['value']) ?><?= $s['unit'] ? ' ' . e($s['unit']) : '' ?></td></tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p class="text-muted">Chưa cập nhật thông số.</p>
            <?php endif; ?>
        </section>
        <?php if ($product['description']): ?>
        <section class="card">
            <h2 class="card-title">Mô tả sản phẩm</h2>
            <div class="pd-description"><?= nl2br(e($product['description'])) ?></div>
        </section>
        <?php endif; ?>
    </div>

    <?php if ($related): ?>
        <div class="section-head mt-32"><h2>Sản phẩm cùng loại</h2></div>
        <div class="product-grid">
            <?php foreach ($related as $p) { require '../layout/product_card.php'; } ?>
        </div>
    <?php endif; ?>
</section>

<?php require '../layout/footer.php'; ?>
