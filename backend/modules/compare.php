<?php
/** SO SÁNH SẢN PHẨM  ($_SESSION['compare'], tối đa 4) */

function compare_count()
{
    return isset($_SESSION['compare']) ? count($_SESSION['compare']) : 0;
}

/** [CN 9] Thêm vào so sánh (tối đa 4) */
function compare_add($productId)
{
    $productId = (int) $productId;
    if (!db_value("SELECT COUNT(*) FROM products WHERE id = ? AND status = 'active'", [$productId])) {
        return 'Sản phẩm không tồn tại.';
    }
    $list = isset($_SESSION['compare']) ? $_SESSION['compare'] : [];
    if (in_array($productId, $list)) {
        return null;
    }
    if (count($list) >= 4) {
        return 'Chỉ so sánh tối đa 4 sản phẩm. Hãy xóa bớt trước khi thêm.';
    }
    $list[] = $productId;
    $_SESSION['compare'] = $list;
    return null;
}

/** [CN 9] Xóa khỏi so sánh */
function compare_remove($productId)
{
    $list = isset($_SESSION['compare']) ? $_SESSION['compare'] : [];
    $_SESSION['compare'] = array_values(array_diff($list, [(int) $productId]));
}
