<?php
/**
 * DANH SÁCH SẢN PHẨM
 *   [CN 4] Xem danh sách và phân loại theo danh mục (gồm cả danh mục con)
 *   [CN 5] Tìm kiếm theo từ khóa (tên, mô tả ngắn, SKU)
 *   [CN 6] Lọc theo giá, thương hiệu, thông số kỹ thuật + sắp xếp
 */
require '../../config.php';

// Đọc bằng get_str()/get_arr(): URL bị sửa tay (VD ?q[]=x) không làm trang lỗi
$q        = mb_substr(get_str('q'), 0, 100);
$slug     = get_str('category');
$brandIds = array_values(array_unique(array_filter(array_map('intval', get_arr('brand')))));
$minPrice = get_str('min_price') !== '' ? max(0, (int) get_str('min_price')) : null;
$maxPrice = get_str('max_price') !== '' ? max(0, (int) get_str('max_price')) : null;
$specFilter = array_filter(array_map('strval', get_arr('spec')), 'strlen');
$inStock  = get_str('in_stock') !== '';
$sort     = get_str('sort', 'newest');

$sorts = [
    'newest'     => ['Mới nhất',        'p.created_at DESC, p.id DESC'],
    'price_asc'  => ['Giá tăng dần',    'p.price ASC'],
    'price_desc' => ['Giá giảm dần',    'p.price DESC'],
    'name'       => ['Tên A → Z',       'p.name ASC'],
];
if (!isset($sorts[$sort])) {
    $sort = 'newest';
}

// Danh mục đang xem (+ danh mục con)
$category = null;
$categoryIds = [];
if ($slug !== '') {
    $category = db_one("SELECT * FROM categories WHERE slug = ?", [$slug]);
    if ($category) {
        $categoryIds = array_merge([$category['id']],
            array_column(db_all("SELECT id FROM categories WHERE parent_id = ?", [$category['id']]), 'id'));
    }
}

// Ghép điều kiện lọc
$where  = ["p.status = 'active'"];
$params = [];

if ($q !== '') {
    $where[] = "(p.name LIKE ? OR p.short_desc LIKE ? OR p.sku LIKE ?)";
    array_push($params, "%$q%", "%$q%", "%$q%");
}
if ($categoryIds) {
    $where[] = "p.category_id IN (" . placeholders($categoryIds) . ")";
    $params = array_merge($params, $categoryIds);
}
if ($brandIds) {
    $where[] = "p.brand_id IN (" . placeholders($brandIds) . ")";
    $params = array_merge($params, $brandIds);
}
if ($minPrice !== null) {
    $where[] = "p.price >= ?";
    $params[] = $minPrice;
}
if ($maxPrice !== null) {
    $where[] = "p.price <= ?";
    $params[] = $maxPrice;
}
if ($inStock) {
    $where[] = "p.stock_quantity > 0";
}
foreach ($specFilter as $code => $value) {
    $where[] = "EXISTS (SELECT 1 FROM product_specs ps JOIN spec_definitions sd ON sd.id = ps.spec_id
                        WHERE ps.product_id = p.id AND sd.code = ? AND ps.value = ?)";
    array_push($params, $code, $value);
}
$whereSql = implode(' AND ', $where);

