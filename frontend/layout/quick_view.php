<?php /** Nội dung khung Xem nhanh sản phẩm (product-api.php vẽ rồi gửi về bằng AJAX) */ ?>
<div class="qv">
    <a href="<?= url('product.php?id=' . $product['id']) ?>" class="qv-img">
        <img src="<?= img_url($product['image']) ?>" alt="<?= e($product['name']) ?>" onerror="this.onerror=null;this.src='<?= img_url('') ?>'">
    </a>
    <div class="qv-info">
        <div class="product-meta"><?= e($product['category_name']) ?><?= $product['brand_name'] ? ' · ' . e($product['brand_name']) : '' ?></div>
        <h2 id="qv-title"><?= e($product['name']) ?></h2>
        <div class="pd-price">
            <span class="price-now"><?= money($salePrice) ?></span>
            <?php if ($salePrice < $product['price']): ?>
                <span class="price-old"><?= money($product['price']) ?></span>
                <span class="badge badge-danger">-<?= round(($product['price'] - $salePrice) / $product['price'] * 100) ?>%</span>
            <?php endif; ?>
        </div>
        <div><?= stock_badge($product['stock_quantity']) ?></div>
        <?php if ($product['short_desc']): ?><p class="qv-short"><?= e($product['short_desc']) ?></p><?php endif; ?>
        <?php if ($specs): ?>
            <ul class="qv-specs">
                <?php foreach ($specs as $s): ?>
                    <li><span><?= e($s['name']) ?></span><b><?= e($s['value']) ?><?= $s['unit'] ? ' ' . e($s['unit']) : '' ?></b></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <div class="qv-actions">
            <?php if ($product['stock_quantity'] > 0): ?>
                <form action="<?= url('cart.php') ?>" method="post" class="add-to-cart">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    <button class="btn btn-primary"><i class="bi bi-cart-plus"></i> Thêm vào giỏ</button>
                </form>
            <?php endif; ?>
            <a href="<?= url('product.php?id=' . $product['id']) ?>" class="btn btn-outline">Xem đầy đủ</a>
        </div>
    </div>
</div>
