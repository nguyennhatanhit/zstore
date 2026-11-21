<?php /** Ô mã giảm giá trong giỏ hàng (trang giỏ và cart-api dùng chung) */ ?>
<?php if ($cart['coupon']): ?>
    <form method="post" class="coupon-applied">
        <input type="hidden" name="action" value="remove_coupon">
        <span><i class="bi bi-ticket-perforated"></i> <b><?= e($cart['coupon']['code']) ?></b> · <?= e($cart['coupon']['name']) ?></span>
        <button class="icon-btn" title="Bỏ mã"><i class="bi bi-x-lg"></i></button>
    </form>
<?php else: ?>
    <form method="post" class="coupon-form">
        <input type="hidden" name="action" value="apply_coupon">
        <input name="coupon" class="input" placeholder="Mã giảm giá" required>
        <button class="btn btn-soft">Áp dụng</button>
    </form>
<?php endif; ?>
