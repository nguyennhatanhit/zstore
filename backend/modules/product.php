<?php
/** SẢN PHẨM */

/** Câu SELECT sản phẩm dùng chung: kèm tên thương hiệu, danh mục và ảnh chính */
function product_sql()
{
    return "SELECT p.*, b.name AS brand_name, c.name AS category_name, c.slug AS category_slug, c.code AS category_code,
                   (SELECT image_url FROM product_images WHERE product_id = p.id
                     ORDER BY is_primary DESC, sort_order, id LIMIT 1) AS image
            FROM products p
            LEFT JOIN brands b ON b.id = p.brand_id
            LEFT JOIN categories c ON c.id = p.category_id";
}

/** Nhãn tồn kho: Còn hàng / Chỉ còn X / Hết hàng */
function stock_badge($qty)
{
    if ($qty <= 0) {
        return '<span class="badge badge-danger">Hết hàng</span>';
    }
    if ($qty <= LOW_STOCK) {
        return '<span class="badge badge-warning">Chỉ còn ' . (int) $qty . '</span>';
    }
    return '<span class="badge badge-success">Còn hàng</span>';
}
