<?php
/** TÌM KIẾM GỢI Ý (AJAX) - [CN 5] Tìm kiếm sản phẩm: gõ tới đâu gợi ý tới đó, trả JSON */
require '../../config.php';

$q = mb_substr(get_str('q'), 0, 100);
if (mb_strlen($q) < 2) {
    json_response(['ok' => true, 'q' => $q, 'total' => 0, 'items' => []]);
}

$like  = "%$q%";
$where = "WHERE p.status = 'active' AND (p.name LIKE ? OR p.short_desc LIKE ? OR p.sku LIKE ?)";
$total = (int) db_value("SELECT COUNT(*) FROM products p $where", [$like, $like, $like]);
$rows  = db_all(product_sql() . " $where ORDER BY p.stock_quantity > 0 DESC, p.name LIMIT 6", [$like, $like, $like]);

$items = [];
foreach ($rows as $p) {
    $items[] = [
        'name'     => $p['name'],
        'category' => $p['category_name'],
        'price'    => money(sale_price($p)),
        'image'    => img_url($p['image']),
        'url'      => url('product.php?id=' . $p['id']),
        'in_stock' => $p['stock_quantity'] > 0,
    ];
}

json_response([
    'ok'      => true,
    'q'       => $q,
    'total'   => $total,
    'items'   => $items,
    'all_url' => url('products.php?q=' . urlencode($q)),
]);
