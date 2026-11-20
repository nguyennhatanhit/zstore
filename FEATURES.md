# Bảng đối chiếu chức năng – ATS E-Store

✅ Hoàn thành · 🔑 Đã code xong, chỉ cần điền khóa API

## Chức năng người dùng (17/17)

| # | Chức năng | File | Bảng CSDL | Trạng thái |
|---|---|---|---|---|
| 1 | Đăng ký tài khoản | `register.php` | users | ✅ |
| 2 | Đăng nhập, đăng xuất, quên mật khẩu | `login.php`, `logout.php`, `forgot_password.php` | users | ✅ |
| 3 | Quản lý thông tin tài khoản | `account.php`, `change_password.php` | users | ✅ |
| 4 | Xem danh sách và phân loại sản phẩm | `products.php?category=` | products, categories | ✅ |
| 5 | Tìm kiếm sản phẩm theo từ khóa | `products.php?q=` | products | ✅ |
| 6 | Lọc, sắp xếp theo giá, thương hiệu, thông số | `products.php` | products, brands, product_specs, spec_definitions | ✅ |
| 7 | Xem chi tiết sản phẩm và thông số | `product.php?id=` | products, product_images, product_specs | ✅ |
| 8 | Xem tình trạng tồn kho | `product.php`, thẻ sản phẩm | products.stock_quantity | ✅ |
| 9 | Thêm, xóa sản phẩm so sánh | `compare.php` | $_SESSION['compare'], product_specs | ✅ |
| 10 | Thêm, xóa sản phẩm khỏi giỏ hàng | `cart.php` | $_SESSION['cart'] | ✅ |
| 11 | Thay đổi số lượng sản phẩm | `cart.php` | $_SESSION['cart'] | ✅ |
| 12 | Tính tổng giá trị giỏ hàng | `cart.php` (`cart_totals()`) | products, promotions | ✅ |
| 13 | Kiểm tra tương thích linh kiện | `build-pc.php` + `backend/modules/compatibility.php` | compatibility_rules, product_specs | ✅ |
| 14 | Khởi tạo đơn hàng | `checkout.php` | orders, order_items, inventory_logs | ✅ |
| 15 | Tạo thông tin thanh toán | `checkout.php`, `order.php` | payments | ✅ |
| 16 | Theo dõi trạng thái đơn hàng | `order.php?code=` | orders, order_status_history | ✅ |
| 17 | Tra cứu lịch sử đơn hàng | `orders.php` | orders, order_items | ✅ |

## Chức năng quản trị viên (13/13)

| # | Chức năng | File | Bảng CSDL | Trạng thái |
|---|---|---|---|---|
| 1 | Thêm, sửa, xóa sản phẩm | `admin/products.php`, `admin/product_form.php` | products | ✅ |
| 2 | Thêm, sửa, xóa danh mục sản phẩm | `admin/categories.php` | categories, brands | ✅ |
| 3 | Quản lý thông tin và thông số kỹ thuật | `admin/product_form.php` | product_specs, spec_definitions | ✅ |
| 4 | Quản lý hình ảnh và mô tả sản phẩm | `admin/product_form.php` | product_images, products.description | ✅ |
| 5 | Quản lý kho hàng | `admin/inventory.php` | products, inventory_logs | ✅ |
| 6 | Thêm, sửa, xóa khuyến mãi | `admin/promotions.php` | promotions, promotion_products | ✅ |
| 7 | Tiếp nhận và xác nhận đơn hàng | `admin/orders.php` | orders, order_status_history | ✅ |
| 8 | Cập nhật trạng thái đơn hàng | `admin/order_detail.php` | orders, order_status_history | ✅ |
| 9 | Quản lý thông tin thanh toán | `admin/order_detail.php` | payments | ✅ |
| 10 | Hủy và xử lý đơn hàng | `admin/order_detail.php` | orders, products, inventory_logs, payments | ✅ |
| 11 | Xem và quản lý tài khoản khách hàng | `admin/customers.php` | users | ✅ |
| 12 | Theo dõi lịch sử mua hàng của khách | `admin/customer_detail.php` | orders, order_items | ✅ |
| 13 | Thống kê doanh thu, đơn hàng, tồn kho | `admin/index.php` | orders, order_items, products, users | ✅ |

## Tích hợp AI (2/2)

| # | Chức năng | File | Ghi chú | Trạng thái |
|---|---|---|---|---|
| 1 | AI tư vấn sản phẩm | `ai.php` + `backend/modules/ai.php` | Gửi câu hỏi + danh sách sản phẩm thật cho AI, đổi [#ID] thành link | 🔑 |
| 2 | Kiểm tra và tối ưu cấu hình PC | `build-pc.php` + `backend/modules/ai.php` | AI dựa trên kết quả `check_compatibility()` + ngân sách + nhu cầu | 🔑 |

**Bật AI:** mở `config.php`, điền `AI_API_KEY`. Mặc định dùng Gemini (`AI_PROVIDER = 'gemini'`, `AI_MODEL = 'gemini-2.0-flash'`);
dùng OpenAI thì đổi `AI_PROVIDER = 'openai'`, `AI_MODEL = 'gpt-4o-mini'`. Dùng API khác: chỉ sửa hàm `ai_ask()` trong `backend/modules/ai.php`.

## Luồng nghiệp vụ chính

```
Đơn hàng:   pending → confirmed → shipping → completed
               └──────────┴──→ cancelled  (hoàn kho; đã thanh toán → refunded)
Thanh toán: unpaid → paid (COD tự paid khi hoàn thành) → refunded
Kho:        mọi thay đổi stock_quantity đều ghi inventory_logs (import/export/adjust/order/cancel)
```

## Bổ sung để chạy thực tế
| Hạng mục | File |
|---|---|
| Quản lý thông số kỹ thuật theo danh mục (form sản phẩm, bộ lọc, Build PC) | `admin/specs.php` |
| Chống giả mạo form (CSRF), cookie phiên an toàn, đọc tham số an toàn | `backend/core/security.php`, `config.php` |
| Chống dò mật khẩu, giới hạn số câu hỏi AI | `frontend/pages/login.php`, `backend/modules/ai.php`, bảng `rate_limits` |
| Tự hủy đơn QR / chuyển khoản quá hạn, chặn giao đơn trả trước chưa thanh toán | `backend/modules/order.php` |
| Kiểm tra lại tổng tiền trước khi tạo đơn, mã giảm giá không vượt giới hạn | `frontend/pages/checkout.php` |
| Xác nhận thanh toán PayOS khi khách quay về | `payos-return.php` |

