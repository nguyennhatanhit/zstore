# ATS E-Store – Website cửa hàng Linh kiện, Máy tính, Laptop

Đồ án môn học · HTML, CSS, JavaScript, PHP thuần, MySQL · chạy trên XAMPP.
Danh sách chức năng và tiến độ: xem **FEATURES.md**. Giải thích hệ thống chi tiết: xem **HUONG_DAN_HE_THONG.md**.

## Cài đặt
1. Chép thư mục `techstore` vào `C:\xampp\htdocs\`.
2. XAMPP: Start **Apache** và **MySQL**.
3. phpMyAdmin → Import → `database/techstore.sql` (tự tạo database `techstore`).
   CSDL đã có dữ liệu từ bản cũ: chạy `database/migrate_v2_bao_mat.sql` (thêm bảng `rate_limits`) thay vì import lại.
   Thêm nhiều sản phẩm mẫu (111 sản phẩm, đủ 10 danh mục): chọn database `techstore` → Import → `database/du_lieu_san_pham.sql`.
4. Mở `http://localhost/techstore/`.
5. (Tùy chọn) Gửi mã quên mật khẩu qua Gmail: bật Xác minh 2 bước cho Gmail của cửa hàng → tạo **Mật khẩu ứng dụng** →
   điền `MAIL_USERNAME` và `MAIL_PASSWORD` trong `.env`. Để trống thì mã được ghi vào `logs/mail.log` (chạy thử trên máy).

| Vai trò | Email | Mật khẩu |
|---|---|---|
| Quản trị | admin@techstore.vn | admin123 |
| Khách hàng | khach@gmail.com | 123456 |

Đường dẫn gốc (`BASE_URL`) tự nhận theo tên thư mục, không cần sửa.

## Cấu trúc
```
techstore/
├── index.php                Trang chủ
├── payos-create.php         Tạo / mở lại link QR PayOS cho đơn
├── payos-return.php         PayOS đưa khách về → hỏi lại PayOS → ghi nhận thanh toán
├── payos-webhook.php        PayOS báo có tiền vào (khai báo URL này trên my.payos.vn)
├── config.php               Cấu hình, kết nối CSDL, tự nạp backend/ (mọi trang require đầu tiên)
├── .htaccess                Giữ địa chỉ ngắn: /cart.php -> frontend/pages/cart.php
│
├── frontend/                ══ GIAO DIỆN KHÁCH HÀNG ══
│   ├── pages/               register, login, logout, account, change_password,
│   │                        products, product, compare, cart, build-pc, checkout,
│   │                        orders, order, ai
│   ├── layout/              header, footer, product_card, account_sidebar
│   └── assets/              css/ (style.css, admin.css) · js/main.js · img/
│
├── admin/                   ══ GIAO DIỆN QUẢN TRỊ ══
│   ├── index.php  products.php  product_form.php  categories.php  specs.php  inventory.php
│   ├── promotions.php  orders.php  order_detail.php  customers.php  customer_detail.php
│   └── layout/              admin_header, admin_footer
│
├── backend/                 ══ XỬ LÝ (trình duyệt không mở trực tiếp được) ══
│   ├── core/                db.php · helpers.php · auth.php · security.php (CSRF, giới hạn tần suất, đọc tham số an toàn)
│   └── modules/             product · cart · promotion · compare · order ·
│                            payment · payos · compatibility · ai
│
├── uploads/products/        Ảnh sản phẩm tải lên
├── logs/                    php-error.log (chặn truy cập từ trình duyệt)
└── database/                techstore.sql · migrate_*.sql (cập nhật CSDL đang dùng)
```

> Địa chỉ web không đổi (`localhost/techstore/cart.php`) nhờ `.htaccess` (cần `mod_rewrite`, XAMPP bật sẵn).
> Thêm chức năng: viết hàm trong `backend/modules/`, trang mới đặt trong `frontend/pages/` (đầu file `require '../../config.php';`).

Mỗi file có ghi chú ở đầu: chức năng phục vụ, bảng CSDL dùng.

