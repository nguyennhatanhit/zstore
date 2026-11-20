<?php
/** ĐỔI MẬT KHẨU - [CN 3] Quản lý thông tin tài khoản  ✅ ĐÃ XONG */
require '../../config.php';
require_login();

$user   = db_one("SELECT * FROM users WHERE id = ?", [current_user()['id']]);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = post_str('current_password', '', false);
    $new     = post_str('new_password', '', false);
    $confirm = post_str('confirm_password', '', false);

    if (!password_verify($current, $user['password_hash'])) {
        $errors['current_password'] = 'Mật khẩu hiện tại không đúng.';
    }
    if (strlen($new) < 6) {
        $errors['new_password'] = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
    } elseif ($new === $current) {
        $errors['new_password'] = 'Mật khẩu mới phải khác mật khẩu hiện tại.';
    }
    if ($confirm !== $new) {
        $errors['confirm_password'] = 'Mật khẩu nhập lại không khớp.';
    }

    if (!$errors) {
        db_run("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        set_flash('success', 'Đổi mật khẩu thành công.');
        redirect('change_password.php');
    }
}

$title = 'Đổi mật khẩu';
require '../layout/header.php';
?>

<section class="container section">
    <div class="account-layout">
        <?php require '../layout/account_sidebar.php'; ?>

        <div class="card">
            <div class="card-head">
                <h1 class="card-title">Đổi mật khẩu</h1>
                <p class="text-muted">Nên dùng mật khẩu mạnh và không trùng với trang web khác.</p>
            </div>

            <form method="post" class="form form-narrow" novalidate>
                <div class="form-group">
                    <label for="current_password">Mật khẩu hiện tại</label>
                    <input type="password" id="current_password" name="current_password" class="input <?= invalid($errors, 'current_password') ?>">
                    <?= show_error($errors, 'current_password') ?>
                </div>
                <div class="form-group">
                    <label for="new_password">Mật khẩu mới</label>
                    <input type="password" id="new_password" name="new_password" class="input <?= invalid($errors, 'new_password') ?>">
                    <?= show_error($errors, 'new_password') ?: '<div class="form-hint">Ít nhất 6 ký tự</div>' ?>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Nhập lại mật khẩu mới</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="input <?= invalid($errors, 'confirm_password') ?>">
                    <?= show_error($errors, 'confirm_password') ?>
                </div>
                <div><button type="submit" class="btn btn-primary">Cập nhật mật khẩu</button></div>
            </form>
        </div>
    </div>
</section>

<?php require '../layout/footer.php'; ?>
