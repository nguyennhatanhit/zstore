<?php
# Cấu hình + kết nối CSDL. Mọi trang đều require file này ở dòng đầu tiên

// Đọc file .env (tên cửa hàng, khóa API...)
function load_env($file)
{
    if (!is_file($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        // Bỏ dấu nháy bao quanh: TEN="ATS E-Store" hoặc TEN='ATS E-Store'
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
            $value = substr($value, 1, -1);
        }
        $_ENV[$key] = $value;
    }
}

#Lấy giá trị trong .env, không có thì dùng $default
function env($key, $default = '')
{
    return isset($_ENV[$key]) && $_ENV[$key] !== '' ? $_ENV[$key] : $default;
}

load_env(__DIR__ . '/.env');

// Thông tin website
define('APP_NAME', env('APP_NAME', 'ATS E-Store'));
define('SHOP_HOTLINE', env('SHOP_HOTLINE', '1900 0000'));
define('SHOP_EMAIL', env('SHOP_EMAIL', 'hotro@ats-estore.vn'));
// true khi đang code trên máy (hiện lỗi PHP ra trang). Đưa lên mạng: ĐỂ false (lỗi chỉ ghi vào logs/php-error.log)
define('APP_DEBUG', false);
define('ROOT', __DIR__);

// Đường dẫn gốc của web, tự tính từ vị trí thư mục so với htdocs
$docRoot  = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
$rootPath = str_replace('\\', '/', ROOT);
define('BASE_URL', rtrim(substr($rootPath, strlen($docRoot)), '/'));

// Thông tin MySQL (mặc định XAMPP)
define('DB_HOST', 'localhost');
define('DB_NAME', 'techstore');
define('DB_USER', 'root');
define('DB_PASS', '');

// Cửa hàng
define('SHIPPING_FEE', 30000);
define('FREE_SHIPPING_FROM', 5000000);
define('LOW_STOCK', 5);
define('BANK_NAME', env('BANK_NAME', 'BANK_NAME'));
define('BANK_ACCOUNT', env('BANK_ACCOUNT'));
define('BANK_OWNER', env('BANK_OWNER'));
define('UNPAID_ORDER_HOURS', 24);
define('AI_LIMIT_PER_HOUR', 20);

// PayOS thanh toán
define('PAYOS_CLIENT_ID',    env('PAYOS_CLIENT_ID'));
define('PAYOS_API_KEY',      env('PAYOS_API_KEY'));
define('PAYOS_CHECKSUM_KEY', env('PAYOS_CHECKSUM_KEY'));
// Địa chỉ HTTPS công khai của website (Ngrok khi chạy XAMPP)
define('PAYOS_SITE_URL',     env('PAYOS_SITE_URL'));
// Chữ đầu nội dung chuyển khoản PayOS
define('PAYOS_DESC_PREFIX',  env('PAYOS_DESC_PREFIX', 'ATS'));

// AI
define('AI_PROVIDER', env('AI_PROVIDER', 'groq'));
define('AI_MODEL',    env('AI_MODEL', 'openai/gpt-oss-120b'));
define('AI_API_KEY',  env('AI_API_KEY'));

// Gửi email (mã quên mật khẩu) qua Gmail
define('MAIL_HOST',       env('MAIL_HOST', 'smtp.gmail.com'));
define('MAIL_PORT',       (int) env('MAIL_PORT', '465'));
define('MAIL_ENCRYPTION', env('MAIL_ENCRYPTION', 'ssl'));
define('MAIL_USERNAME',   env('MAIL_USERNAME'));
define('MAIL_PASSWORD',   str_replace(' ', '', (string) env('MAIL_PASSWORD')));
define('MAIL_FROM_NAME',  env('MAIL_FROM_NAME', APP_NAME));

date_default_timezone_set('Asia/Ho_Chi_Minh');

// Hiển thị / ghi lỗi PHP
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/logs/php-error.log');

// Phiên đăng nhập: cookie không cho JavaScript đọc, không gửi kèm khi trang web khác gửi form tới
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Kết nối PDO
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $ex) {
    error_log('DB connect: ' . $ex->getMessage());
    http_response_code(500);
    die('<h3>Không kết nối được CSDL</h3>'
        . '<p>Kiểm tra MySQL trong XAMPP đã Start và đã import database/techstore.sql.</p>'
        . (APP_DEBUG ? '<pre>' . htmlspecialchars($ex->getMessage()) . '</pre>' : ''));
}

// Tự động nạp code: backend/core/ (nền) rồi backend/modules/ (nghiệp vụ)
foreach (['backend/core', 'backend/modules'] as $dir) {
    foreach (glob(ROOT . "/$dir/*.php") as $file) {
        require_once $file;
    }
}

// Bảo vệ form: kiểm tra mã CSRF của mọi yêu cầu POST, tự chèn mã vào mọi form
csrf_verify();
ob_start('csrf_inject');

// Tự hủy đơn QR/chuyển khoản quá hạn chưa thanh toán (trả hàng về kho)
order_auto_cancel_expired();
