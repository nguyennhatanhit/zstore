<?php
/** ĐĂNG KÝ - [CN 1] Đăng ký tài khoản  ✅ ĐÃ XONG */
require '../../config.php';

if (current_user()) {
    redirect('index.php');
}

$errors = [];
$form = ['full_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['full_name'] = post_str('full_name');
    $form['email']     = strtolower(post_str('email'));
    $form['phone']     = post_str('phone');
    $password          = post_str('password', '', false);
    $confirm           = post_str('password_confirm', '', false);

    // Kiểm tra dữ liệu
    if ($form['full_name'] === '') {
        $errors['full_name'] = 'Vui lòng nhập họ tên.';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email không hợp lệ.';
    } elseif (db_value("SELECT COUNT(*) FROM users WHERE email = ?", [$form['email']]) > 0) {
        $errors['email'] = 'Email này đã được đăng ký.';
    }
    if ($form['phone'] !== '' && !is_valid_phone($form['phone'])) {
        $errors['phone'] = 'Số điện thoại không hợp lệ (VD: 0912345678).';
    }
    if (strlen($password) < 6) {
        $errors['password'] = 'Mật khẩu phải có ít nhất 6 ký tự.';
    }
    if ($confirm !== $password) {
        $errors['password_confirm'] = 'Mật khẩu nhập lại không khớp.';
    }

    // Hợp lệ -> tạo tài khoản và đăng nhập luôn
    if (!$errors) {
        db_run(
            "INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, 'customer')",
            [$form['full_name'], $form['email'], $form['phone'] ?: null, password_hash($password, PASSWORD_DEFAULT)]
        );
        login_user(db_one("SELECT * FROM users WHERE id = ?", [db_last_id()]));
        set_flash('success', 'Đăng ký thành công! Chào mừng bạn đến với ' . APP_NAME . '.');
        redirect('account.php');
    }
}

$title = 'Đăng ký tài khoản';
require '../layout/header.php';
?>

<section class="container section">
    <div class="auth-card card">
        <h1>Đăng ký tài khoản</h1>
        <p class="text-muted">Tạo tài khoản để đặt hàng và theo dõi đơn hàng.</p>

        <form method="post" class="form" novalidate>
            <div class="form-group">
                <label for="full_name">Họ tên <span class="req">*</span></label>
                <input type="text" id="full_name" name="full_name" class="input <?= invalid($errors, 'full_name') ?>"
                       value="<?= e($form['full_name']) ?>" autofocus>
                <?= show_error($errors, 'full_name') ?>
            </div>

            <div class="form-group">
                <label for="email">Email <span class="req">*</span></label>
                <input type="email" id="email" name="email" class="input <?= invalid($errors, 'email') ?>"
                       value="<?= e($form['email']) ?>">
                <?= show_error($errors, 'email') ?>
            </div>

            <div class="form-group">
                <label for="phone">Số điện thoại</label>
                <input type="tel" id="phone" name="phone" class="input <?= invalid($errors, 'phone') ?>"
                       value="<?= e($form['phone']) ?>" placeholder="0912345678">
                <?= show_error($errors, 'phone') ?>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Mật khẩu <span class="req">*</span></label>
                    <input type="password" id="password" name="password" class="input <?= invalid($errors, 'password') ?>">
                    <?= show_error($errors, 'password') ?: '<div class="form-hint">Ít nhất 6 ký tự</div>' ?>
                </div>
                <div class="form-group">
                    <label for="password_confirm">Nhập lại mật khẩu <span class="req">*</span></label>
                    <input type="password" id="password_confirm" name="password_confirm" class="input <?= invalid($errors, 'password_confirm') ?>">
                    <?= show_error($errors, 'password_confirm') ?>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Đăng ký</button>
        </form>

        <p class="auth-switch">Đã có tài khoản? <a href="<?= url('login.php') ?>" class="link">Đăng nhập</a></p>
    </div>
</section>

<?php require '../layout/footer.php'; ?>
