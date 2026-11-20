<?php
/** KHUYẾN MẠI - [Admin 6] Thêm, sửa, xóa chương trình khuyến mãi */
require '../config.php';
require_admin();

$errors = [];
$form = [
    'id' => '', 'name' => '', 'code' => '', 'discount_type' => 'percent', 'discount_value' => '', 'min_order_value' => 0,
    'max_discount' => '', 'start_date' => date('Y-m-d\TH:i'), 'end_date' => date('Y-m-d\TH:i', strtotime('+7 days')),
    'usage_limit' => '', 'status' => 'active',
];
$selectedProducts = [];

if (isset($_GET['edit'])) {
    $found = db_one("SELECT * FROM promotions WHERE id = ?", [(int) get_str('edit', '0')]);
    if ($found) {
        $form = $found;
        $form['start_date'] = date('Y-m-d\TH:i', strtotime($found['start_date']));
        $form['end_date']   = date('Y-m-d\TH:i', strtotime($found['end_date']));
        $selectedProducts = array_column(db_all("SELECT product_id FROM promotion_products WHERE promotion_id = ?", [$found['id']]), 'product_id');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_str('action', '');

    if ($action === 'delete') {
        db_run("DELETE FROM promotions WHERE id = ?", [(int) post_str('id', '0')]);
        set_flash('success', 'Đã xóa chương trình khuyến mãi.');
        redirect('admin/promotions.php');
    }

    if ($action === 'toggle') {
        db_run("UPDATE promotions SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = ?", [(int) post_str('id', '0')]);
        set_flash('success', 'Đã đổi trạng thái khuyến mãi.');
        redirect('admin/promotions.php');
    }

    if ($action === 'save') {
        $form = [
            'id'              => (int) post_str('id', '0'),
            'name'            => post_str('name'),
            'code'            => strtoupper(post_str('code')),
            'discount_type'   => post_str('discount_type') === 'fixed' ? 'fixed' : 'percent',
            'discount_value'  => post_str('discount_value'),
            'min_order_value' => (int) post_str('min_order_value', '0'),
            'max_discount'    => post_str('max_discount'),
            'start_date'      => post_str('start_date'),
            'end_date'        => post_str('end_date'),
            'usage_limit'     => post_str('usage_limit'),
            'status'          => post_str('status') === 'inactive' ? 'inactive' : 'active',
        ];
        $selectedProducts = array_map('intval', post_arr('products'));

        if ($form['name'] === '') {
            $errors['name'] = 'Vui lòng nhập tên chương trình.';
        }
        if ($form['code'] !== '') {
            if (!preg_match('/^[A-Z0-9_-]{3,30}$/', $form['code'])) {
                $errors['code'] = 'Mã gồm 3–30 ký tự: chữ không dấu, số, - hoặc _.';
            } elseif (db_value("SELECT COUNT(*) FROM promotions WHERE code = ? AND id <> ?", [$form['code'], $form['id']])) {
                $errors['code'] = 'Mã đã tồn tại.';
            }
        }
        if (!is_numeric($form['discount_value']) || $form['discount_value'] <= 0) {
            $errors['discount_value'] = 'Giá trị giảm phải lớn hơn 0.';
        } elseif ($form['discount_type'] === 'percent' && $form['discount_value'] > 100) {
            $errors['discount_value'] = 'Phần trăm giảm tối đa 100.';
        }
        if ($form['max_discount'] !== '' && (!is_numeric($form['max_discount']) || $form['max_discount'] < 0)) {
            $errors['max_discount'] = 'Giảm tối đa phải là số không âm.';
        }
        if (!strtotime($form['start_date']) || !strtotime($form['end_date'])) {
            $errors['end_date'] = 'Vui lòng nhập thời gian hợp lệ.';
        } elseif (strtotime($form['end_date']) <= strtotime($form['start_date'])) {
            $errors['end_date'] = 'Thời gian kết thúc phải sau thời gian bắt đầu.';
        }
        if ($form['usage_limit'] !== '' && (int) $form['usage_limit'] <= 0) {
            $errors['usage_limit'] = 'Giới hạn lượt dùng phải lớn hơn 0 (để trống nếu không giới hạn).';
        }
        if ($form['code'] === '' && !$selectedProducts) {
            $errors['products'] = 'Khuyến mãi không có mã phải chọn ít nhất 1 sản phẩm áp dụng.';
        }

        if (!$errors) {
            $data = [
                $form['name'], $form['code'] ?: null, $form['discount_type'], $form['discount_value'], $form['min_order_value'],
                $form['max_discount'] !== '' ? $form['max_discount'] : null,
                date('Y-m-d H:i:s', strtotime($form['start_date'])), date('Y-m-d H:i:s', strtotime($form['end_date'])),
                $form['usage_limit'] !== '' ? (int) $form['usage_limit'] : null, $form['status'],
            ];
            try {
                $pdo->beginTransaction();
                if ($form['id']) {
                    $data[] = $form['id'];
                    db_run("UPDATE promotions SET name = ?, code = ?, discount_type = ?, discount_value = ?, min_order_value = ?, max_discount = ?,
                            start_date = ?, end_date = ?, usage_limit = ?, status = ? WHERE id = ?", $data);
                    $promoId = $form['id'];
                } else {
                    $data[] = db_now();
                    db_run("INSERT INTO promotions (name, code, discount_type, discount_value, min_order_value, max_discount,
                            start_date, end_date, usage_limit, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $data);
                    $promoId = db_last_id();
                }
                db_run("DELETE FROM promotion_products WHERE promotion_id = ?", [$promoId]);
                foreach ($selectedProducts as $pid) {
                    db_run("INSERT INTO promotion_products (promotion_id, product_id) VALUES (?, ?)", [$promoId, $pid]);
                }
                $pdo->commit();
                set_flash('success', ($form['id'] ? 'Đã cập nhật' : 'Đã thêm') . ' khuyến mãi "' . $form['name'] . '".');
                redirect('admin/promotions.php');
            } catch (Exception $ex) {
                $pdo->rollBack();
                $errors['name'] = 'Lỗi: ' . $ex->getMessage();
            }
        }
    }
}

/** Trạng thái hiển thị của khuyến mãi theo thời gian */
function promo_state($p)
{
    $now = time();
    if ($p['status'] !== 'active') {
        return ['Tạm tắt', ''];
    }
    if ($p['usage_limit'] !== null && $p['used_count'] >= $p['usage_limit']) {
        return ['Hết lượt', 'danger'];
    }
    if (strtotime($p['start_date']) > $now) {
        return ['Sắp diễn ra', 'info'];
    }
    if (strtotime($p['end_date']) < $now) {
        return ['Đã kết thúc', 'danger'];
    }
    return ['Đang chạy', 'success'];
}

$promotions = db_all("
    SELECT pr.*, (SELECT COUNT(*) FROM promotion_products WHERE promotion_id = pr.id) AS product_count
    FROM promotions pr ORDER BY pr.end_date DESC");
$products = db_all("SELECT p.id, p.name, p.price, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id
                    WHERE p.status = 'active' ORDER BY c.sort_order, c.name, p.name");

$title = 'Khuyến mại';
$breadcrumb = 'Khuyến mại';
require 'layout/admin_header.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Khuyến mại</h1>
        <p class="page-desc">Giảm giá tự động theo sản phẩm hoặc mã giảm giá nhập ở giỏ hàng.</p>
    </div>
</div>

<div class="detail-grid">
    <section class="panel">
        <div class="panel-head"><h2 class="panel-title">Chương trình <span class="panel-count"><?= count($promotions) ?></span></h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Chương trình</th><th>Mức giảm</th><th>Thời gian</th><th class="num">Đã dùng</th><th>Trạng thái</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($promotions as $p): $st = promo_state($p); ?>
                    <tr class="<?= $p['id'] == $form['id'] ? 'row-editing' : '' ?>">
                        <td>
                            <b><?= e($p['name']) ?></b>
                            <div class="cell-sub">
                                <?= $p['code'] ? 'Mã <span class="tag">' . e($p['code']) . '</span>' : 'Tự động' ?>
                                · <?= $p['product_count'] ? $p['product_count'] . ' sản phẩm' : 'Toàn đơn' ?>
                            </div>
                        </td>
                        <td>
                            <b><?= $p['discount_type'] === 'percent' ? (float) $p['discount_value'] . '%' : money($p['discount_value']) ?></b>
                            <?php if ($p['max_discount']): ?><div class="cell-sub">Tối đa <?= money($p['max_discount']) ?></div><?php endif; ?>
                            <?php if ($p['min_order_value'] > 0): ?><div class="cell-sub">Đơn từ <?= money($p['min_order_value']) ?></div><?php endif; ?>
                        </td>
                        <td><?= format_date($p['start_date'], 'd/m/Y') ?><div class="cell-sub">→ <?= format_date($p['end_date'], 'd/m/Y') ?></div></td>
                        <td class="num"><?= (int) $p['used_count'] ?><?= $p['usage_limit'] ? ' / ' . (int) $p['usage_limit'] : '' ?></td>
                        <td><span class="badge badge-<?= $st[1] ?>"><?= $st[0] ?></span></td>
                        <td>
                            <div class="row-actions">
                                <a href="?edit=<?= $p['id'] ?>#promo-form" class="btn btn-sm btn-soft">Sửa</a>
                                <form method="post">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button class="btn btn-sm btn-outline" title="<?= $p['status'] === 'active' ? 'Tạm tắt' : 'Bật lại' ?>"><i class="bi <?= $p['status'] === 'active' ? 'bi-pause' : 'bi-play' ?>"></i></button>
                                </form>
                                <form method="post" data-confirm="Xóa khuyến mãi &quot;<?= e($p['name']) ?>&quot;?">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button class="btn btn-sm btn-outline"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$promotions): ?><tr><td colspan="6" class="empty">Chưa có khuyến mãi.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel" id="promo-form">
        <div class="panel-head">
            <div>
                <h2 class="panel-title"><?= $form['id'] ? 'Sửa khuyến mãi' : 'Thêm khuyến mãi' ?></h2>
                <?php if ($form['id']): ?><p class="panel-desc"><a href="<?= url('admin/promotions.php') ?>" class="link">+ Tạo mới thay vì sửa</a></p><?php endif; ?>
            </div>
        </div>
        <form method="post" class="panel-body form" novalidate>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= e($form['id']) ?>">

            <div class="form-group">
                <label for="name">Tên chương trình <span class="req">*</span></label>
                <input id="name" name="name" class="input <?= invalid($errors, 'name') ?>" value="<?= e($form['name']) ?>" placeholder="VD: Black Friday giảm 10% VGA">
                <?= show_error($errors, 'name') ?>
            </div>
            <div class="form-group">
                <label for="code">Mã giảm giá</label>
                <input id="code" name="code" class="input <?= invalid($errors, 'code') ?>" value="<?= e($form['code']) ?>" placeholder="Để trống = tự giảm vào giá sản phẩm">
                <?= show_error($errors, 'code') ?>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="discount_type">Loại giảm</label>
                    <select id="discount_type" name="discount_type" class="select">
                        <option value="percent" <?= $form['discount_type'] === 'percent' ? 'selected' : '' ?>>Phần trăm (%)</option>
                        <option value="fixed" <?= $form['discount_type'] === 'fixed' ? 'selected' : '' ?>>Số tiền (₫)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="discount_value">Giá trị <span class="req">*</span></label>
                    <input id="discount_value" type="number" step="any" min="0" name="discount_value" class="input <?= invalid($errors, 'discount_value') ?>" value="<?= e($form['discount_value']) ?>">
                    <?= show_error($errors, 'discount_value') ?>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="max_discount">Giảm tối đa (₫)</label>
                    <input id="max_discount" type="number" min="0" name="max_discount" class="input <?= invalid($errors, 'max_discount') ?>" value="<?= e($form['max_discount']) ?>" placeholder="Không giới hạn">
                    <?= show_error($errors, 'max_discount') ?>
                </div>
                <div class="form-group">
                    <label for="min_order_value">Đơn tối thiểu (₫)</label>
                    <input id="min_order_value" type="number" min="0" name="min_order_value" class="input" value="<?= e($form['min_order_value']) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="start_date">Bắt đầu</label>
                    <input id="start_date" type="datetime-local" name="start_date" class="input" value="<?= e($form['start_date']) ?>">
                </div>
                <div class="form-group">
                    <label for="end_date">Kết thúc</label>
                    <input id="end_date" type="datetime-local" name="end_date" class="input <?= invalid($errors, 'end_date') ?>" value="<?= e($form['end_date']) ?>">
                    <?= show_error($errors, 'end_date') ?>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="usage_limit">Giới hạn lượt dùng</label>
                    <input id="usage_limit" type="number" min="1" name="usage_limit" class="input <?= invalid($errors, 'usage_limit') ?>" value="<?= e($form['usage_limit']) ?>" placeholder="Không giới hạn">
                    <?= show_error($errors, 'usage_limit') ?>
                </div>
                <div class="form-group">
                    <label for="status">Trạng thái</label>
                    <select id="status" name="status" class="select">
                        <option value="active" <?= $form['status'] === 'active' ? 'selected' : '' ?>>Bật</option>
                        <option value="inactive" <?= $form['status'] === 'inactive' ? 'selected' : '' ?>>Tạm tắt</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="products">Sản phẩm áp dụng</label>
                <select id="products" name="products[]" multiple size="8" class="select select-multi <?= invalid($errors, 'products') ?>">
                    <?php $group = null; foreach ($products as $p): ?>
                        <?php if ($group !== $p['category_name']): ?>
                            <?php if ($group !== null) echo '</optgroup>'; $group = $p['category_name']; ?>
                            <optgroup label="<?= e($group) ?>">
                        <?php endif; ?>
                        <option value="<?= $p['id'] ?>" <?= in_array($p['id'], $selectedProducts) ? 'selected' : '' ?>><?= e($p['name']) ?> — <?= money($p['price']) ?></option>
                    <?php endforeach; ?>
                    <?php if ($group !== null) echo '</optgroup>'; ?>
                </select>
                <?= show_error($errors, 'products') ?: '<div class="form-hint">Giữ Ctrl (Cmd trên Mac) để chọn nhiều. Mã giảm giá không chọn sản phẩm = áp dụng toàn đơn.</div>' ?>
            </div>
            <button class="btn btn-primary btn-block"><?= $form['id'] ? 'Lưu thay đổi' : '+ Thêm khuyến mãi' ?></button>
        </form>
    </section>
</div>

<?php require 'layout/admin_footer.php'; ?>
