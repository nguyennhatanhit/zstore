<?php
/** Phần đầu trang khách hàng. Trước khi require, đặt biến $title. */
$me = current_user();
// Menu danh mục: các danh mục không có danh mục con (CPU, RAM, ..., Laptop), lấy từ CSDL
$categoryMenu = db_all("
    SELECT name, slug, code FROM categories
    WHERE id NOT IN (SELECT parent_id FROM categories WHERE parent_id IS NOT NULL)
    ORDER BY parent_id IS NULL, sort_order, name
");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="cart-api" content="<?= url('cart-api.php') ?>">
    <title><?= e(isset($title) ? "$title · " . APP_NAME : APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('frontend/assets/css/style.css') ?>">
</head>
<body>

<header class="site-header">
    <div class="container header-main">
        <a href="<?= url('index.php') ?>" class="brand">
            <span class="brand-mark"><i class="bi bi-lightning-charge-fill"></i></span><?= APP_NAME ?>
        </a>

        <form action="<?= url('products.php') ?>" method="get" class="header-search" data-search="<?= url('search-api.php') ?>">
            <i class="bi bi-search"></i>
            <input type="search" name="q" placeholder="Tìm CPU, laptop, card đồ họa..." value="<?= e(get_str('q')) ?>"
                   autocomplete="off" role="combobox" aria-expanded="false" aria-controls="search-suggest">
            <div class="search-suggest" id="search-suggest" role="listbox" hidden></div>
        </form>

        <nav class="header-actions">
            <a href="<?= url('ai.php') ?>" class="header-link"><i class="bi bi-stars"></i><span>Tư vấn AI</span></a>
            <a href="<?= url('build-pc.php') ?>" class="header-link"><i class="bi bi-cpu"></i><span>Build PC</span></a>
            <a href="<?= url('compare.php') ?>" class="header-link">
                <i class="bi bi-arrow-left-right"></i><span>So sánh</span>
                <b class="count" id="compare-count" <?= compare_count() ? '' : 'hidden' ?>><?= compare_count() ?></b>
            </a>
            <a href="<?= url('cart.php') ?>" class="header-link">
                <i class="bi bi-cart3"></i><span>Giỏ hàng</span>
                <b class="count" id="cart-count" <?= cart_count() ? '' : 'hidden' ?>><?= cart_count() ?></b>
            </a>

            <?php if ($me): ?>
                <div class="dropdown">
                    <button class="header-link" data-dropdown><i class="bi bi-person-circle"></i><span><?= e($me['full_name']) ?></span></button>
                    <div class="dropdown-menu dropdown-right">
                        <?php if (is_admin()): ?><a href="<?= url('admin/index.php') ?>">Trang quản trị</a><?php endif; ?>
                        <a href="<?= url('account.php') ?>">Tài khoản của tôi</a>
                        <a href="<?= url('orders.php') ?>">Đơn hàng của tôi</a>
                        <a href="<?= url('logout.php') ?>">Đăng xuất</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= url('login.php') ?>" class="btn btn-primary btn-sm">Đăng nhập</a>
            <?php endif; ?>
        </nav>
    </div>

    <nav class="category-nav">
        <div class="container">
            <?php foreach ($categoryMenu as $c): ?>
                <a href="<?= url('products.php?category=' . $c['slug']) ?>"
                   class="<?= (get_str('category') === $c['slug']) ? 'active' : '' ?>"><i class="bi <?= category_icon($c['code']) ?>"></i><?= e($c['name']) ?></a>
            <?php endforeach; ?>
        </div>
    </nav>
</header>

<main class="site-main">
    <div class="container"><?= show_flash() ?></div>
