<?php
/** KHÁCH HÀNG - [Admin 11] Xem và quản lý tài khoản khách hàng (tìm kiếm, khóa / mở khóa) */
require '../config.php';
require_admin();

// Khóa / mở khóa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = db_one("SELECT * FROM users WHERE id = ? AND role = 'customer'", [(int) post_str('id', '0')]);
    if ($user) {
        $new = $user['status'] === 'active' ? 'locked' : 'active';
        db_run("UPDATE users SET status = ? WHERE id = ?", [$new, $user['id']]);
        set_flash('success', ($new === 'locked' ? 'Đã khóa' : 'Đã mở khóa') . ' tài khoản ' . $user['email'] . '.');
    }
    redirect_back('admin/customers.php');
}

$q = get_str('q');
$status = get_str('status', '');

$where = ["u.role = 'customer'"];
$params = [];
if ($q !== '') {
    $where[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    array_push($params, "%$q%", "%$q%", "%$q%");
}
if (in_array($status, ['active', 'locked'])) {
    $where[] = "u.status = ?";
    $params[] = $status;
}
$whereSql = implode(' AND ', $where);

$stats = db_one("SELECT COUNT(*) AS total,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
                        SUM(CASE WHEN status = 'locked' THEN 1 ELSE 0 END) AS locked,
                        SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS new30
                 FROM users WHERE role = 'customer'", [date('Y-m-d', strtotime('-30 days'))]);

$total = (int) db_value("SELECT COUNT(*) FROM users u WHERE $whereSql", $params);
$pg = paginate($total, 15);
$customers = db_all("
    SELECT u.*,
           (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS order_count,
           (SELECT COALESCE(SUM(total), 0) FROM orders WHERE user_id = u.id AND status = 'completed') AS spent,
           (SELECT MAX(created_at) FROM orders WHERE user_id = u.id) AS last_order
    FROM users u WHERE $whereSql
    ORDER BY u.created_at DESC, u.id DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params);

$title = 'Khách hàng';
$breadcrumb = 'Khách hàng';
require 'layout/admin_header.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Khách hàng</h1>
        <p class="page-desc">Tìm kiếm, theo dõi hoạt động và quản lý trạng thái tài khoản khách hàng.</p>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-head"><span>Tổng khách hàng</span><i class="bi bi-people"></i></div><div class="stat-value"><?= (int) $stats['total'] ?></div><div class="stat-note">Tài khoản đã đăng ký</div></div>
    <div class="stat-card"><div class="stat-head"><span>Đang hoạt động</span><i class="bi bi-person-check"></i></div><div class="stat-value text-success"><?= (int) $stats['active'] ?></div><div class="stat-note">Đăng nhập bình thường</div></div>
    <div class="stat-card"><div class="stat-head"><span>Đã khóa</span><i class="bi bi-person-lock"></i></div><div class="stat-value text-danger"><?= (int) $stats['locked'] ?></div><div class="stat-note">Không thể đăng nhập</div></div>
    <div class="stat-card"><div class="stat-head"><span>Khách mới</span><i class="bi bi-person-plus"></i></div><div class="stat-value"><?= (int) $stats['new30'] ?></div><div class="stat-note">30 ngày gần nhất</div></div>
</div>

<section class="panel">
    <form method="get" class="filter-bar">
        <label class="search-input"><i class="bi bi-search"></i><input name="q" value="<?= e($q) ?>" placeholder="Tìm theo tên, email hoặc số điện thoại"></label>
        <select name="status" class="select">
            <option value="">Tất cả trạng thái</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Hoạt động</option>
            <option value="locked" <?= $status === 'locked' ? 'selected' : '' ?>>Đã khóa</option>
        </select>
        <button class="btn btn-soft">Lọc</button>
    </form>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Tài khoản</th><th>Số điện thoại</th><th class="num">Số đơn</th><th>Tổng chi tiêu</th><th>Hoạt động gần nhất</th><th>Trạng thái</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
                <tr>
                    <td>
                        <div class="cell-product">
                            <span class="avatar"><?= e(initials($c['full_name'])) ?></span>
                            <div><a href="<?= url('admin/customer_detail.php?id=' . $c['id']) ?>"><b><?= e($c['full_name']) ?></b></a><div class="cell-sub"><?= e($c['email']) ?></div></div>
                        </div>
                    </td>
                    <td><?= e($c['phone']) ?></td>
                    <td class="num"><?= $c['order_count'] ?></td>
                    <td><b><?= money($c['spent']) ?></b></td>
                    <td><?= $c['last_order'] ? format_date($c['last_order']) : '<span class="text-muted">Chưa mua</span>' ?><div class="cell-sub">Đăng ký <?= format_date($c['created_at'], 'd/m/Y') ?></div></td>
                    <td><?= $c['status'] === 'active' ? '<span class="badge badge-success">Hoạt động</span>' : '<span class="badge badge-danger">Đã khóa</span>' ?></td>
                    <td>
                        <div class="row-actions">
                            <a href="<?= url('admin/customer_detail.php?id=' . $c['id']) ?>" class="btn btn-sm btn-soft">Chi tiết</a>
                            <form method="post" data-confirm="<?= $c['status'] === 'active' ? 'Khóa' : 'Mở khóa' ?> tài khoản <?= e($c['email']) ?>?">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <?= back_field() ?>
                                <button class="btn btn-sm btn-outline"><?= $c['status'] === 'active' ? 'Khóa' : 'Mở khóa' ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$customers): ?><tr><td colspan="7" class="empty">Không có khách hàng phù hợp.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="panel-foot"><span class="text-muted">Hiển thị <?= count($customers) ?> / <?= $total ?> tài khoản</span><?= pagination_links($pg) ?></div>
</section>

<?php require 'layout/admin_footer.php'; ?>
