<?php
/** THÊM / SỬA SẢN PHẨM */
require '../config.php';
require_admin();

$id = (int) get_str('id', '0');
$product = $id ? db_one("SELECT * FROM products WHERE id = ?", [$id]) : null;
if ($id && !$product) {
    set_flash('error', 'Không tìm thấy sản phẩm.');
    redirect('admin/products.php');
}

$errors = [];
$form = $product ?: [
    'name' => '', 'slug' => '', 'sku' => '', 'category_id' => '', 'brand_id' => '', 'price' => '',
    'warranty_months' => 12, 'status' => 'active', 'short_desc' => '', 'description' => '', 'stock_quantity' => 0,
];
// Thông số hiện tại: [spec_id => value]
$specValues = $id ? array_column(db_all("SELECT spec_id, value FROM product_specs WHERE product_id = ?", [$id]), 'value', 'spec_id') : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_str('action', 'save');

    // Thao tác với ảnh có sẵn
    if ($action === 'set_primary' && $id) {
        db_run("UPDATE product_images SET is_primary = 0 WHERE product_id = ?", [$id]);
        db_run("UPDATE product_images SET is_primary = 1 WHERE id = ? AND product_id = ?", [(int) post_str('image_id', '0'), $id]);
        set_flash('success', 'Đã đặt ảnh chính.');
        redirect('admin/product_form.php?id=' . $id . '#images');
    }
    if ($action === 'delete_image' && $id) {
        $img = db_one("SELECT * FROM product_images WHERE id = ? AND product_id = ?", [(int) post_str('image_id', '0'), $id]);
        if ($img) {
            if (strpos($img['image_url'], 'uploads/') === 0 && is_file(ROOT . '/' . $img['image_url'])) {
                unlink(ROOT . '/' . $img['image_url']);
            }
            db_run("DELETE FROM product_images WHERE id = ?", [$img['id']]);
            // Xóa ảnh chính thì lấy ảnh đầu tiên còn lại làm ảnh chính
            if ($img['is_primary']) {
                $first = db_value("SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order, id LIMIT 1", [$id]);
                if ($first) {
                    db_run("UPDATE product_images SET is_primary = 1 WHERE id = ?", [$first]);
                }
            }
            set_flash('success', 'Đã xóa ảnh.');
        }
        redirect('admin/product_form.php?id=' . $id . '#images');
    }

    // Lưu thông tin sản phẩm
    $form = [
        'name'            => post_str('name'),
        'slug'            => post_str('slug'),
        'sku'             => strtoupper(post_str('sku')),
        'category_id'     => (int) post_str('category_id', '0'),
        'brand_id'        => post_str('brand_id') !== '' ? (int) post_str('brand_id', '0') : null,
        'price'           => post_str('price'),
        'warranty_months' => (int) post_str('warranty_months', '0'),
        'status'          => post_str('status') === 'hidden' ? 'hidden' : 'active',
        'short_desc'      => post_str('short_desc'),
        'description'     => post_str('description'),
        'stock_quantity'  => $product ? $product['stock_quantity'] : (int) post_str('stock_quantity', '0'),
    ];
    if ($form['slug'] === '') {
        $form['slug'] = slugify($form['name']);
    }
    $specValues = array_map('trim', post_arr('specs'));

    if ($form['name'] === '') {
        $errors['name'] = 'Vui lòng nhập tên sản phẩm.';
    } elseif (!preg_match('/^[a-z0-9-]+$/', $form['slug'])) {
        $errors['slug'] = 'Slug chỉ gồm chữ thường không dấu, số và dấu gạch ngang.';
    } elseif (db_value("SELECT COUNT(*) FROM products WHERE slug = ? AND id <> ?", [$form['slug'], $id])) {
        $errors['slug'] = 'Slug đã được dùng cho sản phẩm khác.';
    }
    if ($form['sku'] === '') {
        $errors['sku'] = 'Vui lòng nhập mã SKU.';
    } elseif (db_value("SELECT COUNT(*) FROM products WHERE sku = ? AND id <> ?", [$form['sku'], $id])) {
        $errors['sku'] = 'SKU đã tồn tại.';
    }
    if (!db_value("SELECT COUNT(*) FROM categories WHERE id = ?", [$form['category_id']])) {
        $errors['category_id'] = 'Vui lòng chọn danh mục.';
    }
    if (!is_numeric($form['price']) || $form['price'] < 0) {
        $errors['price'] = 'Giá phải là số không âm.';
    }
    if (!$product && $form['stock_quantity'] < 0) {
        $errors['stock_quantity'] = 'Tồn kho không được âm.';
    }

    // Kiểm tra ảnh tải lên trước khi lưu
    $uploads = [];
    if (!empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['name'] as $i => $name) {
            $file = [
                'name' => $name, 'tmp_name' => $_FILES['images']['tmp_name'][$i],
                'error' => $_FILES['images']['error'][$i], 'size' => $_FILES['images']['size'][$i],
            ];
            if ($file['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploads[] = $file;
            }
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $data = [$form['name'], $form['slug'], $form['sku'], $form['category_id'], $form['brand_id'], $form['price'],
                     $form['warranty_months'], $form['status'], $form['short_desc'] ?: null, $form['description'] ?: null];

            if ($product) {
                $data[] = db_now();
                $data[] = $id;
                db_run("UPDATE products SET name = ?, slug = ?, sku = ?, category_id = ?, brand_id = ?, price = ?, warranty_months = ?,
                        status = ?, short_desc = ?, description = ?, updated_at = ? WHERE id = ?", $data);
            } else {
                $data[] = $form['stock_quantity'];
                $data[] = db_now();
                db_run("INSERT INTO products (name, slug, sku, category_id, brand_id, price, warranty_months, status, short_desc, description,
                        stock_quantity, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $data);
                $id = (int) db_last_id();
                if ($form['stock_quantity'] > 0) {
                    db_run("INSERT INTO inventory_logs (product_id, change_qty, type, note, created_by, created_at) VALUES (?, ?, 'import', 'Nhập kho ban đầu', ?, ?)",
                        [$id, $form['stock_quantity'], current_user()['id'], db_now()]);
                }
            }

            // Thông số: chỉ lưu các thông số thuộc danh mục đã chọn
            db_run("DELETE FROM product_specs WHERE product_id = ?", [$id]);
            $validSpecIds = array_column(db_all("SELECT id FROM spec_definitions WHERE category_id = ?", [$form['category_id']]), 'id');
            foreach ($specValues as $specId => $value) {
                if ($value !== '' && in_array($specId, $validSpecIds)) {
                    db_run("INSERT INTO product_specs (product_id, spec_id, value) VALUES (?, ?, ?)", [$id, $specId, $value]);
                }
            }
            $pdo->commit();
        } catch (Exception $ex) {
            $pdo->rollBack();
            $errors['general'] = 'Không lưu được sản phẩm: ' . $ex->getMessage();
        }

        if (!$errors) {
            // Ảnh: lưu sau khi đã có id sản phẩm
            $imgErrors = [];
            $hasPrimary = db_value("SELECT COUNT(*) FROM product_images WHERE product_id = ? AND is_primary = 1", [$id]);
            foreach ($uploads as $file) {
                list($path, $err) = upload_image($file);
                if ($err) {
                    $imgErrors[] = $file['name'] . ': ' . $err;
                    continue;
                }
                db_run("INSERT INTO product_images (product_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)",
                    [$id, $path, $hasPrimary ? 0 : 1, (int) db_value("SELECT COUNT(*) FROM product_images WHERE product_id = ?", [$id])]);
                $hasPrimary = 1;
            }
            set_flash($imgErrors ? 'warning' : 'success',
                ($product ? 'Đã cập nhật sản phẩm.' : 'Đã thêm sản phẩm.') . ($imgErrors ? ' Một số ảnh lỗi: ' . implode('; ', $imgErrors) : ''));
            redirect('admin/product_form.php?id=' . $id);
        }
    }
}

