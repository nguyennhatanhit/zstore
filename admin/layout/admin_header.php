<?php
/** Phần đầu trang quản trị (theo Figma ATS E-Store). */
$me = current_user();
// Nhóm menu => [đường dẫn => [tên, icon, các trang con cũng tô sáng mục này]]
$adminMenu = [
    'Tổng quan' => [
        'admin/index.php'      => ['Tổng quan & Thống kê',   'bi-grid',     []],
    ],
    'Bán hàng' => [
        'admin/orders.php'     => ['Đơn hàng',               'bi-bag',      ['admin/order_detail.php']],
        'admin/customers.php'  => ['Khách hàng',             'bi-people',   ['admin/customer_detail.php']],
        'admin/promotions.php' => ['Khuyến mại',             'bi-gift',     []],
    ],
    'Sản phẩm & kho' => [
        'admin/products.php'   => ['Sản phẩm',               'bi-box-seam', ['admin/product_form.php']],
        'admin/categories.php' => ['Danh mục & Thương hiệu', 'bi-tags',     []],
        'admin/specs.php'      => ['Thông số kỹ thuật',      'bi-sliders',  []],
        'admin/inventory.php'  => ['Kho hàng',               'bi-boxes',    []],
    ],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(isset($title) ? "$title · Quản trị" : 'Quản trị') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('frontend/assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('frontend/assets/css/admin.css') ?>">
</head>
<body class="admin">

<aside class="admin-sidebar">
    <a href="<?= url('admin/index.php') ?>" class="admin-brand">
        <span class="brand-mark"><i class="bi bi-lightning-charge-fill"></i></span><span class="label">Quản Trị</span>
    </a>
    <nav class="admin-nav">
        <?php foreach ($adminMenu as $group => $items): ?>
            <div class="admin-nav-label label"><?= e($group) ?></div>
            <?php foreach ($items as $path => $item): ?>
                <?php $active = is_page($path) || array_filter($item[2], 'is_page'); ?>
                <a href="<?= url($path) ?>" class="<?= $active ? 'active' : '' ?>" title="<?= e($item[0]) ?>">
                    <i class="bi <?= $item[1] ?>"></i><span class="label"><?= e($item[0]) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <div class="admin-support label">
        <span class="support-title"><span class="dot"></span>Hỗ trợ &amp; Cài đặt</span>
        <a href="<?= url('index.php') ?>" class="support-link"><i class="bi bi-arrow-left"></i> Xem cửa hàng</a>
        <div class="support-user"><?= e($me['full_name']) ?> · Quản trị viên</div>
    </div>
</aside>

<div class="admin-main">
    <header class="admin-topbar">
        <div class="breadcrumb">
            <a href="<?= url('admin/index.php') ?>">Quản trị</a>
            <i class="bi bi-chevron-right"></i>
            <span class="current"><?= e(isset($breadcrumb) ? $breadcrumb : '') ?></span>
        </div>
        <div class="topbar-tools">
            <label class="topbar-search"><i class="bi bi-search"></i><input type="search" placeholder="Tìm nhanh trong quản trị..."></label>
            <button class="icon-btn has-dot" aria-label="Thông báo"><i class="bi bi-bell"></i></button>
            <div class="dropdown">
                <button class="topbar-profile" data-dropdown>
                    <span class="avatar"><?= e(initials($me['full_name'])) ?></span>
                    <span class="profile-text"><b><?= e($me['full_name']) ?></b><small>Quản trị viên</small></span>
                </button>
                <div class="dropdown-menu dropdown-right">
                    <a href="<?= url('index.php') ?>">Xem cửa hàng</a>
                    <a href="<?= url('logout.php') ?>">Đăng xuất</a>
                </div>
            </div>
        </div>
    </header>

    <main class="admin-content">
        <?= show_flash() ?>
