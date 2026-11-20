<?php
/** THANH TOÁN: phương thức, nhãn trạng thái */

function payment_methods()
{
    return [
        'cod'           => 'Thanh toán khi nhận hàng (COD)',
        'bank_transfer' => 'Chuyển khoản ngân hàng',
        'payos'         => 'Thanh toán QR qua PayOS',
    ];
}

/** Tên ngắn hiện trong bảng đơn hàng của admin */
function payment_short($method)
{
    $list = ['cod' => 'COD', 'bank_transfer' => 'Chuyển khoản', 'payos' => 'QR PayOS'];
    return isset($list[$method]) ? $list[$method] : $method;
}

function payment_badge($status)
{
    $list = [
        'unpaid'   => ['Chưa thanh toán', 'warning'],
        'paid'     => ['Đã thanh toán',   'success'],
        'refunded' => ['Đã hoàn tiền',    'danger'],
    ];
    $s = isset($list[$status]) ? $list[$status] : [$status, ''];
    return '<span class="badge badge-' . $s[1] . '">' . e($s[0]) . '</span>';
}
