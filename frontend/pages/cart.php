<?php
/**
 * GIỎ HÀNG
 *   [CN 10] Thêm, xóa sản phẩm khỏi giỏ hàng
 *   [CN 11] Thay đổi số lượng
 *   [CN 12] Tính tổng giá trị giỏ hàng (giá khuyến mãi, mã giảm giá, phí giao hàng)
 */
require '../../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_str('action', '');

    switch ($action) {
        case 'add':
            $error = cart_add(post_str('product_id'), post_str('quantity', '1'));
            set_flash($error ? 'warning' : 'success', $error ?: 'Đã thêm sản phẩm vào giỏ hàng.');
            if (!empty($_POST['buy_now']) && cart_count() > 0) {
                redirect('checkout.php');
            }
            redirect_back('cart.php');
            break;

        case 'update':
            $messages = [];
            foreach (post_arr('quantities') as $productId => $qty) {
                if ($msg = cart_update($productId, $qty)) {
                    $messages[] = $msg;
                }
            }
            set_flash($messages ? 'warning' : 'success', $messages ? $messages[0] : 'Đã cập nhật giỏ hàng.');
            break;

        case 'remove':
            cart_remove(post_str('product_id', '0'));
            set_flash('success', 'Đã xóa sản phẩm khỏi giỏ hàng.');
            break;

        case 'clear':
            cart_clear();
            set_flash('success', 'Đã xóa toàn bộ giỏ hàng.');
            break;

        case 'apply_coupon':
            $code = strtoupper(post_str('coupon'));
            list($promo, $error) = find_coupon($code, cart_details());
            if ($error) {
                set_flash('error', $error);
            } else {
                $_SESSION['coupon'] = $code;
                set_flash('success', 'Đã áp dụng mã ' . $code . '.');
            }
            break;

        case 'remove_coupon':
            unset($_SESSION['coupon']);
            set_flash('success', 'Đã bỏ mã giảm giá.');
            break;
    }
    redirect('cart.php');
}

$cart = cart_totals();
if ($cart['coupon_error']) {
    set_flash('warning', 'Mã giảm giá đã bị gỡ: ' . $cart['coupon_error']);
}

$title = 'Giỏ hàng';
require '../layout/header.php';
?>

<section class="container section">
    <div class="page-heading">
        <h1>Giỏ hàng</h1>
        <p class="text-muted"><span data-cart-count-text><?= $cart['count'] ?></span> sản phẩm</p>
    </div>

    <?php if (!$cart['items']): ?>
        <div class="empty-state card">
            <i class="bi bi-cart3"></i>
            <h3>Giỏ hàng đang trống</h3>
            <p class="text-muted">Hãy chọn vài sản phẩm hoặc tự build một bộ PC.</p>
            <div class="form-actions center">
                <a href="<?= url('products.php') ?>" class="btn btn-primary">Mua sắm ngay</a>
                <a href="<?= url('build-pc.php') ?>" class="btn btn-soft">Build PC</a>
            </div>
        </div>
    <?php else: ?>
        <div class="cart-layout">
            <div class="card no-pad">
                <form method="post" id="cart-form">
                    <input type="hidden" name="action" value="update">
                    <div class="table-wrap">
                        <table class="table cart-table">
                            <thead><tr><th>Sản phẩm</th><th>Đơn giá</th><th>Số lượng</th><th>Thành tiền</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($cart['items'] as $item): ?>
                                <tr data-cart-row="<?= $item['id'] ?>">
                                    <td>
                                        <div class="cart-product">
                                            <img src="<?= img_url($item['image']) ?>" alt="" onerror="this.onerror=null;this.src='<?= img_url('') ?>'">
                                            <div>
                                                <a href="<?= url('product.php?id=' . $item['id']) ?>"><b><?= e($item['name']) ?></b></a>
                                                <div class="cell-sub"><?= e($item['category_name']) ?> · còn <?= $item['stock_quantity'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?= money($item['sale_price']) ?>
                                        <?php if ($item['sale_price'] < $item['price']): ?><div class="price-old"><?= money($item['price']) ?></div><?php endif; ?>
                                    </td>
                                    <td><input type="number" name="quantities[<?= $item['id'] ?>]" value="<?= $item['quantity'] ?>" min="0" max="<?= $item['stock_quantity'] ?>" class="input qty-input" data-cart-qty="<?= $item['id'] ?>"></td>
                                    <td><b data-line-total><?= money($item['line_total']) ?></b></td>
                                    <td><button form="remove-<?= $item['id'] ?>" class="icon-btn" title="Xóa"><i class="bi bi-trash"></i></button></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
                <?php foreach ($cart['items'] as $item): ?>
                    <form method="post" id="remove-<?= $item['id'] ?>" class="hidden cart-remove">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                    </form>
                <?php endforeach; ?>
                <div class="cart-actions">
                    <a href="<?= url('products.php') ?>" class="btn btn-outline"><i class="bi bi-arrow-left"></i> Tiếp tục mua</a>
                    <div class="form-actions">
                        <form method="post" data-confirm="Xóa toàn bộ giỏ hàng?">
                            <input type="hidden" name="action" value="clear">
                            <button class="btn btn-outline">Xóa giỏ hàng</button>
                        </form>
                        <button form="cart-form" class="btn btn-soft"><i class="bi bi-arrow-repeat"></i> Cập nhật số lượng</button>
                    </div>
                </div>
            </div>

            <aside class="card summary">
                <h2 class="card-title">Tóm tắt đơn hàng</h2>

                <div class="summary-rows" id="cart-coupon">
                    <?php require '../layout/cart_coupon.php'; ?>
                </div>

                <div class="summary-rows" id="cart-summary">
                    <?php require '../layout/cart_summary.php'; ?>
                </div>

                <a href="<?= url('checkout.php') ?>" class="btn btn-primary btn-block btn-lg">Tiến hành đặt hàng</a>
                <?php if (!current_user()): ?><p class="form-hint center">Bạn cần đăng nhập để đặt hàng.</p><?php endif; ?>
            </aside>
        </div>
    <?php endif; ?>
</section>

<?php require '../layout/footer.php'; ?>

