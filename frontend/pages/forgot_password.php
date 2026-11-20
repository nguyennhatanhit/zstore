<?php
/** QUÊN MẬT KHẨU - [CN 2] Đăng nhập (khôi phục quyền truy cập) */
require '../../config.php';

if (current_user()) {
    redirect(is_admin() ? 'admin/index.php' : 'index.php');
}

const OTP_TTL      = 600;
const OTP_RESEND   = 60;
const OTP_TRIES    = 5;
const RESET_TTL    = 600;

/** k***@gmail.com */
function mask_email($email)
{
    [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
    return mb_substr($name, 0, 1) . str_repeat('*', max(3, mb_strlen($name) - 1)) . '@' . $domain;
}

/** Tạo mã và gửi email. Trả về '' nếu thành công, ngược lại là câu báo lỗi. */
function otp_send($email)
{
    if (rate_count('otp_send', $email, 15) >= 3 || rate_count('otp_send_ip', client_ip(), 15) >= 10) {
        return 'Bạn đã yêu cầu gửi mã quá nhiều lần. Vui lòng thử lại sau 15 phút.';
    }
    rate_hit('otp_send', $email);
    rate_hit('otp_send_ip', client_ip());

    $user = db_one("SELECT id, full_name, email, status FROM users WHERE email = ?", [$email]);
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    if ($user && $user['status'] === 'active') {
        $html = mail_layout('Mã đặt lại mật khẩu',
            '<p style="margin:0 0 16px;line-height:1.6">Xin chào ' . e($user['full_name']) . ',<br>Dùng mã dưới đây để đặt lại mật khẩu tài khoản ' . e(APP_NAME) . ' của bạn.</p>'
            . '<div style="margin:0 0 16px;padding:16px;text-align:center;background:#eff6ff;border-radius:12px;font-size:32px;font-weight:bold;letter-spacing:10px;color:#2563eb">' . $code . '</div>'
            . '<p style="margin:0 0 8px;color:#64748b;font-size:13px;line-height:1.6">Mã có hiệu lực trong 10 phút. Không chia sẻ mã này cho bất kỳ ai, kể cả nhân viên cửa hàng.</p>'
            . '<p style="margin:0;color:#64748b;font-size:13px;line-height:1.6">Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua email này. Mật khẩu hiện tại vẫn giữ nguyên.</p>');

        if (!send_mail($user['email'], $code . ' là mã đặt lại mật khẩu ' . APP_NAME, $html)) {
            return 'Chưa gửi được email lúc này. Vui lòng thử lại sau ít phút hoặc gọi hotline ' . SHOP_HOTLINE . '.';
        }
    }

    session_regenerate_id(true);
    $_SESSION['pw_reset'] = [
        'email'    => $email,
        'uid'      => ($user && $user['status'] === 'active') ? (int) $user['id'] : 0,
        'hash'     => password_hash($code, PASSWORD_DEFAULT),
        'exp'      => time() + OTP_TTL,
        'sent'     => time(),
        'tries'    => 0,
        'verified' => false,
    ];
    return '';
}

if (get_str('restart') === '1') {
    unset($_SESSION['pw_reset']);
    redirect('forgot_password.php' . (get_str('email') ? '?email=' . urlencode(get_str('email')) : ''));
}

$reset  = $_SESSION['pw_reset'] ?? null;
$errors = [];
$error  = '';
$notice = '';
$email  = strtolower(get_str('email'));
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? post_str('action') : '';

// Phiên đã xác minh nhưng quá hạn đặt mật khẩu
if ($reset && $reset['verified'] && $reset['vexp'] < time()) {
    unset($_SESSION['pw_reset']);
    $reset = null;
    $error = 'Phiên đặt lại mật khẩu đã hết hạn, vui lòng lấy mã mới.';
}

if ($action === 'send') {
    // Bước 1: gửi mã
    $email = strtolower(post_str('email'));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email không hợp lệ.';
    } elseif (!($error = otp_send($email))) {
        redirect('forgot_password.php');
    }
} elseif ($action === 'resend' && $reset && !$reset['verified']) {
    $wait = OTP_RESEND - (time() - $reset['sent']);
    if ($wait > 0) {
        $error = "Vui lòng chờ $wait giây rồi gửi lại mã.";
    } elseif (!($error = otp_send($reset['email']))) {
        set_flash('success', 'Đã gửi mã mới. Mã cũ không còn dùng được.');
        redirect('forgot_password.php');
    }
} elseif ($action === 'verify' && $reset && !$reset['verified']) {
    // Bước 2: kiểm tra mã
    $code = preg_replace('/\D/', '', post_str('code'));

    if ($reset['exp'] < time()) {
        $error = 'Mã đã hết hạn. Bấm "Gửi lại mã" để nhận mã mới.';
    } elseif ($reset['tries'] >= OTP_TRIES) {
        $error = 'Bạn đã nhập sai quá ' . OTP_TRIES . ' lần. Bấm "Gửi lại mã" để nhận mã mới.';
    } elseif (strlen($code) !== 6) {
        $errors['code'] = 'Mã gồm 6 chữ số.';
    } elseif (!$reset['uid'] || !password_verify($code, $reset['hash'])) {
        $_SESSION['pw_reset']['tries']++;
        $left  = OTP_TRIES - $_SESSION['pw_reset']['tries'];
        $errors['code'] = $left > 0 ? "Mã không đúng. Bạn còn $left lần thử." : 'Mã không đúng. Bấm "Gửi lại mã" để nhận mã mới.';
    } else {
        session_regenerate_id(true);
        $_SESSION['pw_reset']['verified'] = true;
        $_SESSION['pw_reset']['vexp']     = time() + RESET_TTL;
        unset($_SESSION['pw_reset']['hash']);
        redirect('forgot_password.php');
    }
    $reset = $_SESSION['pw_reset'];
} elseif ($action === 'reset') {
    // Bước 3: đặt mật khẩu mới
    if (!$reset || !$reset['verified']) {
        set_flash('error', 'Phiên đặt lại mật khẩu đã hết hạn, vui lòng lấy mã mới.');
        redirect('forgot_password.php');
    }

    $new     = post_str('new_password', '', false);
    $confirm = post_str('confirm_password', '', false);
    $user    = db_one("SELECT id, email, password_hash, status FROM users WHERE id = ?", [$reset['uid']]);

    if (!$user || $user['status'] !== 'active') {
        unset($_SESSION['pw_reset']);
        set_flash('error', 'Không thể đặt lại mật khẩu cho tài khoản này.');
        redirect('forgot_password.php');
    }

    if (strlen($new) < 6) {
        $errors['new_password'] = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
    } elseif (password_verify($new, $user['password_hash'])) {
        $errors['new_password'] = 'Mật khẩu mới phải khác mật khẩu cũ.';
    }
    if ($confirm !== $new) {
        $errors['confirm_password'] = 'Mật khẩu nhập lại không khớp.';
    }

    if (!$errors) {
        db_run("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        unset($_SESSION['pw_reset']);
        rate_clear('login', $user['email']);
        rate_clear('otp_send', $user['email']);
        set_flash('success', 'Đặt lại mật khẩu thành công. Hãy đăng nhập bằng mật khẩu mới.');
        redirect('login.php?email=' . urlencode($user['email']));
    }
}

$step  = !$reset ? 1 : ($reset['verified'] ? 3 : 2);
$heads = [
    1 => ['bi-shield-lock', 'Quên mật khẩu', 'Nhập email đã đăng ký, chúng tôi sẽ gửi mã xác minh gồm 6 chữ số.'],
    2 => ['bi-envelope-check', 'Nhập mã xác minh', ''],
    3 => ['bi-key', 'Đặt mật khẩu mới', ''],
];
$wait  = $step === 2 ? max(0, OTP_RESEND - (time() - $reset['sent'])) : 0;
$title = 'Quên mật khẩu';
require '../layout/header.php';
?>

<section class="container section">
    <div class="auth-card card">
        <div class="auth-head">
            <span class="auth-icon"><i class="bi <?= $heads[$step][0] ?>"></i></span>
            <h1><?= $heads[$step][1] ?></h1>
            <p class="text-muted">
                <?php if ($step === 1): ?>
                    <?= $heads[1][2] ?>
                <?php elseif ($step === 2): ?>
                    Nếu <strong><?= e(mask_email($reset['email'])) ?></strong> đã đăng ký tài khoản, mã 6 số vừa được gửi tới hộp thư. Hãy kiểm tra cả mục Spam hoặc Quảng cáo.
                <?php else: ?>
                    Tài khoản <strong><?= e(mask_email($reset['email'])) ?></strong> đã được xác minh.
                <?php endif; ?>
            </p>
        </div>

        <ol class="auth-steps auth-steps-3" aria-label="Tiến trình">
            <?php foreach ([1 => 'Email', 2 => 'Nhập mã', 3 => 'Mật khẩu mới'] as $i => $label): ?>
                <li class="<?= $i === $step ? 'is-current' : ($i < $step ? 'is-done' : '') ?>"><span><?= $i < $step ? '<i class="bi bi-check"></i>' : $i ?></span> <?= $label ?></li>
            <?php endforeach; ?>
        </ol>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i><span><?= e($error) ?></span></div>
        <?php endif; ?>
        <?php if ($step === 2 && !mail_configured()): ?>
            <div class="alert alert-warning"><i class="bi bi-tools"></i><span>Chế độ chạy thử: chưa khai báo Gmail trong file .env nên mã được ghi vào <b>logs/mail.log</b>.</span></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <form method="post" class="form" novalidate>
                <input type="hidden" name="action" value="send">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="input <?= invalid($errors, 'email') ?>"
                           value="<?= e($email) ?>" autocomplete="email" placeholder="ban@gmail.com" required autofocus>
                    <?= show_error($errors, 'email') ?>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Gửi mã xác minh</button>
            </form>
            <p class="auth-note">
                <i class="bi bi-headset"></i>
                <span>Không còn dùng email này? Gọi <a href="tel:<?= e(preg_replace('/\D/', '', SHOP_HOTLINE)) ?>" class="link"><?= e(SHOP_HOTLINE) ?></a> để được hỗ trợ.</span>
            </p>

        <?php elseif ($step === 2): ?>
            <form method="post" class="form" novalidate>
                <input type="hidden" name="action" value="verify">
                <div class="form-group">
                    <label for="code">Mã xác minh</label>
                    <input type="text" id="code" name="code" class="input otp-input <?= invalid($errors, 'code') ?>"
                           inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"
                           placeholder="••••••" required autofocus data-otp>
                    <?= show_error($errors, 'code') ?>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Xác minh</button>
            </form>
            <div class="otp-actions">
                <span class="text-muted">Chưa nhận được mã?</span>
                <form method="post">
                    <input type="hidden" name="action" value="resend">
                    <button type="submit" class="btn-link" data-wait="<?= $wait ?>" <?= $wait ? 'disabled' : '' ?>>
                        Gửi lại mã<?= $wait ? ' (' . $wait . 's)' : '' ?>
                    </button>
                </form>
            </div>
            <p class="auth-note">
                <i class="bi bi-clock"></i>
                <span>Mã có hiệu lực 10 phút. <a href="<?= url('forgot_password.php?restart=1&email=' . urlencode($reset['email'])) ?>" class="link">Đổi email khác</a></span>
            </p>

        <?php else: ?>
            <form method="post" class="form" novalidate data-pw-form>
                <input type="hidden" name="action" value="reset">
                <input type="email" name="username" value="<?= e($reset['email']) ?>" autocomplete="username" hidden>
                <div class="form-group">
                    <label for="new_password">Mật khẩu mới</label>
                    <div class="input-pw">
                        <input type="password" id="new_password" name="new_password" class="input <?= invalid($errors, 'new_password') ?>"
                               autocomplete="new-password" minlength="6" required autofocus data-pw-new>
                        <button type="button" class="pw-toggle" aria-label="Hiện mật khẩu"><i class="bi bi-eye"></i></button>
                    </div>
                    <?= show_error($errors, 'new_password') ?>
                    <ul class="pw-rules">
                        <li data-rule="len"><i class="bi bi-check-circle"></i> Ít nhất 6 ký tự</li>
                        <li data-rule="mix"><i class="bi bi-check-circle"></i> Nên có cả chữ và số</li>
                    </ul>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Nhập lại mật khẩu mới</label>
                    <div class="input-pw">
                        <input type="password" id="confirm_password" name="confirm_password" class="input <?= invalid($errors, 'confirm_password') ?>"
                               autocomplete="new-password" required data-pw-confirm>
                        <button type="button" class="pw-toggle" aria-label="Hiện mật khẩu"><i class="bi bi-eye"></i></button>
                    </div>
                    <?= show_error($errors, 'confirm_password') ?>
                    <div class="form-hint pw-match" data-pw-match hidden></div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Lưu mật khẩu mới</button>
            </form>
            <p class="auth-note">
                <i class="bi bi-clock"></i>
                <span>Bạn có 10 phút để đặt mật khẩu mới.</span>
            </p>
        <?php endif; ?>

        <p class="auth-switch"><a href="<?= url('login.php') ?>" class="link"><i class="bi bi-arrow-left"></i> Quay lại đăng nhập</a></p>
    </div>
</section>

<?php require '../layout/footer.php'; ?>
