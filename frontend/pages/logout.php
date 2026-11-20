<?php
/** ĐĂNG XUẤT - [CN 2] Đăng xuất  ✅ ĐÃ XONG */
require '../../config.php';

// Xóa sạch phiên: tài khoản, giỏ hàng, mã giảm giá, so sánh, lịch sử chat AI (máy dùng chung không lộ dữ liệu)
$_SESSION = [];
session_regenerate_id(true);
set_flash('success', 'Bạn đã đăng xuất.');
redirect('index.php');