## Tên cửa hàng và khóa API (file `.env`)
Tên cửa hàng, liên hệ, tài khoản ngân hàng và các khóa API nằm trong file `.env` ở thư mục gốc
(bị `.htaccess` chặn truy cập từ trình duyệt, không đưa lên GitHub nhờ `.gitignore`).
Máy mới: chép `.env.example` thành `.env` rồi điền khóa. `config.php` đọc `.env` và gán vào các hằng số dùng trong code.
- `APP_NAME`: tên cửa hàng (đầu trang, chân trang, tiêu đề tab, lời chào AI). **Đổi tên web chỉ cần sửa dòng này.**
- `SHOP_HOTLINE`, `SHOP_EMAIL`: liên hệ ở chân trang
- `BANK_NAME`, `BANK_ACCOUNT`, `BANK_OWNER`: thông tin chuyển khoản hiện cho khách
- `PAYOS_CLIENT_ID`, `PAYOS_API_KEY`, `PAYOS_CHECKSUM_KEY`, `PAYOS_SITE_URL`, `PAYOS_DESC_PREFIX`: PayOS
- `AI_PROVIDER` (gemini | openai | groq), `AI_MODEL`, `AI_API_KEY`: AI tư vấn (để trống khóa thì tắt AI)

## Cấu hình khác (`config.php`)
- `SHIPPING_FEE`, `FREE_SHIPPING_FROM`: phí giao hàng và mức miễn phí
- `LOW_STOCK`: ngưỡng cảnh báo sắp hết hàng
- `APP_DEBUG`: `true` khi đang code (hiện lỗi PHP), **`false` khi đưa lên mạng** (lỗi chỉ ghi vào `logs/php-error.log`)
- `UNPAID_ORDER_HOURS`: đơn QR / chuyển khoản quá số giờ này chưa trả tiền thì tự hủy, trả hàng về kho
- `AI_LIMIT_PER_HOUR`: số câu hỏi AI tối đa mỗi giờ cho 1 địa chỉ IP

## Bảo mật đã có
- **CSRF:** mọi form `method="post"` tự được chèn mã `_csrf` (không cần sửa từng trang); yêu cầu POST thiếu/sai mã bị từ chối.
  Trang nhận dữ liệu từ máy chủ khác (webhook) đặt `define('SKIP_CSRF', true);` trước khi require config.
- **Cookie phiên** `HttpOnly`, `SameSite=Lax`, `Secure` khi chạy HTTPS. Đăng xuất xóa sạch phiên.
- **Chống dò mật khẩu:** sai 5 lần / tài khoản (hoặc 20 lần / IP) trong 15 phút → khóa tạm 15 phút.
- **Tài khoản bị khóa** bị đăng xuất ngay ở lần tải trang kế tiếp.
- **Đọc tham số** bằng `get_str()`, `post_str()`, `get_arr()`, `post_arr()`: URL/form bị sửa tay không làm trang lỗi.
- SQL luôn dùng tham số `?`; HTML luôn qua `e()`; ảnh tải lên kiểm tra đuôi + nội dung, thư mục `uploads/` không chạy PHP.

## Đưa lên hosting
1. `APP_DEBUG` = `false`; sửa `DB_*` theo thông tin hosting.
2. Xóa 3 dòng đầu `DROP DATABASE / CREATE DATABASE / USE` trong `techstore.sql` rồi import vào database hosting đã tạo.
3. Bật HTTPS (hosting thường có SSL miễn phí). Webhook PayOS: `https://ten-mien/payos-webhook.php`.
4. Tạo file `.env` trên hosting (chép từ `.env.example`); không đưa `.env` có khóa thật lên GitHub công khai.

## Khuôn mẫu một trang
```php
<?php
require 'config.php';          // trong admin/: require '../config.php';
require_login();               // trang admin: require_admin();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Lấy $_POST  2. Kiểm tra -> $errors  3. Hợp lệ: db_run(...); set_flash(...); redirect(...);
}
$rows = db_all("SELECT ... WHERE id = ?", [$id]);

$title = 'Tiêu đề';
require '../layout/header.php'; // admin: $breadcrumb = '...'; require 'admin/layout/admin_header.php';
?>
<!-- HTML -->
<?php require '../layout/footer.php'; ?>
```

## Quy tắc
- In dữ liệu ra HTML luôn qua `e()` → chống XSS.
- Truy vấn luôn dùng dấu `?` với `db_*()` → chống SQL injection.
- Mật khẩu: `password_hash()` / `password_verify()`.
- Thao tác nhiều bảng cùng lúc (đặt hàng, hủy đơn) dùng transaction.
- Đơn hàng lưu lại tên và giá sản phẩm tại thời điểm mua (`order_items`).