$categories = db_all("SELECT * FROM categories ORDER BY parent_id IS NOT NULL, sort_order, name");
$brands     = db_all("SELECT * FROM brands ORDER BY name");
$specDefs   = db_all("SELECT * FROM spec_definitions ORDER BY category_id, sort_order");
$specsByCat = [];
foreach ($specDefs as $d) {
    $specsByCat[$d['category_id']][] = $d;
}
$images = $id ? db_all("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order, id", [$id]) : [];

$title = $product ? 'Sửa sản phẩm' : 'Thêm sản phẩm';
$breadcrumb = $title;
require 'layout/admin_header.php';
?>

<a href="<?= url('admin/products.php') ?>" class="back-link"><i class="bi bi-arrow-left"></i> Quay lại danh sách</a>

<div class="page-head">
    <div>
        <h1 class="page-title"><?= $product ? e($product['name']) : 'Thêm sản phẩm' ?></h1>
        <p class="page-desc"><?= $product ? 'SKU ' . e($product['sku']) . ' · Tồn kho ' . $product['stock_quantity'] : 'Nhập thông tin, thông số kỹ thuật và hình ảnh.' ?></p>
    </div>
    <?php if ($product && $product['status'] === 'active'): ?>
        <div class="page-actions"><a href="<?= url('product.php?id=' . $id) ?>" target="_blank" class="btn btn-outline"><i class="bi bi-box-arrow-up-right"></i> Xem trên cửa hàng</a></div>
    <?php endif; ?>
