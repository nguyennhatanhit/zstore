<?php
/** Thẻ sản phẩm dùng chung (trang chủ, danh sách, sản phẩm liên quan) */
$salePrice = sale_price($p);
?>
<article class="product-card">
    <a href="<?= url('product.php?id=' . $p['id']) ?>" class="product-thumb">
        <img src="<?= img_url($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy"
             onerror="this.onerror=null;this.src='<?= img_url('') ?>'">
        <?php if ($salePrice < $p['price']): ?>
            <span class="discount-tag">-<?= round(($p['price'] - $salePrice) / $p['price'] * 100) ?>%</span>
        <?php endif; ?>
    </a>
    <button type="button" class="quick-view-btn" data-quick-view="<?= $p['id'] ?>" title="Xem nhanh" aria-label="Xem nhanh <?= e($p['name']) ?>">
        <i class="bi bi-eye"></i>
    </button>
    <div class="product-body">
        <div class="product-meta"><?= $p['brand_name'] ? e($p['brand_name']) . ' · ' : '' ?><?= e($p['category_name']) ?></div>
        <h3 class="product-name"><a href="<?= url('product.php?id=' . $p['id']) ?>"><?= e($p['name']) ?></a></h3>
        <div class="product-price">
            <?= money($salePrice) ?>
            <?php if ($salePrice < $p['price']): ?><span class="price-old"><?= money($p['price']) ?></span><?php endif; ?>
        </div>
        <div class="product-foot">
            <?= stock_badge($p['stock_quantity']) ?>
            <form action="<?= url('cart.php') ?>" method="post" class="add-to-cart">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                <?= back_field() ?>
                <button class="btn btn-primary btn-sm" title="Thêm vào giỏ" <?= $p['stock_quantity'] > 0 ? '' : 'disabled' ?>>
                    <i class="bi bi-cart-plus"></i>
                </button>
            </form>
        </div>
    </div>
</article>
