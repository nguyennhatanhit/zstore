<?php /** Các dòng linh kiện của Build PC (trang build-pc và AJAX dùng chung) */ ?>
<?php foreach ($slots as $code => $name): $p = isset($selected[$code]) ? $selected[$code] : null; ?>
    <div class="build-row <?= $p ? 'chosen' : '' ?>">
        <div class="build-slot"><?= e($name) ?></div>
        <?php if ($p): ?>
            <div class="build-product">
                <img src="<?= img_url($p['image']) ?>" alt="" onerror="this.onerror=null;this.src='<?= img_url('') ?>'">
                <div>
                    <a href="<?= url('product.php?id=' . $p['id']) ?>"><b><?= e($p['name']) ?></b></a>
                    <div class="cell-sub">
                        <?php foreach ($keySpecs as $k) { if (isset($p['specs'][$k])) echo '<span class="tag">' . e($k) . ': ' . e($p['specs'][$k]) . '</span> '; } ?>
                    </div>
                </div>
                <b class="build-price"><?= money($p['sale_price']) ?></b>
                <form method="post">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="slot" value="<?= $code ?>">
                    <button class="icon-btn" title="Bỏ chọn"><i class="bi bi-x-lg"></i></button>
                </form>
            </div>
        <?php else: ?>
            <form method="post" class="build-select">
                <input type="hidden" name="action" value="set">
                <input type="hidden" name="slot" value="<?= $code ?>">
                <select name="product_id" class="select" data-build-select <?= $options[$code] ? '' : 'disabled' ?>>
                    <option value=""><?= $options[$code] ? '+ Chọn ' . e(mb_strtolower($name)) : 'Chưa có sản phẩm còn hàng' ?></option>
                    <?php foreach ($options[$code] as $o): ?>
                        <option value="<?= $o['id'] ?>"><?= e($o['name']) ?> — <?= money(sale_price($o)) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