</div>

<?php if (isset($errors['general'])): ?>
    <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i><span><?= e($errors['general']) ?></span></div>
<?php elseif ($errors): ?>
    <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i><span>Vui lòng kiểm tra lại các ô được đánh dấu.</span></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="action" value="save">
    <div class="detail-grid">
        <div class="stack">
            <section class="panel">
                <div class="panel-head"><h2 class="panel-title">Thông tin sản phẩm</h2></div>
                <div class="panel-body form">
                    <div class="form-group">
                        <label for="name">Tên sản phẩm <span class="req">*</span></label>
                        <input id="name" name="name" class="input <?= invalid($errors, 'name') ?>" value="<?= e($form['name']) ?>">
                        <?= show_error($errors, 'name') ?>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="sku">Mã SKU <span class="req">*</span></label>
                            <input id="sku" name="sku" class="input <?= invalid($errors, 'sku') ?>" value="<?= e($form['sku']) ?>" placeholder="VD: CPU-I5-14400F">
                            <?= show_error($errors, 'sku') ?>
                        </div>
                        <div class="form-group">
                            <label for="slug">Slug</label>
                            <input id="slug" name="slug" class="input <?= invalid($errors, 'slug') ?>" value="<?= e($form['slug']) ?>" placeholder="Để trống sẽ tự tạo từ tên">
                            <?= show_error($errors, 'slug') ?>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="category_id">Danh mục <span class="req">*</span></label>
                            <select id="category_id" name="category_id" class="select <?= invalid($errors, 'category_id') ?>" data-spec-switch>
                                <option value="">— Chọn danh mục —</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $form['category_id'] == $c['id'] ? 'selected' : '' ?>><?= $c['parent_id'] ? '— ' : '' ?><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= show_error($errors, 'category_id') ?>
                        </div>
                        <div class="form-group">
                            <label for="brand_id">Thương hiệu</label>
                            <select id="brand_id" name="brand_id" class="select">
                                <option value="">— Không có —</option>
                                <?php foreach ($brands as $b): ?>
                                    <option value="<?= $b['id'] ?>" <?= $form['brand_id'] == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="short_desc">Mô tả ngắn</label>
                        <input id="short_desc" name="short_desc" class="input" maxlength="500" value="<?= e($form['short_desc']) ?>" placeholder="Hiện ở danh sách sản phẩm, VD: 10 nhân 16 luồng, socket LGA1700">
                    </div>
                    <div class="form-group">
                        <label for="description">Mô tả chi tiết</label>
                        <textarea id="description" name="description" rows="8" class="textarea"><?= e($form['description']) ?></textarea>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title">Thông số kỹ thuật</h2>
                        <p class="panel-desc">Các ô thay đổi theo danh mục. Thông số dùng cho bộ lọc, so sánh và kiểm tra tương thích.</p>
                    </div>
                </div>
                <div class="panel-body">
                    <p class="text-muted" data-spec-empty>Danh mục này chưa có thông số nào.</p>
                    <?php foreach ($specsByCat as $catId => $defs): ?>
                        <div class="spec-grid" data-spec-group="<?= $catId ?>">
                            <?php foreach ($defs as $d): ?>
                                <div class="form-group">
                                    <label for="spec<?= $d['id'] ?>"><?= e($d['name']) ?><?= $d['unit'] ? ' (' . e($d['unit']) . ')' : '' ?></label>
                                    <input id="spec<?= $d['id'] ?>" name="specs[<?= $d['id'] ?>]" class="input"
                                           <?= $d['data_type'] === 'number' ? 'inputmode="decimal"' : '' ?>
                                           value="<?= e(isset($specValues[$d['id']]) ? $specValues[$d['id']] : '') ?>">
                                    <div class="form-hint">Mã: <?= e($d['code']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <aside class="stack">
            <section class="panel">
                <div class="panel-head"><h2 class="panel-title">Giá &amp; trạng thái</h2></div>
                <div class="panel-body form">
                    <div class="form-group">
                        <label for="price">Giá bán (₫) <span class="req">*</span></label>
                        <input id="price" type="number" name="price" min="0" step="1000" class="input <?= invalid($errors, 'price') ?>" value="<?= e($form['price']) ?>">
                        <?= show_error($errors, 'price') ?>
                    </div>
                    <div class="form-group">
                        <label for="warranty_months">Bảo hành (tháng)</label>
                        <input id="warranty_months" type="number" name="warranty_months" min="0" class="input" value="<?= (int) $form['warranty_months'] ?>">
                    </div>
                    <?php if (!$product): ?>
                        <div class="form-group">
                            <label for="stock_quantity">Tồn kho ban đầu</label>
                            <input id="stock_quantity" type="number" name="stock_quantity" min="0" class="input <?= invalid($errors, 'stock_quantity') ?>" value="<?= (int) $form['stock_quantity'] ?>">
                            <?= show_error($errors, 'stock_quantity') ?>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label>Tồn kho</label>
                            <div><?= stock_badge($product['stock_quantity']) ?> <b><?= $product['stock_quantity'] ?></b>
                                · <a href="<?= url('admin/inventory.php?product_id=' . $id) ?>" class="link">Nhập / xuất kho</a></div>
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="status">Trạng thái</label>
                        <select id="status" name="status" class="select">
                            <option value="active" <?= $form['status'] === 'active' ? 'selected' : '' ?>>Đang bán</option>
                            <option value="hidden" <?= $form['status'] === 'hidden' ? 'selected' : '' ?>>Ẩn khỏi cửa hàng</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head"><h2 class="panel-title">Tải ảnh lên</h2></div>
                <div class="panel-body form">
                    <input type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp" multiple class="input input-file">
                    <div class="form-hint">Chọn được nhiều ảnh · JPG, PNG, WEBP · tối đa 2MB/ảnh. Ảnh đầu tiên sẽ là ảnh chính nếu chưa có.</div>
                </div>
            </section>

            <button class="btn btn-primary btn-lg btn-block"><i class="bi bi-check-lg"></i> <?= $product ? 'Lưu thay đổi' : 'Thêm sản phẩm' ?></button>
        </aside>
    </div>
</form>

<?php if ($product): ?>
    <section class="panel mt-20" id="images">
        <div class="panel-head">
            <div>
                <h2 class="panel-title">Hình ảnh <span class="panel-count"><?= count($images) ?></span></h2>
                <p class="panel-desc">Ảnh chính hiển thị ở danh sách sản phẩm.</p>
            </div>
        </div>
        <div class="panel-body">
            <?php if (!$images): ?><p class="text-muted">Chưa có ảnh. Tải ảnh lên ở khung bên trên rồi bấm Lưu.</p><?php endif; ?>
            <div class="image-grid">
                <?php foreach ($images as $img): ?>
                    <div class="image-item <?= $img['is_primary'] ? 'primary' : '' ?>">
                        <img src="<?= img_url($img['image_url']) ?>" alt="" onerror="this.onerror=null;this.src='<?= img_url('') ?>'">
                        <?php if ($img['is_primary']): ?><span class="badge badge-info">Ảnh chính</span><?php endif; ?>
                        <div class="image-actions">
                            <?php if (!$img['is_primary']): ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="set_primary">
                                    <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                                    <button class="btn btn-sm btn-soft">Đặt làm ảnh chính</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" data-confirm="Xóa ảnh này?">
                                <input type="hidden" name="action" value="delete_image">
                                <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                                <button class="btn btn-sm btn-outline"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php require 'layout/admin_footer.php'; ?>
