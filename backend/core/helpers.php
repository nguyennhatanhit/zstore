<?php
/** HÀM TIỆN ÍCH DÙNG CHUNG: hiển thị, định dạng, form, chuyển trang, thông báo, phân trang, upload */

/** Chống XSS: luôn dùng khi in dữ liệu ra HTML */
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Đường dẫn tuyệt đối: url('cart.php') -> /techstore/cart.php */
function url($path = '')
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Ảnh sản phẩm (không có ảnh thì dùng ảnh mặc định) */
function img_url($path)
{
    return url($path ?: 'frontend/assets/img/no-image.svg');
}

/** 4290000 -> 4.290.000 ₫ */
function money($amount)
{
    return number_format((float) $amount, 0, ',', '.') . "\u{00A0}₫";
}

function format_date($datetime, $format = 'd/m/Y H:i')
{
    return $datetime ? date($format, strtotime($datetime)) : '';
}

/** "Nguyễn Văn An" -> "VA" (dùng cho ảnh đại diện chữ) */
function initials($name)
{
    $out = '';
    foreach (array_slice(preg_split('/\s+/', trim($name)), -2) as $w) {
        $out .= mb_strtoupper(mb_substr($w, 0, 1));
    }
    return $out;
}

/** Trang hiện tại có phải $path không (để tô đậm menu): is_page('admin/index.php') */
function is_page($path)
{
    // Trang khách nằm trong frontend/pages/ nhưng địa chỉ web vẫn là /cart.php (xem .htaccess)
    return str_replace('/frontend/pages/', '/', $_SERVER['SCRIPT_NAME']) === url($path);
}

/** Lỗi của 1 ô nhập: <?= show_error($errors, 'email') ?> */
function show_error($errors, $field)
{
    return isset($errors[$field]) ? '<div class="form-error">' . e($errors[$field]) . '</div>' : '';
}

/** Viền đỏ cho ô lỗi: class="input <?= invalid($errors, 'email') ?>" */
function invalid($errors, $field)
{
    return isset($errors[$field]) ? 'is-invalid' : '';
}

/** Tạo slug không dấu cho URL: slugify('Card đồ họa') -> 'card-do-hoa' */
function slugify($text)
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $map = [
        'a' => 'à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ',
        'e' => 'è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ',
        'i' => 'ì|í|ị|ỉ|ĩ',
        'o' => 'ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ',
        'u' => 'ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ',
        'y' => 'ỳ|ý|ỵ|ỷ|ỹ',
        'd' => 'đ',
    ];
    foreach ($map as $to => $from) {
        $text = preg_replace("/($from)/u", $to, $text);
    }
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

function is_valid_phone($phone)
{
    return (bool) preg_match('/^(0|\+84)[0-9]{9}$/', $phone);
}

function redirect($path)
{
    header('Location: ' . url($path));
    exit;
}

/** Quay lại trang trước (form gửi kèm ô ẩn name="back"), không có thì về $fallback */
function redirect_back($fallback = 'index.php')
{
    $back = post_str('back', '');
    if ($back !== '' && $back[0] === '/' && strpos($back, '//') === false) {
        header('Location: ' . $back);
        exit;
    }
    redirect($fallback);
}

/** Ô ẩn chứa địa chỉ trang hiện tại, đặt trong form cần quay lại trang cũ */
function back_field()
{
    return '<input type="hidden" name="back" value="' . e($_SERVER['REQUEST_URI']) . '">';
}

/** Ghi thông báo để hiện ở trang kế tiếp: set_flash('success', 'Đã lưu') */
function set_flash($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** In thông báo (header.php đã gọi sẵn) */
function show_flash()
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $icon = $f['type'] === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle';
    return '<div class="alert alert-' . e($f['type']) . '"><i class="bi ' . $icon . '"></i>'
         . '<span>' . e($f['message']) . '</span>'
         . '<button type="button" class="alert-close" aria-label="Đóng"><i class="bi bi-x-lg"></i></button></div>';
}

/** Trả JSON cho AJAX rồi dừng */
function json_response($data, $status = 200)
{
    while (ob_get_level() > 0) {
        ob_end_clean(); // bỏ bộ đệm csrf_inject: JSON phải được gửi nguyên vẹn
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Phân trang: $p = paginate($tongSoDong, 12) */
function paginate($total, $perPage = 12)
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = (int) get_str('page', '0');
    $page  = min($pages, max(1, $page));
    return ['page' => $page, 'pages' => $pages, 'limit' => $perPage, 'offset' => ($page - 1) * $perPage, 'total' => $total];
}

/** In các nút số trang, giữ nguyên các tham số lọc khác trên URL */
function pagination_links($p)
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $html = '<nav class="pagination">';
    for ($i = 1; $i <= $p['pages']; $i++) {
        $query = http_build_query(array_merge($_GET, ['page' => $i]));
        $html .= '<a href="?' . e($query) . '" class="' . ($i === $p['page'] ? 'active' : '') . '">' . $i . '</a>';
    }
    return $html . '</nav>';
}

/** Lưu ảnh từ <input type="file" name="image"> vào uploads/products/ */
function upload_image($file, $folder = 'products')
{
    if (empty($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, 'Chưa chọn ảnh.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Tải ảnh thất bại.'];
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return [null, 'Ảnh tối đa 2MB.'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) || !getimagesize($file['tmp_name'])) {
        return [null, 'Chỉ chấp nhận ảnh JPG, PNG, WEBP.'];
    }
    $path = "uploads/$folder/" . uniqid() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], ROOT . '/' . $path)) {
        return [null, 'Không lưu được ảnh.'];
    }
    return [$path, null];
}

/** Biểu tượng (Bootstrap Icons) theo mã danh mục, dùng cho menu và ô danh mục ở trang chủ */
function category_icon($code)
{
    $icons = [
        'cpu' => 'bi-cpu', 'mainboard' => 'bi-motherboard', 'ram' => 'bi-memory', 'vga' => 'bi-gpu-card',
        'storage' => 'bi-device-ssd', 'psu' => 'bi-plug', 'case' => 'bi-pc', 'cooler' => 'bi-fan',
        'laptop' => 'bi-laptop', 'pc' => 'bi-pc-display',
    ];
    return isset($icons[$code]) ? $icons[$code] : 'bi-box';
}
