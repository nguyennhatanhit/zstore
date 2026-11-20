<?php
/** TÀI KHOẢN - [CN 3] Quản lý thông tin tài khoản (họ tên, SĐT, địa chỉ)  ✅ ĐÃ XONG */
require '../../config.php';
require_login();

$user   = db_one("SELECT * FROM users WHERE id = ?", [current_user()['id']]);
$errors = [];
$form   = $user;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['full_name'] = post_str('full_name');
    $form['phone']     = post_str('phone');
    $form['address']   = post_str('address');

    if ($form['full_name'] === '') {
        $errors['full_name'] = 'Vui lòng nhập họ tên.';
    }
    if ($form['phone'] !== '' && !is_valid_phone($form['phone'])) {
        $errors['phone'] = 'Số điện thoại không hợp lệ (VD: 0912345678).';
    }

    if (!$errors) {
        db_run(
            "UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?",
            [$form['full_name'], $form['phone'] ?: null, $form['address'] ?: null, $user['id']]
        );
        $_SESSION['user']['full_name'] = $form['full_name'];
        set_flash('success', 'Đã cập nhật thông tin tài khoản.');
        redirect('account.php');
    }
}

$title = 'Tài khoản của tôi';
require '../layout/header.php';
?>

<section class="container section">
    <div class="account-layout">
        <?php require '../layout/account_sidebar.php'; ?>

        <div class="card">
            <div class="card-head">
                <h1 class="card-title">Thông tin tài khoản</h1>
                <p class="text-muted">Thông tin này được dùng để điền sẵn khi bạn đặt hàng.</p>
            </div>

            <form method="post" class="form" novalidate>
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Họ tên <span class="req">*</span></label>
                        <input type="text" id="full_name" name="full_name" class="input <?= invalid($errors, 'full_name') ?>"
                               value="<?= e($form['full_name']) ?>">
                        <?= show_error($errors, 'full_name') ?>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" class="input" value="<?= e($user['email']) ?>" disabled>
                        <div class="form-hint">Email dùng để đăng nhập, không thể thay đổi.</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="phone">Số điện thoại</label>
                    <input type="tel" id="phone" name="phone" class="input <?= invalid($errors, 'phone') ?>"
                           value="<?= e($form['phone']) ?>" placeholder="0912345678">
                    <?= show_error($errors, 'phone') ?>
                </div>

                <div class="form-group">
                    <label for="address">Địa chỉ giao hàng</label>
                    <textarea id="address" name="address" class="textarea" rows="3"
                              placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành"><?= e($form['address']) ?></textarea>
                </div>

                <div class="form-foot">
                    <span class="text-muted">Thành viên từ <?= format_date($user['created_at'], 'd/m/Y') ?></span>
                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>
</section>

<?php require '../layout/footer.php'; ?>
