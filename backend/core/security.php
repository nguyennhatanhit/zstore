<?php
/** BẢO MẬT DÙNG CHUNG */

// CSRF

function csrf_token()
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

/** Ô ẩn chứa mã CSRF (thường không cần gọi tay: csrf_inject() tự chèn) */
function csrf_field()
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

/** Bộ lọc output: chèn mã CSRF ngay sau mọi thẻ <form ... method="post" ...> */
function csrf_inject($html)
{
    return preg_replace(
        '/<form\b[^>]*\bmethod\s*=\s*["\']post["\'][^>]*>/i',
        '$0' . csrf_field(),
        $html
    );
}

/** Token gửi lên có đúng không (dùng cho trang trả JSON) */
function csrf_valid()
{
    $sent = isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : '';
    return $sent !== '' && hash_equals(csrf_token(), $sent);
}

/** Kiểm tra mọi yêu cầu POST (gọi trong config.php). */
function csrf_verify()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || defined('SKIP_CSRF')) {
        return;
    }
    $sent = isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : '';
    if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
        set_flash('error', 'Phiên làm việc đã hết hạn hoặc yêu cầu không hợp lệ. Vui lòng thử lại.');
        header('Location: ' . $_SERVER['REQUEST_URI'], true, 303);
        exit;
    }
}

// GIỚI HẠN TẦN SUẤT

function client_ip()
{
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

/** Số lần $action của $ident trong $minutes phút gần nhất */
function rate_count($action, $ident, $minutes)
{
    // Nếu CSDL chưa có bảng rate_limits (chưa import migrate_v2_bao_mat.sql) thì bỏ qua giới hạn,
    try {
        return (int) db_value(
            "SELECT COUNT(*) FROM rate_limits WHERE action = ? AND ident = ? AND created_at >= ?",
            [$action, mb_substr($ident, 0, 190), date('Y-m-d H:i:s', time() - $minutes * 60)]
        );
    } catch (PDOException $e) {
        rate_table_missing($e);
        return 0;
    }
}

/** Ghi 1 lần (thỉnh thoảng dọn bản ghi cũ hơn 1 ngày) */
function rate_hit($action, $ident)
{
    try {
        db_run("INSERT INTO rate_limits (action, ident, created_at) VALUES (?, ?, ?)",
            [$action, mb_substr($ident, 0, 190), db_now()]);
        if (random_int(1, 50) === 1) {
            db_run("DELETE FROM rate_limits WHERE created_at < ?", [date('Y-m-d H:i:s', time() - 86400)]);
        }
    } catch (PDOException $e) {
        rate_table_missing($e);
    }
}

function rate_clear($action, $ident)
{
    try {
        db_run("DELETE FROM rate_limits WHERE action = ? AND ident = ?", [$action, mb_substr($ident, 0, 190)]);
    } catch (PDOException $e) {
        rate_table_missing($e);
    }
}

/** Bảng rate_limits chưa có hoặc sai cột: ghi log 1 lần mỗi lượt tải trang, trang vẫn chạy tiếp */
function rate_table_missing(PDOException $e)
{
    static $logged = false;
    if (!$logged) {
        error_log('[' . APP_NAME . '] Bảng rate_limits chưa sẵn sàng, tạm bỏ qua giới hạn tần suất: ' . $e->getMessage());
        $logged = true;
    }
}

// ĐỌC THAM SỐ AN TOÀN

/** Chuỗi từ $_GET (thiếu hoặc là mảng → $default) */
function get_str($key, $default = '')
{
    return isset($_GET[$key]) && is_scalar($_GET[$key]) ? trim((string) $_GET[$key]) : $default;
}

/** Chuỗi từ $_POST, $trim = false cho mật khẩu */
function post_str($key, $default = '', $trim = true)
{
    if (!isset($_POST[$key]) || !is_scalar($_POST[$key])) {
        return $default;
    }
    return $trim ? trim((string) $_POST[$key]) : (string) $_POST[$key];
}

/** Mảng từ $_GET / $_POST (thiếu → []), chỉ giữ phần tử dạng chuỗi/số */
function get_arr($key)
{
    return isset($_GET[$key]) && is_array($_GET[$key]) ? array_filter($_GET[$key], 'is_scalar') : [];
}

function post_arr($key)
{
    return isset($_POST[$key]) && is_array($_POST[$key]) ? array_filter($_POST[$key], 'is_scalar') : [];
}
