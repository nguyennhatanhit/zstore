<?php
/** Menu bên trái trang tài khoản. Cần có biến $user (lấy từ CSDL). */
$accountMenu = [
    'account.php'         => ['Thông tin tài khoản', 'bi-person'],
    'change_password.php' => ['Đổi mật khẩu',        'bi-key'],
    'orders.php'          => ['Đơn hàng của tôi',    'bi-bag'],
];
?>
<aside class="account-side card">
    <div class="account-user">
        <span class="avatar avatar-lg"><?= e(initials($user['full_name'])) ?></span>
        <div>
            <b><?= e($user['full_name']) ?></b>
            <div class="text-muted"><?= e($user['email']) ?></div>
        </div>
    </div>
    <nav class="account-nav">
        <?php foreach ($accountMenu as $path => $item): ?>
            <a href="<?= url($path) ?>" class="<?= is_page($path) ? 'active' : '' ?>">
                <i class="bi <?= $item[1] ?>"></i><span><?= e($item[0]) ?></span>
            </a>
        <?php endforeach; ?>
        <a href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right"></i><span>Đăng xuất</span></a>
    </nav>
</aside>
