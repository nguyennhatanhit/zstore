<?php
/** THÔNG SỐ KỸ THUẬT THEO DANH MỤC (bổ sung cho [Admin 2] Quản lý danh mục) */
require '../config.php';
require_admin();

$categories = db_all("
    SELECT c.*, (SELECT COUNT(*) FROM spec_definitions WHERE category_id = c.id) AS spec_count
    FROM categories c ORDER BY COALESCE(c.parent_id, c.id), c.parent_id IS NOT NULL, c.sort_order, c.name");
if (!$categories) {
    set_flash('warning', 'Hãy tạo danh mục trước khi khai báo thông số.');
    redirect('admin/categories.php');
}

$catId = (int) get_str('category', '0');
$category = null;
foreach ($categories as $c) {
    if ((int) $c['id'] === $catId) {
        $category = $c;
    }
}
if (!$category) {
    $category = $categories[0];
    $catId = (int) $category['id'];
}

/** Mã thông số đang được luật tương thích Build PC sử dụng (đổi mã / xóa sẽ làm hỏng luật) */
function spec_used_in_rules($catCode, $specCode)
{
    if (!$catCode) {
        return false;
    }
    return (bool) db_value("SELECT COUNT(*) FROM compatibility_rules
        WHERE (category_a = ? AND spec_code_a = ?) OR (category_b = ? AND spec_code_b = ?)",
        [$catCode, $specCode, $catCode, $specCode]);
}

$errors = [];
$spec = ['id' => '', 'name' => '', 'code' => '', 'unit' => '', 'data_type' => 'text', 'is_filterable' => 1, 'sort_order' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_str('action');

    if ($action === 'delete_spec') {
        $old = db_one("SELECT * FROM spec_definitions WHERE id = ? AND category_id = ?", [(int) post_str('id', '0'), $catId]);
        if (!$old) {
            set_flash('error', 'Không tìm thấy thông số.');
        } elseif (spec_used_in_rules($category['code'], $old['code'])) {
            set_flash('error', 'Thông số "' . $old['name'] . '" đang dùng để kiểm tra tương thích Build PC nên không thể xóa.');
        } else {
            $used = (int) db_value("SELECT COUNT(*) FROM product_specs WHERE spec_id = ?", [$old['id']]);
            db_run("DELETE FROM spec_definitions WHERE id = ?", [$old['id']]);
            set_flash('success', 'Đã xóa thông số "' . $old['name'] . '"' . ($used ? " (kèm giá trị của $used sản phẩm)." : '.'));
        }
        redirect('admin/specs.php?category=' . $catId);
    }

    if ($action === 'save_spec') {
        $spec = [
            'id'            => (int) post_str('id', '0') ?: '',
            'name'          => mb_substr(post_str('name'), 0, 100),
            'code'          => strtolower(post_str('code')),
            'unit'          => mb_substr(post_str('unit'), 0, 20),
            'data_type'     => post_str('data_type') === 'number' ? 'number' : 'text',
            'is_filterable' => post_str('is_filterable') !== '' ? 1 : 0,
            'sort_order'    => (int) post_str('sort_order', '0'),
        ];
        $old = $spec['id'] ? db_one("SELECT * FROM spec_definitions WHERE id = ? AND category_id = ?", [$spec['id'], $catId]) : null;

        if ($spec['id'] && !$old) {
            $errors['name'] = 'Không tìm thấy thông số cần sửa.';
        }
        if ($spec['name'] === '') {
            $errors['name'] = 'Vui lòng nhập tên thông số.';
        }
        if ($spec['code'] === '') {
            $spec['code'] = str_replace('-', '_', slugify($spec['name']));
        }
        if (!preg_match('/^[a-z0-9_]{1,50}$/', $spec['code'])) {
            $errors['code'] = 'Mã chỉ gồm chữ thường không dấu, số và dấu gạch dưới (VD: ram_type).';
        } elseif (db_value("SELECT COUNT(*) FROM spec_definitions WHERE category_id = ? AND code = ? AND id <> ?",
                           [$catId, $spec['code'], (int) $spec['id']])) {
            $errors['code'] = 'Danh mục này đã có thông số mã "' . $spec['code'] . '".';
        } elseif ($old && $old['code'] !== $spec['code'] && spec_used_in_rules($category['code'], $old['code'])) {
            $errors['code'] = 'Mã "' . $old['code'] . '" đang dùng để kiểm tra tương thích Build PC, không được đổi.';
        }

        if (!$errors) {
            $data = [$spec['name'], $spec['code'], $spec['unit'] ?: null, $spec['data_type'], $spec['is_filterable'], $spec['sort_order']];
            if ($old) {
                db_run("UPDATE spec_definitions SET name = ?, code = ?, unit = ?, data_type = ?, is_filterable = ?, sort_order = ?
                        WHERE id = ?", array_merge($data, [$old['id']]));
                set_flash('success', 'Đã cập nhật thông số "' . $spec['name'] . '".');
            } else {
                db_run("INSERT INTO spec_definitions (name, code, unit, data_type, is_filterable, sort_order, category_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?)", array_merge($data, [$catId]));
                set_flash('success', 'Đã thêm thông số "' . $spec['name'] . '" cho danh mục ' . $category['name'] . '.');
            }
            redirect('admin/specs.php?category=' . $catId);
        }
    }
}

// Bấm "Sửa"
$editId = (int) get_str('edit', '0');
if ($editId && !$errors) {
    $found = db_one("SELECT * FROM spec_definitions WHERE id = ? AND category_id = ?", [$editId, $catId]);
    if ($found) {
        $spec = $found;
    }
}

$specs = db_all("
    SELECT s.*, (SELECT COUNT(*) FROM product_specs WHERE spec_id = s.id) AS product_count
    FROM spec_definitions s WHERE s.category_id = ? ORDER BY s.sort_order, s.id", [$catId]);

$title = 'Thông số kỹ thuật';
$breadcrumb = 'Thông số kỹ thuật';
require 'layout/admin_header.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Thông số kỹ thuật</h1>
        <p class="page-desc">Khai báo thông số cho từng danh mục: dùng cho form sản phẩm, bộ lọc tìm kiếm và kiểm tra tương thích Build PC.</p>
    </div>
</div>

<form method="get" class="filter-bar">
    <label for="category">Danh mục</label>
    <select id="category" name="category" class="select" data-autosubmit>
        <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (int) $c['id'] === $catId ? 'selected' : '' ?>>
                <?= $c['parent_id'] ? '— ' : '' ?><?= e($c['name']) ?> (<?= (int) $c['spec_count'] ?> thông số)
            </option>
        <?php endforeach; ?>
    </select>
    <noscript><button class="btn btn-outline">Xem</button></noscript>
</form>

<div class="grid-2">

    <section class="panel" id="spec-form">
        <div class="panel-head">
            <div>
                <h2 class="panel-title"><?= $spec['id'] ? 'Sửa thông số' : 'Thêm thông số' ?></h2>
                <p class="panel-desc">Danh mục: <b><?= e($category['name']) ?></b><?= $spec['id'] ? ' · đang sửa <b>' . e($spec['name']) . '</b>' : '' ?></p>
            </div>
        </div>

        <form method="post" action="?category=<?= $catId ?>" class="form inline-form" novalidate>
            <input type="hidden" name="action" value="save_spec">
            <input type="hidden" name="id" value="<?= e($spec['id']) ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="spec_name">Tên thông số <span class="req">*</span></label>
                    <input id="spec_name" name="name" class="input <?= invalid($errors, 'name') ?>" value="<?= e($spec['name']) ?>" placeholder="VD: Kích thước màn hình">
                    <?= show_error($errors, 'name') ?>
                </div>
                <div class="form-group">
                    <label for="spec_code">Mã</label>
                    <input id="spec_code" name="code" class="input <?= invalid($errors, 'code') ?>" value="<?= e($spec['code']) ?>" placeholder="Để trống sẽ tự tạo, VD: screen_size">
                    <?= show_error($errors, 'code') ?: '<div class="form-hint">Chữ thường, số, dấu gạch dưới</div>' ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="spec_unit">Đơn vị</label>
                    <input id="spec_unit" name="unit" class="input" value="<?= e($spec['unit']) ?>" placeholder="VD: inch, GB, W">
                </div>
                <div class="form-group">
                    <label for="spec_type">Kiểu giá trị</label>
                    <select id="spec_type" name="data_type" class="select">
                        <option value="text" <?= $spec['data_type'] === 'text' ? 'selected' : '' ?>>Chữ (VD: AM5, DDR5)</option>
                        <option value="number" <?= $spec['data_type'] === 'number' ? 'selected' : '' ?>>Số (VD: 65, 16)</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="spec_sort">Thứ tự hiển thị</label>
                    <input id="spec_sort" type="number" name="sort_order" class="input" value="<?= (int) $spec['sort_order'] ?>">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <label class="check"><input type="checkbox" name="is_filterable" value="1" <?= $spec['is_filterable'] ? 'checked' : '' ?>> Hiện trong bộ lọc tìm kiếm</label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $spec['id'] ? 'Lưu thay đổi' : '+ Thêm thông số' ?></button>
                <?php if ($spec['id']): ?>
                    <a href="<?= url('admin/specs.php?category=' . $catId) ?>" class="btn btn-outline">Hủy</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head">
            <div>
                <h2 class="panel-title">Thông số của <?= e($category['name']) ?> <span class="panel-count"><?= count($specs) ?></span></h2>
                <p class="panel-desc"><?= $category['code'] ? 'Mã danh mục: <span class="tag">' . e($category['code']) . '</span>' : 'Danh mục chưa có mã kỹ thuật (không tham gia Build PC)' ?></p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Thông số</th><th>Kiểu</th><th class="num">Sản phẩm</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($specs as $s): $inRule = spec_used_in_rules($category['code'], $s['code']); ?>
                        <tr class="<?= $s['id'] == $spec['id'] ? 'row-editing' : '' ?>">
                            <td>
                                <b><?= e($s['name']) ?></b><?= $s['unit'] ? ' <span class="text-muted">(' . e($s['unit']) . ')</span>' : '' ?>
                                <div class="cell-sub">
                                    <span class="tag"><?= e($s['code']) ?></span>
                                    <?= $s['is_filterable'] ? '<span class="tag">Bộ lọc</span>' : '' ?>
                                    <?= $inRule ? '<span class="tag">Build PC</span>' : '' ?>
                                </div>
                            </td>
                            <td><?= $s['data_type'] === 'number' ? 'Số' : 'Chữ' ?></td>
                            <td class="num"><?= (int) $s['product_count'] ?></td>
                            <td>
                                <div class="row-actions">
                                    <a href="?category=<?= $catId ?>&amp;edit=<?= $s['id'] ?>#spec-form" class="btn btn-sm btn-soft">Sửa</a>
                                    <form method="post" action="?category=<?= $catId ?>"
                                          data-confirm="Xóa thông số &quot;<?= e($s['name']) ?>&quot;?<?= $s['product_count'] ? ' Giá trị của ' . (int) $s['product_count'] . ' sản phẩm cũng bị xóa.' : '' ?>">
                                        <input type="hidden" name="action" value="delete_spec">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <button class="btn btn-sm btn-outline" <?= $inRule ? 'disabled title="Đang dùng để kiểm tra tương thích Build PC"' : '' ?>>Xóa</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$specs): ?>
                        <tr><td colspan="4" class="empty">Danh mục này chưa có thông số. Thêm ở form bên cạnh.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php require 'layout/admin_footer.php'; ?>
