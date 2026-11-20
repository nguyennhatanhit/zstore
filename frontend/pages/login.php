<?php
/** ĐĂNG NHẬP - [CN 2] Đăng nhập (chặn tài khoản bị khóa)  ✅ ĐÃ XONG */
require '../../config.php';

if (current_user()) {
    redirect(is_admin() ? 'admin/index.php' : 'index.php');
}

$error = '';
$email = strtolower(get_str('email'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(post_str('email'));
    $password = post_str('password', '', false);

    $user = db_one("SELECT * FROM users WHERE email = ?", [$email]);

    if ($email === '' || $password === '') {
        $error = 'Vui lòng nhập email và mật khẩu.';
    } elseif (rate_count('login', $email, 15) >= 5 || rate_count('login_ip', client_ip(), 15) >= 20) {
        // Chống dò mật khẩu: 5 lần sai / tài khoản hoặc 20 lần sai / địa chỉ IP trong 15 phút
        $error = 'Bạn đã nhập sai quá nhiều lần. Vui lòng thử lại sau 15 phút.';
    } elseif (!$user || !password_verify($password, $user['password_hash'])) {
        rate_hit('login', $email);
        rate_hit('login_ip', client_ip());
        $error = 'Email hoặc mật khẩu không đúng.';
    } elseif ($user['status'] !== 'active') {
        $error = 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ cửa hàng.';
    } else {
        rate_clear('login', $email);
        login_user($user);
        set_flash('success', 'Xin chào ' . $user['full_name'] . '!');
        redirect($user['role'] === 'admin' ? 'admin/index.php' : 'index.php');
    }
}

$title = 'Đăng nhập';
require '../layout/header.php';
?>

<section class="container section">
    <div class="auth-card card">
        <h1>Đăng nhập</h1>
        <p class="text-muted">Chào mừng bạn quay lại <?= APP_NAME ?>.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i><span><?= e($error) ?></span></div>
        <?php endif; ?>

        <form method="post" class="form">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="input" value="<?= e($email) ?>" autocomplete="username" required <?= $email ? '' : 'autofocus' ?>>
            </div>
            <div class="form-group">
                <div class="label-row">
                    <label for="password">Mật khẩu</label>
                    <a href="<?= url('forgot_password.php') ?><?= $email ? '?email=' . urlencode($email) : '' ?>" class="link">Quên mật khẩu?</a>
                </div>
                <div class="input-pw">
                    <input type="password" id="password" name="password" class="input" autocomplete="current-password" required <?= $email ? 'autofocus' : '' ?>>
                    <button type="button" class="pw-toggle" aria-label="Hiện mật khẩu"><i class="bi bi-eye"></i></button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Đăng nhập</button>
        </form>

        <p class="auth-switch">Chưa có tài khoản? <a href="<?= url('register.php') ?>" class="link">Đăng ký</a></p>
    </div>
</section>

<?php require '../layout/footer.php'; ?>
