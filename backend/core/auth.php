<?php
/** ĐĂNG NHẬP / PHÂN QUYỀN */

function login_user($user)
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'        => $user['id'],
        'full_name' => $user['full_name'],
        'email'     => $user['email'],
        'role'      => $user['role'],
    ];
}

/** Người đang đăng nhập (null nếu chưa). */
function current_user()
{
    static $checked = false;
    if (!isset($_SESSION['user'])) {
        return null;
    }
    if (!$checked) {
        $checked = true;
        $status = db_value("SELECT status FROM users WHERE id = ?", [$_SESSION['user']['id']]);
        if ($status !== 'active') {
            unset($_SESSION['user']);
            session_regenerate_id(true);
            set_flash('error', 'Tài khoản của bạn đã bị khóa hoặc không còn tồn tại. Vui lòng liên hệ cửa hàng.');
            return null;
        }
    }
    return $_SESSION['user'];
}

function is_admin()
{
    $user = current_user();
    return $user && $user['role'] === 'admin';
}

/** Đặt ở đầu trang cần đăng nhập */
function require_login()
{
    if (!current_user()) {
        if (empty($_SESSION['flash'])) {
            set_flash('error', 'Vui lòng đăng nhập để tiếp tục.');
        }
        redirect('login.php');
    }
}

/** Đặt ở đầu mọi trang trong thư mục admin/ */
function require_admin()
{
    require_login();
    if (!is_admin()) {
        set_flash('error', 'Bạn không có quyền truy cập trang quản trị.');
        redirect('index.php');
    }
}
