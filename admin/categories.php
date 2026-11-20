<?php
/** DANH MỤC & THƯƠNG HIỆU */
require '../config.php';
require_admin();

$catErrors   = [];
$brandErrors = [];

// Giá trị mặc định của 2 form (id rỗng = đang thêm mới)
$cat   = ['id' => '', 'name' => '', 'slug' => '', 'parent_id' => '', 'code' => '', 'sort_order' => 0, 'description' => ''];
$brand = ['id' => '', 'name' => ''];

// Chế độ sửa: đổ dữ liệu cũ vào form
if (isset($_GET['edit_category'])) {
    $found = db_one("SELECT * FROM categories WHERE id = ?", [(int) get_str('edit_category', '0')]);
    if ($found) {
        $cat = $found;
    }
}
if (isset($_GET['edit_brand'])) {
    $found = db_one("SELECT * FROM brands WHERE id = ?", [(int) get_str('edit_brand', '0')]);
    if ($found) {
        $brand = $found;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_str('action', '');

    // LƯU DANH MỤC (thêm hoặc sửa)
    if ($action === 'save_category') {
        $cat = [
            'id'          => (int) post_str('id', '0'),
            'name'        => post_str('name'),
            'slug'        => post_str('slug'),
            'parent_id'   => post_str('parent_id') !== '' ? (int) post_str('parent_id', '0') : null,
            'code'        => strtolower(post_str('code')),
            'sort_order'  => (int) post_str('sort_order', '0'),
            'description' => post_str('description'),
        ];
        // Không nhập slug thì tự tạo từ tên
        if ($cat['slug'] === '') {
            $cat['slug'] = slugify($cat['name']);
        }

        // Tên và slug
        if ($cat['name'] === '') {
            $catErrors['name'] = 'Vui lòng nhập tên danh mục.';
        } elseif (!preg_match('/^[a-z0-9-]+$/', $cat['slug'])) {
            $catErrors['slug'] = 'Slug chỉ gồm chữ thường không dấu, số và dấu gạch ngang.';
        } elseif (db_value("SELECT COUNT(*) FROM categories WHERE slug = ? AND id <> ?", [$cat['slug'], $cat['id']])) {
            $catErrors['slug'] = 'Slug đã được dùng cho danh mục khác.';
        }

        // Mã kỹ thuật (không bắt buộc, dùng cho kiểm tra tương thích)
        if ($cat['code'] !== '') {
            if (!preg_match('/^[a-z0-9_]+$/', $cat['code'])) {
                $catErrors['code'] = 'Mã chỉ gồm chữ thường không dấu, số và dấu _.';
            } elseif (db_value("SELECT COUNT(*) FROM categories WHERE code = ? AND id <> ?", [$cat['code'], $cat['id']])) {
                $catErrors['code'] = 'Mã đã được dùng cho danh mục khác.';
            }
        }

        // Danh mục cha: chỉ 2 cấp
        if ($cat['parent_id'] !== null) {
            $parent = db_one("SELECT * FROM categories WHERE id = ?", [$cat['parent_id']]);
            if ($cat['parent_id'] === $cat['id']) {
                $catErrors['parent_id'] = 'Không thể chọn chính danh mục này làm danh mục cha.';
            } elseif (!$parent || $parent['parent_id'] !== null) {
                $catErrors['parent_id'] = 'Chỉ được chọn danh mục cấp 1 làm danh mục cha.';
            } elseif ($cat['id'] && db_value("SELECT COUNT(*) FROM categories WHERE parent_id = ?", [$cat['id']])) {
                $catErrors['parent_id'] = 'Danh mục này đang có danh mục con nên không thể làm danh mục con.';
            }
        }

        if (!$catErrors) {
            $params = [$cat['name'], $cat['slug'], $cat['parent_id'], $cat['code'] ?: null, $cat['sort_order'], $cat['description'] ?: null];
            if ($cat['id']) {
                $params[] = $cat['id'];
                db_run("UPDATE categories SET name = ?, slug = ?, parent_id = ?, code = ?, sort_order = ?, description = ? WHERE id = ?", $params);
                set_flash('success', 'Đã cập nhật danh mục "' . $cat['name'] . '".');
            } else {
                db_run("INSERT INTO categories (name, slug, parent_id, code, sort_order, description) VALUES (?, ?, ?, ?, ?, ?)", $params);
                set_flash('success', 'Đã thêm danh mục "' . $cat['name'] . '".');
            }
            redirect('admin/categories.php');
        }
    }

    // XÓA DANH MỤC
    if ($action === 'delete_category') {
        $id = (int) post_str('id', '0');
        $products = db_value("SELECT COUNT(*) FROM products WHERE category_id = ?", [$id]);
        $children = db_value("SELECT COUNT(*) FROM categories WHERE parent_id = ?", [$id]);

        if ($products > 0) {
            set_flash('error', "Không thể xóa: danh mục đang có $products sản phẩm.");
        } elseif ($children > 0) {
            set_flash('error', "Không thể xóa: danh mục đang có $children danh mục con.");
        } else {
            db_run("DELETE FROM categories WHERE id = ?", [$id]);
            set_flash('success', 'Đã xóa danh mục.');
        }
        redirect('admin/categories.php');
    }

    // LƯU THƯƠNG HIỆU (thêm hoặc sửa)
    if ($action === 'save_brand') {
        $brand = ['id' => (int) post_str('id', '0'), 'name' => post_str('name')];
        $slug  = slugify($brand['name']);

        if ($brand['name'] === '') {
            $brandErrors['name'] = 'Vui lòng nhập tên thương hiệu.';
        } elseif (db_value("SELECT COUNT(*) FROM brands WHERE (name = ? OR slug = ?) AND id <> ?", [$brand['name'], $slug, $brand['id']])) {
            $brandErrors['name'] = 'Thương hiệu này đã tồn tại.';
        }

        if (!$brandErrors) {
            if ($brand['id']) {
                db_run("UPDATE brands SET name = ?, slug = ? WHERE id = ?", [$brand['name'], $slug, $brand['id']]);
                set_flash('success', 'Đã cập nhật thương hiệu "' . $brand['name'] . '".');
            } else {
                db_run("INSERT INTO brands (name, slug) VALUES (?, ?)", [$brand['name'], $slug]);
                set_flash('success', 'Đã thêm thương hiệu "' . $brand['name'] . '".');
            }
            redirect('admin/categories.php');
        }
    }

    // XÓA THƯƠNG HIỆU
    if ($action === 'delete_brand') {
        $id = (int) post_str('id', '0');
        $products = db_value("SELECT COUNT(*) FROM products WHERE brand_id = ?", [$id]);

        if ($products > 0) {
            set_flash('error', "Không thể xóa: thương hiệu đang có $products sản phẩm.");
        } else {
            db_run("DELETE FROM brands WHERE id = ?", [$id]);
            set_flash('success', 'Đã xóa thương hiệu.');
        }
        redirect('admin/categories.php');
    }
}

// LẤY DỮ LIỆU HIỂN THỊ

$categories = db_all("
    SELECT c.*,
           (SELECT COUNT(*) FROM products   WHERE category_id = c.id) AS product_count,
           (SELECT COUNT(*) FROM categories WHERE parent_id   = c.id) AS child_count
    FROM categories c
    ORDER BY c.sort_order, c.name
");

// Sắp thành cây: mỗi danh mục cấp 1, ngay sau là các danh mục con của nó
$tree    = [];
$parents = [];
foreach ($categories as $c) {
    if ($c['parent_id'] === null) {
        $parents[] = $c;
        $tree[] = $c;
        foreach ($categories as $child) {
            if ($child['parent_id'] == $c['id']) {
                $child['is_child'] = true;
                $tree[] = $child;
            }
        }
    }
}

$brands = db_all("
    SELECT b.*, (SELECT COUNT(*) FROM products WHERE brand_id = b.id) AS product_count
    FROM brands b
    ORDER BY b.name
");

$title = 'Danh mục & Thương hiệu';
$breadcrumb = 'Danh mục & Thương hiệu';
require 'layout/admin_header.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Danh mục &amp; Thương hiệu</h1>
        <p class="page-desc">Phân loại sản phẩm theo danh mục (2 cấp) và thương hiệu. Thông số kỹ thuật của từng danh mục khai báo ở trang <a href="<?= url('admin/specs.php') ?>" class="link">Thông số kỹ thuật</a>.</p>
    </div>
</div>

<div class="grid-2">

    <section class="panel" id="category-form">
        <div class="panel-head">
            <div>
                <h2 class="panel-title">Danh mục <span class="panel-count"><?= count($categories) ?></span></h2>
                <p class="panel-desc"><?= $cat['id'] ? 'Đang sửa: <b>' . e($cat['name']) . '</b>' : 'Thêm danh mục mới' ?></p>
            </div>
        </div>

        <form method="post" class="form inline-form" novalidate>
            <input type="hidden" name="action" value="save_category">
            <input type="hidden" name="id" value="<?= e($cat['id']) ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="cat_name">Tên danh mục <span class="req">*</span></label>
                    <input id="cat_name" name="name" class="input <?= invalid($catErrors, 'name') ?>" value="<?= e($cat['name']) ?>" placeholder="VD: Card đồ họa">
                    <?= show_error($catErrors, 'name') ?>
                </div>
                <div class="form-group">
                    <label for="cat_parent">Danh mục cha</label>
                    <select id="cat_parent" name="parent_id" class="select <?= invalid($catErrors, 'parent_id') ?>">
                        <option value="">— Không có (cấp 1) —</option>
                        <?php foreach ($parents as $p): if ($p['id'] == $cat['id']) continue; ?>
                            <option value="<?= $p['id'] ?>" <?= $p['id'] == $cat['parent_id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= show_error($catErrors, 'parent_id') ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="cat_slug">Slug (đường dẫn)</label>
                    <input id="cat_slug" name="slug" class="input <?= invalid($catErrors, 'slug') ?>" value="<?= e($cat['slug']) ?>" placeholder="Để trống sẽ tự tạo từ tên">
                    <?= show_error($catErrors, 'slug') ?>
                </div>
                <div class="form-group">
                    <label for="cat_code">Mã kỹ thuật</label>
                    <input id="cat_code" name="code" class="input <?= invalid($catErrors, 'code') ?>" value="<?= e($cat['code']) ?>" placeholder="VD: cpu, ram, vga">
                    <?= show_error($catErrors, 'code') ?: '<div class="form-hint">Dùng cho kiểm tra tương thích linh kiện</div>' ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="cat_sort">Thứ tự hiển thị</label>
                    <input id="cat_sort" type="number" name="sort_order" class="input" value="<?= (int) $cat['sort_order'] ?>">
                </div>
                <div class="form-group">
                    <label for="cat_desc">Mô tả</label>
                    <input id="cat_desc" name="description" class="input" value="<?= e($cat['description']) ?>">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $cat['id'] ? 'Lưu thay đổi' : '+ Thêm danh mục' ?></button>
                <?php if ($cat['id']): ?>
                    <a href="<?= url('admin/categories.php') ?>" class="btn btn-outline">Hủy</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Tên danh mục</th><th>Mã</th><th class="num">Sản phẩm</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($tree as $c): ?>
                        <tr class="<?= $c['id'] == $cat['id'] ? 'row-editing' : '' ?>">
                            <td>
                                <div class="<?= !empty($c['is_child']) ? 'tree-child' : 'tree-parent' ?>"><?= e($c['name']) ?></div>
                                <div class="cell-sub">/<?= e($c['slug']) ?></div>
                            </td>
                            <td><?= $c['code'] ? '<span class="tag">' . e($c['code']) . '</span>' : '' ?></td>
                            <td class="num"><?= $c['product_count'] ?></td>
                            <td>
                                <div class="row-actions">
                                    <a href="?edit_category=<?= $c['id'] ?>#category-form" class="btn btn-sm btn-soft">Sửa</a>
                                    <form method="post" data-confirm="Xóa danh mục &quot;<?= e($c['name']) ?>&quot;?">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <button class="btn btn-sm btn-outline" <?= ($c['product_count'] || $c['child_count']) ? 'disabled title="Đang có sản phẩm hoặc danh mục con"' : '' ?>>Xóa</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$tree): ?>
                        <tr><td colspan="4" class="empty">Chưa có danh mục.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel" id="brand-form">
        <div class="panel-head">
            <div>
                <h2 class="panel-title">Thương hiệu <span class="panel-count"><?= count($brands) ?></span></h2>
                <p class="panel-desc"><?= $brand['id'] ? 'Đang sửa: <b>' . e($brand['name']) . '</b>' : 'Thêm thương hiệu mới' ?></p>
            </div>
        </div>

        <form method="post" class="form inline-form" novalidate>
            <input type="hidden" name="action" value="save_brand">
            <input type="hidden" name="id" value="<?= e($brand['id']) ?>">

            <div class="form-group">
                <label for="brand_name">Tên thương hiệu <span class="req">*</span></label>
                <input id="brand_name" name="name" class="input <?= invalid($brandErrors, 'name') ?>" value="<?= e($brand['name']) ?>" placeholder="VD: ASUS">
                <?= show_error($brandErrors, 'name') ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $brand['id'] ? 'Lưu thay đổi' : '+ Thêm thương hiệu' ?></button>
                <?php if ($brand['id']): ?>
                    <a href="<?= url('admin/categories.php') ?>" class="btn btn-outline">Hủy</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Thương hiệu</th><th class="num">Sản phẩm</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($brands as $b): ?>
                        <tr class="<?= $b['id'] == $brand['id'] ? 'row-editing' : '' ?>">
                            <td><b><?= e($b['name']) ?></b><div class="cell-sub">/<?= e($b['slug']) ?></div></td>
                            <td class="num"><?= $b['product_count'] ?></td>
                            <td>
                                <div class="row-actions">
                                    <a href="?edit_brand=<?= $b['id'] ?>#brand-form" class="btn btn-sm btn-soft">Sửa</a>
                                    <form method="post" data-confirm="Xóa thương hiệu &quot;<?= e($b['name']) ?>&quot;?">
                                        <input type="hidden" name="action" value="delete_brand">
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                        <button class="btn btn-sm btn-outline" <?= $b['product_count'] ? 'disabled title="Đang có sản phẩm"' : '' ?>>Xóa</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$brands): ?>
                        <tr><td colspan="3" class="empty">Chưa có thương hiệu.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

<?php require 'layout/admin_footer.php'; ?>
