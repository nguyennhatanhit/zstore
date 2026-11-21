<?php
/** XEM NHANH SẢN PHẨM (AJAX) - [CN 7] [CN 8] GET ?id= -> JSON chứa HTML khung Xem nhanh */
require '../../config.php';

$id = (int) get_str('id', '0');
$product = db_one(product_sql() . " WHERE p.id = ? AND p.status = 'active'", [$id]);
if (!$product) {
    json_response(['ok' => false, 'message' => 'Sản phẩm không tồn tại hoặc đã ngừng kinh doanh.'], 404);
}

$salePrice = sale_price($product);
$specs = db_all("
    SELECT sd.name, sd.unit, ps.value
    FROM product_specs ps JOIN spec_definitions sd ON sd.id = ps.spec_id
    WHERE ps.product_id = ? ORDER BY sd.sort_order LIMIT 6", [$id]);

ob_start();
require '../layout/quick_view.php';
$html = csrf_inject(ob_get_clean());

json_response(['ok' => true, 'id' => $product['id'], 'name' => $product['name'], 'html' => $html]);
