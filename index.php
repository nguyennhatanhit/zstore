<?php
/** TRANG CHỦ: banner, sản phẩm đang giảm giá, sản phẩm mới, laptop */
require 'config.php';

$newProducts = db_all(product_sql() . " WHERE p.status = 'active' ORDER BY p.created_at DESC, p.id DESC LIMIT 8");

// Sản phẩm đang có khuyến mãi tự động
$saleIds = array_keys(auto_promotions());
$saleProducts = $saleIds
    ? db_all(product_sql() . " WHERE p.status = 'active' AND p.id IN (" . placeholders($saleIds) . ") LIMIT 4", $saleIds)
    : [];

$laptops = db_all(product_sql() . " WHERE p.status = 'active' AND c.code = 'laptop' ORDER BY p.price LIMIT 4");

// Cấu hình Build PC khách đang chọn (lưu trong session) để hiện ở khung bên phải banner
$build = isset($_SESSION['build']) && is_array($_SESSION['build']) ? $_SESSION['build'] : [];
$buildIds = array_values(array_filter(array_map('intval', $build)));
$buildNames = $buildIds
    ? array_column(db_all('SELECT id, name, price FROM products WHERE id IN (' . placeholders($buildIds) . ')', $buildIds), null, 'id')
    : [];
$buildTotal = 0;
foreach ($buildNames as $bp) { $buildTotal += $bp['price']; }

$title = 'Trang chủ';
require 'frontend/layout/header.php';
?>

<section class="hero">
    <div class="container hero-inner">
        <div class="hero-copy">
            <h1>Linh kiện, PC và laptop, kiểm tra tương thích trước khi mua</h1>
            <p>Chọn từng linh kiện, hệ thống báo ngay chỗ không khớp: socket, loại RAM, kích thước case, công suất nguồn. Cần gợi ý thì hỏi AI theo ngân sách.</p>
            <div class="hero-actions">
                <a href="<?= url('build-pc.php') ?>" class="btn btn-primary btn-lg"><i class="bi bi-cpu"></i> Build PC ngay</a>
                <a href="<?= url('ai.php') ?>" class="btn btn-ghost-light btn-lg"><i class="bi bi-stars"></i> Hỏi AI tư vấn</a>
            </div>
        </div>

        <a href="<?= url('build-pc.php') ?>" class="build-sheet" aria-label="Mở Build PC">
            <div class="build-sheet-head">
                <b>Cấu hình của bạn</b>
                <span><?= count($buildNames) ?>/<?= count(build_slots()) ?> linh kiện</span>
            </div>
            <ol class="build-sheet-list">
                <?php foreach (build_slots() as $code => $label): ?>
                    <?php $chosen = isset($build[$code]) && isset($buildNames[(int) $build[$code]]) ? $buildNames[(int) $build[$code]] : null; ?>
                    <li class="<?= $chosen ? 'is-set' : '' ?>">
                        <i class="bi <?= category_icon($code) ?>"></i>
                        <span class="slot"><?= e($label) ?></span>
                        <span class="value"><?= $chosen ? e($chosen['name']) : 'Chưa chọn' ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
            <div class="build-sheet-foot">
                <?php if ($buildNames): ?>
                    <span>Tạm tính <b><?= money($buildTotal) ?></b></span><span class="go">Tiếp tục <i class="bi bi-arrow-right"></i></span>
                <?php else: ?>
                    <span>Chưa có linh kiện nào</span><span class="go">Bắt đầu chọn <i class="bi bi-arrow-right"></i></span>
                <?php endif; ?>
            </div>
        </a>
    </div>
</section>

<section class="container section cat-section">
    <div class="section-head"><h2>Mua theo danh mục</h2></div>
    <div class="cat-grid">
        <?php foreach ($categoryMenu as $c): ?>
            <a href="<?= url('products.php?category=' . $c['slug']) ?>" class="cat-tile">
                <i class="bi <?= category_icon($c['code']) ?>"></i><span><?= e($c['name']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($saleProducts): ?>
<section class="container section">
    <div class="section-head">
        <h2><i class="bi bi-lightning-charge text-warning"></i> Đang giảm giá</h2>
        <a href="<?= url('products.php') ?>" class="link">Xem tất cả</a>
    </div>
    <div class="product-grid">
        <?php foreach ($saleProducts as $p) { require 'frontend/layout/product_card.php'; } ?>
    </div>
</section>
<?php endif; ?>

<section class="container section">
    <div class="section-head">
        <h2>Sản phẩm mới</h2>
        <a href="<?= url('products.php?sort=newest') ?>" class="link">Xem tất cả</a>
    </div>
    <div class="product-grid">
        <?php foreach ($newProducts as $p) { require 'frontend/layout/product_card.php'; } ?>
    </div>
    <?php if (!$newProducts): ?><div class="empty">Chưa có sản phẩm nào.</div><?php endif; ?>
</section>

<?php if ($laptops): ?>
<section class="container section">
    <div class="section-head">
        <h2>Laptop</h2>
        <a href="<?= url('products.php?category=laptop') ?>" class="link">Xem tất cả</a>
    </div>
    <div class="product-grid">
        <?php foreach ($laptops as $p) { require 'frontend/layout/product_card.php'; } ?>
    </div>
</section>
<?php endif; ?>

<?php require 'frontend/layout/footer.php'; ?>
