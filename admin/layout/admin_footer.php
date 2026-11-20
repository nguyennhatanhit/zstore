    </main>
</div>

<nav class="admin-bottom-nav">
    <a href="<?= url('admin/index.php') ?>" class="<?= is_page('admin/index.php') ? 'active' : '' ?>"><i class="bi bi-grid"></i><span>Tổng quan</span></a>
    <a href="<?= url('admin/products.php') ?>" class="<?= is_page('admin/products.php') ? 'active' : '' ?>"><i class="bi bi-box-seam"></i><span>Sản phẩm</span></a>
    <a href="<?= url('admin/orders.php') ?>" class="<?= is_page('admin/orders.php') ? 'active' : '' ?>"><i class="bi bi-bag"></i><span>Đơn hàng</span></a>
    <a href="<?= url('admin/customers.php') ?>" class="<?= is_page('admin/customers.php') ? 'active' : '' ?>"><i class="bi bi-people"></i><span>Khách hàng</span></a>
    <button type="button" data-toggle-sidebar><i class="bi bi-list"></i><span>Menu</span></button>
</nav>
<div class="sidebar-backdrop" data-toggle-sidebar></div>

<script src="<?= url('frontend/assets/js/main.js') ?>"></script>
</body>
</html>