// Đếm + phân trang + lấy dữ liệu
$total = (int) db_value("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE $whereSql", $params);
$pg = paginate($total, 12);
$products = db_all(product_sql() . " WHERE $whereSql ORDER BY {$sorts[$sort][1]} LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params);

// Dữ liệu cho bộ lọc
$brandSql = "SELECT DISTINCT b.id, b.name FROM brands b JOIN products p ON p.brand_id = b.id WHERE p.status = 'active'";
$brandParams = [];
if ($categoryIds) {
    $brandSql .= " AND p.category_id IN (" . placeholders($categoryIds) . ")";
    $brandParams = $categoryIds;
}
$brands = db_all($brandSql . " ORDER BY b.name", $brandParams);

// Thông số lọc được của danh mục đang xem + các giá trị đang có
$specOptions = [];
if ($category) {
    $defs = db_all("SELECT * FROM spec_definitions WHERE category_id = ? AND is_filterable = 1 ORDER BY sort_order", [$category['id']]);
    foreach ($defs as $d) {
        $values = array_column(db_all("
            SELECT DISTINCT ps.value FROM product_specs ps JOIN products p ON p.id = ps.product_id
            WHERE ps.spec_id = ? AND p.status = 'active' ORDER BY ps.value", [$d['id']]), 'value');
        if ($d['data_type'] === 'number') {
            sort($values, SORT_NUMERIC);
        }
        if (count($values) > 1) {
            $specOptions[] = ['def' => $d, 'values' => $values];
        }
    }
}

// Danh mục con để chuyển nhanh
$subCategories = $category ? db_all("SELECT * FROM categories WHERE parent_id = ? ORDER BY sort_order", [$category['id']]) : [];

if ($q !== '') {
    $heading = 'Kết quả tìm kiếm "' . $q . '"';
} elseif ($category) {
    $heading = $category['name'];
} else {
    $heading = 'Tất cả sản phẩm';
}

$title = $heading;
require '../layout/header.php';
?>

<section class="container section">
    <nav class="breadcrumbs">
        <a href="<?= url('index.php') ?>">Trang chủ</a> <i class="bi bi-chevron-right"></i>
        <a href="<?= url('products.php') ?>">Sản phẩm</a>
        <?php if ($category): ?><i class="bi bi-chevron-right"></i> <span><?= e($category['name']) ?></span><?php endif; ?>
    </nav>

    <div class="page-heading">
        <h1><?= e($heading) ?></h1>
        <?php if ($category && $category['description']): ?><p class="text-muted"><?= e($category['description']) ?></p><?php endif; ?>
    </div>

    <?php if ($subCategories): ?>
        <div class="chips">
            <?php foreach ($subCategories as $sc): ?>
                <a href="<?= url('products.php?category=' . $sc['slug']) ?>" class="chip"><?= e($sc['name']) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="shop-layout">
        <aside>
            <form method="get" class="card filter-box">
                <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
                <?php if ($slug !== ''): ?><input type="hidden" name="category" value="<?= e($slug) ?>"><?php endif; ?>
                <input type="hidden" name="sort" value="<?= e($sort) ?>">

                <div class="filter-group">
                    <h4>Khoảng giá (₫)</h4>
                    <div class="price-range">
                        <input type="number" name="min_price" class="input" placeholder="Từ" min="0" step="100000" value="<?= e($minPrice) ?>">
                        <input type="number" name="max_price" class="input" placeholder="Đến" min="0" step="100000" value="<?= e($maxPrice) ?>">
                    </div>
                </div>

                <?php if ($brands): ?>
                    <div class="filter-group">
                        <h4>Thương hiệu</h4>
                        <?php foreach ($brands as $b): ?>
                            <label class="check"><input type="checkbox" name="brand[]" value="<?= $b['id'] ?>" <?= in_array($b['id'], $brandIds) ? 'checked' : '' ?>> <?= e($b['name']) ?></label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php foreach ($specOptions as $so): $code = $so['def']['code']; ?>
                    <div class="filter-group">
                        <h4><?= e($so['def']['name']) ?></h4>
                        <select name="spec[<?= e($code) ?>]" class="select">
                            <option value="">Tất cả</option>
                            <?php foreach ($so['values'] as $v): ?>
                                <option value="<?= e($v) ?>" <?= (isset($specFilter[$code]) && $specFilter[$code] === $v) ? 'selected' : '' ?>>
                                    <?= e($v) ?><?= $so['def']['unit'] ? ' ' . e($so['def']['unit']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>

                <div class="filter-group">
                    <label class="check"><input type="checkbox" name="in_stock" value="1" <?= $inStock ? 'checked' : '' ?>> Chỉ hiện sản phẩm còn hàng</label>
                </div>

                <button class="btn btn-primary btn-block">Áp dụng bộ lọc</button>
                <a href="<?= url('products.php' . ($slug ? '?category=' . urlencode($slug) : '')) ?>" class="btn btn-outline btn-block mt-8">Xóa bộ lọc</a>
            </form>
        </aside>

        <div>
            <div class="toolbar">
                <span class="text-muted">Tìm thấy <b><?= $total ?></b> sản phẩm</span>
                <form method="get" class="sort-form">
                    <?php foreach ($_GET as $k => $v): if (in_array($k, ['sort', 'page'])) continue; ?>
                        <?php foreach ((array) $v as $kk => $vv): if (!is_scalar($vv)) continue; ?>
                            <input type="hidden" name="<?= e(is_array($v) ? "{$k}[{$kk}]" : $k) ?>" value="<?= e($vv) ?>">
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <label class="text-muted">Sắp xếp:</label>
                    <select name="sort" class="select select-sm" data-autosubmit>
                        <?php foreach ($sorts as $key => $s): ?>
                            <option value="<?= $key ?>" <?= $key === $sort ? 'selected' : '' ?>><?= $s[0] ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if ($products): ?>
                <div class="product-grid grid-3">
                    <?php foreach ($products as $p) { require '../layout/product_card.php'; } ?>
                </div>
                <?= pagination_links($pg) ?>
            <?php else: ?>
                <div class="empty-state card">
                    <i class="bi bi-search"></i>
                    <h3>Không tìm thấy sản phẩm phù hợp</h3>
                    <p class="text-muted">Thử từ khóa khác hoặc bỏ bớt điều kiện lọc.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require '../layout/footer.php'; ?>
