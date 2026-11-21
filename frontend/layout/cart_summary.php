<?php /** Các dòng tiền trong tóm tắt giỏ hàng (trang giỏ và cart-api dùng chung) */ ?>
<div class="summary-row"><span>Tạm tính</span><b><?= money($cart['subtotal']) ?></b></div>
<?php if ($cart['saved'] > 0): ?>
    <div class="summary-row text-success"><span>Đã tiết kiệm (khuyến mãi)</span><span><?= money($cart['saved']) ?></span></div>
<?php endif; ?>
<?php if ($cart['discount'] > 0): ?>
    <div class="summary-row text-success"><span>Mã giảm giá</span><span>-<?= money($cart['discount']) ?></span></div>
<?php endif; ?>
<div class="summary-row"><span>Phí giao hàng</span><span><?= $cart['shipping'] ? money($cart['shipping']) : 'Miễn phí' ?></span></div>
<?php if ($cart['shipping']): ?>
    <p class="form-hint">Mua thêm <?= money(FREE_SHIPPING_FROM - $cart['subtotal']) ?> để được miễn phí giao hàng.</p>
<?php endif; ?>
<div class="summary-row summary-total"><span>Tổng cộng</span><b><?= money($cart['total']) ?></b></div>
