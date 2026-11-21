# Hướng dẫn từng bước: Thêm AJAX vào ATS E-Store

Tài liệu này kể lại **đúng thứ tự** mình đã thêm AJAX vào dự án, mỗi bước ghi rõ **file nào, thêm gì, xóa gì, sửa gì, vì sao**, và **cách tự kiểm tra**. Đọc lần lượt từ Phần 0 → Bước 11 là làm lại được từ đầu.

**Cách đọc các khối code thay đổi**

```diff
- dòng có dấu trừ  = dòng CŨ đã bị XÓA (hoặc bị thay)
+ dòng có dấu cộng = dòng MỚI được THÊM
  dòng không dấu   = giữ nguyên, chỉ để bạn biết vị trí
```

Nhãn trong bảng: **[MỚI]** = tạo file mới · **[SỬA]** = sửa file có sẵn · **[XÓA → CHUYỂN]** = cắt một đoạn ra file khác (code không mất, chỉ đổi chỗ).

---

## Mục lục

- [Phần 0. Hiểu AJAX trước khi làm](#phần-0-hiểu-ajax-trước-khi-làm)
- [Phần 1. Tổng quan: những file đã thay đổi](#phần-1-tổng-quan-những-file-đã-thay-đổi)
- [Bước 1. Hai hàm dùng chung: `json_response()` và `csrf_valid()`](#bước-1-hai-hàm-dùng-chung)
- [Bước 2. Tạo "cửa nhận" cho giỏ hàng: `cart-api.php`](#bước-2-tạo-cửa-nhận-cho-giỏ-hàng-cart-apiphp)
- [Bước 3. Đánh dấu HTML để JavaScript tìm thấy](#bước-3-đánh-dấu-html-để-javascript-tìm-thấy)
- [Bước 4. JavaScript cho nút "Thêm vào giỏ"](#bước-4-javascript-cho-nút-thêm-vào-giỏ)
- [Bước 5. Trang giỏ hàng: đổi số lượng, xóa sản phẩm](#bước-5-trang-giỏ-hàng-đổi-số-lượng-xóa-sản-phẩm)
- [Bước 6. Mã giảm giá](#bước-6-mã-giảm-giá)
- [Bước 7. Khung chat AI nổi (cách "thêm nhánh ajax=1")](#bước-7-khung-chat-ai-nổi)
- [Bước 8. Tìm kiếm gợi ý khi gõ](#bước-8-tìm-kiếm-gợi-ý-khi-gõ)
- [Bước 9. Build PC chọn linh kiện không tải lại](#bước-9-build-pc-chọn-linh-kiện-không-tải-lại)
- [Bước 10. Thêm / bỏ so sánh](#bước-10-thêm--bỏ-so-sánh)
- [Bước 11. Xem nhanh sản phẩm](#bước-11-xem-nhanh-sản-phẩm)
- [Phần 2. Kiểm thử tổng hợp](#phần-2-kiểm-thử-tổng-hợp)
- [Phần 3. Gỡ lỗi](#phần-3-gỡ-lỗi)
- [Phần 4. Tự luyện](#phần-4-tự-luyện)

---

# Phần 0. Hiểu AJAX trước khi làm

## 0.1. Form thường và AJAX khác nhau ở đâu?

```
FORM THƯỜNG (trước đây)                         AJAX (bây giờ)
──────────────────────────                      ──────────────────────────────
Bấm nút → trình duyệt gửi form                  Bấm nút → JavaScript chặn lại
   ↓                                               ↓
cart.php xử lý → set_flash() → redirect()       JS gửi ngầm bằng fetch() tới cart-api.php
   ↓                                               ↓
Trình duyệt TẢI LẠI CẢ TRANG                    cart-api.php xử lý → trả về JSON (vài dòng chữ)
(header, menu, ảnh… tải lại hết)                   ↓
                                                JS đọc JSON → chỉ sửa đúng chỗ cần đổi
                                                (số trên giỏ, dòng thông báo)
                                                → TRANG KHÔNG TẢI LẠI
```

Điểm quan trọng: **logic xử lý không đổi** (vẫn gọi `cart_add()`, `cart_update()`… như cũ). AJAX chỉ đổi **cách gửi** và **cách trả lời**.

## 0.2. Bốn mảnh ghép của mọi chức năng AJAX trong dự án

| # | Mảnh ghép | Nằm ở | Dòng code tiêu biểu |
|---|---|---|---|
| 1 | Chặn trình duyệt tự gửi form | JavaScript | `ev.preventDefault();` |
| 2 | Gửi ngầm dữ liệu lên server | JavaScript | `fetch(url, { method: 'POST', body: new FormData(form) })` |
| 3 | Server trả JSON thay vì chuyển trang | PHP | `json_response(['ok' => true, 'message' => '...']);` |
| 4 | Đọc JSON, sửa đúng phần trên trang | JavaScript | `badge.textContent = res.cart_count;` |

Bước 1–11 bên dưới đều là 4 mảnh này, chỉ khác dữ liệu.

## 0.3. Mấy từ sẽ gặp

| Từ | Nghĩa ngắn gọn |
|---|---|
| `fetch()` | Hàm của JavaScript để gửi yêu cầu tới server mà không tải lại trang |
| `FormData` | Gom mọi ô có `name` trong form thành gói dữ liệu, gửi giống form thật |
| JSON | Dạng dữ liệu chữ `{"ok": true, "message": "..."}` – PHP tạo bằng `json_encode`, JS đọc bằng `res.json()` |
| `GET` / `POST` | GET để **đọc** (tìm kiếm, xem nhanh) – không cần token. POST để **thay đổi** (giỏ hàng, so sánh) – bắt buộc token CSRF |
| Token CSRF (`_csrf`) | Mã bí mật trong mỗi form, chứng minh yêu cầu đến từ chính trang của mình. Dự án đã có `csrf_inject()` tự chèn vào mọi form POST, nên `FormData(form)` gửi kèm luôn |
| Partial | Một file PHP nhỏ chứa **một phần** giao diện (VD: phần tổng tiền), trang chính và file AJAX cùng `require` nó |
| Ủy quyền sự kiện | Gắn sự kiện lên khung cha thay vì từng phần tử con, để phần tử tạo mới sau này vẫn hoạt động (giải thích ở Bước 9) |
| Debounce | Đợi người dùng ngừng gõ một chút rồi mới gửi (Bước 5, Bước 8) |

---

# Phần 1. Tổng quan: những file đã thay đổi

| File | Loại | Dùng ở bước |
|---|---|---|
| `backend/core/helpers.php` | [SỬA] thêm `json_response()` | 1 |
| `backend/core/security.php` | [SỬA] thêm `csrf_valid()` | 1 |
| `frontend/pages/cart-api.php` | [MỚI] | 2, 5, 6 |
| `frontend/layout/header.php` | [SỬA] | 3, 8, 10 |
| `frontend/layout/product_card.php` | [SỬA] | 3, 11 |
| `frontend/pages/product.php` | [SỬA] | 3, 10 |
| `frontend/pages/compare.php` | [SỬA] | 3, 10 |
| `frontend/layout/cart_summary.php` | [MỚI] – cắt từ `cart.php` | 5 |
| `frontend/layout/cart_coupon.php` | [MỚI] – cắt từ `cart.php` | 6 |
| `frontend/pages/cart.php` | [SỬA] + [XÓA → CHUYỂN] | 5, 6 |
| `frontend/pages/ai.php` | [SỬA] | 7 |
| `frontend/pages/search-api.php` | [MỚI] | 8 |
| `backend/modules/compatibility.php` | [SỬA] thêm `build_view()` | 9 |
| `frontend/layout/build_rows.php`, `build_check.php` | [MỚI] – cắt từ `build-pc.php` | 9 |
| `frontend/pages/build-pc.php` | [SỬA] + [XÓA → CHUYỂN] | 9 |
| `frontend/pages/product-api.php` | [MỚI] | 11 |
| `frontend/layout/quick_view.php` | [MỚI] | 11 |
| `frontend/layout/footer.php` | [SỬA] | 11 |
| `frontend/assets/js/main.js` | [SỬA] thêm các phần JS ở cuối file | 4–11 |
| `frontend/assets/css/style.css` | [SỬA] thêm kiểu ở cuối file | 4–11 |

**Không đổi:** CSDL, các hàm nghiệp vụ (`cart_add`, `compare_add`, `check_compatibility`, `find_coupon`…), giao diện các trang (đã chụp so sánh trước/sau, trùng khớp từng điểm ảnh).

---

# Bước 1. Hai hàm dùng chung

**Mục tiêu:** có sẵn công cụ để mọi file AJAX dùng chung: một hàm **trả JSON**, một hàm **kiểm tra token** mà không chuyển trang.

### 1.1. `backend/core/helpers.php` – [SỬA] thêm hàm `json_response()`

```diff
+/** Trả JSON cho AJAX rồi dừng */
+function json_response($data, $status = 200)
+{
+    while (ob_get_level() > 0) {
+        ob_end_clean(); // bỏ bộ đệm csrf_inject: JSON phải được gửi nguyên vẹn
+    }
+    http_response_code($status);
+    header('Content-Type: application/json; charset=utf-8');
+    echo json_encode($data, JSON_UNESCAPED_UNICODE);
+    exit;
+}
```

Giải thích từng dòng:
- `ob_end_clean()` – `config.php` có `ob_start('csrf_inject')`: mọi thứ PHP in ra đều đi qua bộ lọc chèn token vào form. Nếu JSON chứa HTML có `<form method="post">` thì bộ lọc sẽ chèn chuỗi có dấu `"` vào giữa JSON và làm hỏng JSON. Nên trước khi in JSON, tắt bộ lọc đi.
- `http_response_code($status)` – mã trạng thái: **200** thành công, **403** sai token, **404** không tìm thấy, **405** sai phương thức, **422** dữ liệu không hợp lệ.
- `header('Content-Type: application/json…')` – báo cho trình duyệt đây là JSON.
- `JSON_UNESCAPED_UNICODE` – giữ tiếng Việt có dấu (không thành `ạ`).
- `exit` – dừng ngay, không in thêm gì sau JSON.

### 1.2. `backend/core/security.php` – [SỬA] thêm hàm `csrf_valid()`

```diff
+/** Token gửi lên có đúng không (dùng cho trang trả JSON) */
+function csrf_valid()
+{
+    $sent = isset($_POST['_csrf']) && is_string($_POST['_csrf']) ? $_POST['_csrf'] : '';
+    return $sent !== '' && hash_equals(csrf_token(), $sent);
+}
```

**Vì sao không dùng `csrf_verify()` có sẵn?** Vì `csrf_verify()` khi sai token sẽ `set_flash()` rồi **chuyển trang** – JavaScript đang chờ JSON sẽ nhận về cả trang HTML và báo lỗi. `csrf_valid()` chỉ trả `true/false`, để file AJAX tự trả JSON 403.

✅ **Ghi nhớ:** mọi file AJAX kết thúc bằng `json_response(...)`, không bao giờ bằng `redirect(...)`.

---

# Bước 2. Tạo "cửa nhận" cho giỏ hàng: `cart-api.php`

**Mục tiêu:** một file PHP nhận yêu cầu từ JavaScript, gọi lại đúng các hàm giỏ hàng cũ, trả JSON.

### `frontend/pages/cart-api.php` – [MỚI]

```php
<?php
define('SKIP_CSRF', true);            // (1)
require '../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {                                   // (2)
    json_response(['ok' => false, 'message' => 'Chỉ nhận POST'], 405);
}
if (!csrf_valid()) {                                                           // (3)
    json_response(['ok' => false, 'message' => 'Phiên làm việc đã hết hạn, hãy tải lại trang.'], 403);
}

$action = post_str('action', 'add');                                           // (4)
$id     = (int) post_str('product_id', '0');
$error  = 'Thao tác không hợp lệ.';

if ($action === 'add') {
    $error = cart_add($id, post_str('quantity', '1'));                         // (5)
} elseif ($action === 'update') {
    $error = cart_update($id, post_str('quantity', '0'));
} elseif ($action === 'remove') {
    cart_remove($id);
    $error = null;
}
// (nhánh apply_coupon / remove_coupon thêm ở Bước 6)

$cart = cart_totals();                                                         // (6)
// ... tìm thành tiền của dòng vừa đổi, vẽ lại phần tổng tiền (Bước 5)

json_response([                                                                // (7)
    'ok'         => $error === null,
    'message'    => $error ?: 'Đã thêm sản phẩm vào giỏ hàng.',
    'cart_count' => cart_count(),
    // ... các khóa cho trang giỏ (Bước 5)
], $error === null ? 200 : 422);
```

1. `SKIP_CSRF` – báo `config.php` **đừng** tự kiểm tra token (vì nó sẽ chuyển trang khi sai); file này tự kiểm tra ở (3).
2. Chỉ nhận POST vì thao tác này **thay đổi** giỏ hàng.
3. Sai/thiếu token → trả 403.
4. Đọc dữ liệu bằng `post_str()` như các trang khác.
5. **Gọi lại đúng hàm cũ** trong `backend/modules/cart.php` – không viết lại logic giỏ hàng.
6. Tính lại giỏ để trả số liệu mới.
7. Trả JSON. `cart_count` là số **lấy từ server** – JS không tự cộng 1, nên luôn đúng.

**So với `cart.php` cũ:**

| | `cart.php` (giữ nguyên, dùng khi tắt JS) | `cart-api.php` (mới) |
|---|---|---|
| Kiểm tra token | `csrf_verify()` trong config | `csrf_valid()` |
| Logic | `cart_add()`… | `cart_add()`… **giống hệt** |
| Báo kết quả | `set_flash()` | `"message"` trong JSON |
| Kết thúc | `redirect()` | `json_response()` |

✅ **Tự kiểm tra:** chưa có JS nên chưa bấm được, nhưng mở thẳng `http://localhost/…/cart-api.php` trên trình duyệt (là GET) phải thấy `{"ok":false,"message":"Chỉ nhận POST"}`.

---

# Bước 3. Đánh dấu HTML để JavaScript tìm thấy

**Mục tiêu:** JavaScript cần biết **form nào** cần chặn, **số nào** cần cập nhật, **gửi tới đâu**. Ta gắn "nhãn" (class, id, data-…) vào HTML. **Không đổi giao diện.**

### 3.1. `frontend/layout/header.php` – [SỬA]

```diff
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
+    <meta name="cart-api" content="<?= url('cart-api.php') ?>">
```
→ Ghi địa chỉ `cart-api.php` vào thẻ meta để JS đọc (đường dẫn do PHP tính đúng cả trên XAMPP lẫn hosting).

```diff
             <a href="<?= url('cart.php') ?>" class="header-link">
                 <i class="bi bi-cart3"></i><span>Giỏ hàng</span>
-                <?php if (cart_count()): ?><b class="count"><?= cart_count() ?></b><?php endif; ?>
+                <b class="count" id="cart-count" <?= cart_count() ? '' : 'hidden' ?>><?= cart_count() ?></b>
             </a>
```
→ **Vì sao phải xóa `if`?** Code cũ không in thẻ `<b>` khi giỏ trống. Khi thêm sản phẩm đầu tiên bằng AJAX, JS không có chỗ để ghi số. Giờ thẻ **luôn có**, giỏ trống thì chỉ ẩn bằng `hidden`. Thêm `id="cart-count"` để JS tìm.

### 3.2. Form "Thêm vào giỏ" – [SỬA] thêm `class="add-to-cart"` ở 3 nơi

`frontend/layout/product_card.php`:
```diff
-            <form action="<?= url('cart.php') ?>" method="post">
+            <form action="<?= url('cart.php') ?>" method="post" class="add-to-cart">
```
`frontend/pages/product.php`:
```diff
-                <form action="<?= url('cart.php') ?>" method="post" class="pd-buy">
+                <form action="<?= url('cart.php') ?>" method="post" class="pd-buy add-to-cart">
```
`frontend/pages/compare.php`:
```diff
-                            <form action="<?= url('cart.php') ?>" method="post">
+                            <form action="<?= url('cart.php') ?>" method="post" class="add-to-cart">
```

Form vẫn có `action="cart.php"` → **tắt JavaScript vẫn chạy như cũ**. JS chỉ "cướp" lần gửi khi nó hoạt động.

---

# Bước 4. JavaScript cho nút "Thêm vào giỏ"

**File:** `frontend/assets/js/main.js` – [SỬA] thêm phần "Giỏ hàng AJAX" ở cuối file.

```js
var cartApi = document.querySelector('meta[name="cart-api"]');   // (1)
if (cartApi) {
    var cartUrl = cartApi.content;

    // (2) Hiện thông báo nhỏ góc trên phải, tự ẩn sau 3,5 giây
    var toast = function (type, message, link, linkText) {
        var stack = document.querySelector('.toast-stack');
        if (!stack) { stack = document.createElement('div'); stack.className = 'toast-stack'; document.body.appendChild(stack); }
        var el = document.createElement('div');
        el.className = 'alert alert-' + type;
        el.innerHTML = '<i class="bi …"></i><span></span>';
        el.querySelector('span').textContent = message;     // textContent: chỉ gán chữ, không chèn HTML
        if (link) { var a = document.createElement('a'); a.href = link; a.textContent = linkText || 'Xem giỏ'; el.appendChild(a); }
        stack.appendChild(el);
        setTimeout(function () { el.remove(); }, 3500);
    };

    // (3) Cập nhật số trên biểu tượng giỏ
    var setBadge = function (n) {
        var badge = document.getElementById('cart-count');
        badge.textContent = n;
        badge.hidden = n <= 0;
    };

    // (4) Gửi tới cart-api.php, đọc JSON
    var cartSend = function (data) {
        return fetch(cartUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    };

    // (5) Bắt sự kiện gửi form "Thêm vào giỏ"
    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!form.matches('form.add-to-cart')) return;                    // không phải form giỏ → bỏ qua
        if (ev.submitter && ev.submitter.name === 'buy_now') return;      // "Mua ngay" → để form chạy như cũ
        ev.preventDefault();                                              // CHẶN tải lại trang
        var btn = ev.submitter || form.querySelector('button');
        if (btn.disabled) return;
        btn.disabled = true;                                              // chống bấm 2 lần
        cartSend(new FormData(form)).then(function (res) {                // FormData gồm action, product_id, _csrf
            setBadge(res.cart_count);
            toast(res.ok ? 'success' : 'warning', res.message, res.ok ? form.getAttribute('action') : null);
        }).catch(function () {
            form.submit();                                                // lỗi mạng → gửi form kiểu cũ
        }).then(function () { btn.disabled = false; });                   // luôn mở lại nút
    });
}
```

1. Chỉ chạy khi trang có thẻ meta (trang khách) – trang admin không có thì bỏ qua.
2. `toast()` – dùng lại class `.alert` có sẵn nên đúng màu theme. Dùng `textContent` để chống chèn mã.
3. `hidden = n <= 0` – giỏ trống thì ẩn số.
4. `credentials: 'same-origin'` – gửi kèm cookie phiên, server mới biết giỏ của ai.
5. Gắn trên `document` (ủy quyền sự kiện) – lúc đầu mình gắn từng form bằng `querySelectorAll(...).forEach`, đến Bước 11 mới đổi sang cách này (xem lý do ở Bước 11).

**CSS** (`style.css`, [SỬA] thêm cuối file): `.toast-stack` (vị trí góc phải, `z-index: 1200`), `.header-link .count[hidden] { display: none; }`, hiệu ứng `.is-bump` khi số đổi.

✅ **Tự kiểm tra:** mở trang sản phẩm → **F12 → tab Network** → bấm "Thêm vào giỏ" → thấy dòng `cart-api.php` mã **200**, tab **Response** là JSON; trang không tải lại, số trên giỏ tăng.

---

# Bước 5. Trang giỏ hàng: đổi số lượng, xóa sản phẩm

**Mục tiêu:** sửa số lượng hay bấm thùng rác thì thành tiền, tổng tiền đổi ngay.

**Vấn đề:** phần "Tóm tắt đơn hàng" (tạm tính, giảm giá, phí ship, tổng) có nhiều `if`. Nếu bắt JS tự tính và vẽ lại thì phải viết logic **hai lần** (PHP và JS) – dễ sai lệch. **Cách giải:** cắt phần đó ra một **partial**, để PHP vẽ lại rồi gửi HTML về trong JSON.

### 5.1. [XÓA → CHUYỂN] Cắt phần tổng tiền khỏi `cart.php` sang file mới

`frontend/pages/cart.php`:
```diff
-                <div class="summary-row"><span>Tạm tính</span><b><?= money($cart['subtotal']) ?></b></div>
-                <?php if ($cart['saved'] > 0): ?>
-                    <div class="summary-row text-success"><span>Đã tiết kiệm (khuyến mãi)</span>…</div>
-                <?php endif; ?>
-                <?php if ($cart['discount'] > 0): ?>
-                    <div class="summary-row text-success"><span>Mã giảm giá</span>…</div>
-                <?php endif; ?>
-                <div class="summary-row"><span>Phí giao hàng</span>…</div>
-                <?php if ($cart['shipping']): ?>
-                    <p class="form-hint">Mua thêm … để được miễn phí giao hàng.</p>
-                <?php endif; ?>
-                <div class="summary-row summary-total"><span>Tổng cộng</span><b><?= money($cart['total']) ?></b></div>
+                <div class="summary-rows" id="cart-summary">
+                    <?php require '../layout/cart_summary.php'; ?>
+                </div>
```

`frontend/layout/cart_summary.php` – [MỚI]: chứa **nguyên văn** 12 dòng vừa xóa ở trên.

`.summary-rows { display: contents; }` (CSS) – khung bọc "trong suốt" với bố cục, nên giao diện không đổi.

### 5.2. [SỬA] Gắn nhãn cho từng dòng sản phẩm trong `cart.php`

```diff
-        <p class="text-muted"><?= $cart['count'] ?> sản phẩm</p>
+        <p class="text-muted"><span data-cart-count-text><?= $cart['count'] ?></span> sản phẩm</p>
 …
-                                <tr>
+                                <tr data-cart-row="<?= $item['id'] ?>">
 …
-  … class="input qty-input"></td>
-                                    <td><b><?= money($item['line_total']) ?></b></td>
+  … class="input qty-input" data-cart-qty="<?= $item['id'] ?>"></td>
+                                    <td><b data-line-total><?= money($item['line_total']) ?></b></td>
 …
-                    <form method="post" id="remove-<?= $item['id'] ?>" class="hidden">
+                    <form method="post" id="remove-<?= $item['id'] ?>" class="hidden cart-remove">
```

### 5.3. [SỬA] `cart-api.php` trả thêm dữ liệu cho trang giỏ

```php
$cart = cart_totals();
$line = 0;
foreach ($cart['items'] as $item) {                 // thành tiền của dòng vừa đổi
    if ($item['id'] == $id) $line = $item['line_total'];
}
ob_start();                                          // bắt đầu "hứng" HTML thay vì in ra
require __DIR__ . '/../layout/cart_summary.php';     // CÙNG file mà cart.php dùng
$summary = ob_get_clean();                           // lấy HTML vừa hứng vào biến

json_response([
    'ok'           => $error === null,
    'message'      => …,
    'cart_count'   => cart_count(),
    'item_count'   => $cart['count'],                // số ở tiêu đề "x sản phẩm"
    'quantity'     => $_SESSION['cart'][$id] ?? 0,   // số lượng THẬT (đã giới hạn theo tồn kho)
    'line_total'   => money($line),
    'summary_html' => $summary,
], …);
```

`ob_start()` + `ob_get_clean()` là mẹo để **lấy HTML của một file PHP vào biến** thay vì in thẳng ra trang.

### 5.4. [SỬA] JavaScript cho trang giỏ (`main.js`, trong khối giỏ hàng)

```js
var summary = document.getElementById('cart-summary');
if (summary) {                                                   // chỉ chạy ở trang giỏ
    var token = document.querySelector('#cart-form input[name="_csrf"]').value;

    var update = function (id, action, qty) {                    // tự tạo gói dữ liệu
        var data = new FormData();
        data.append('_csrf', token);
        data.append('action', action);
        data.append('product_id', id);
        if (qty !== undefined) data.append('quantity', qty);
        cartSend(data).then(function (res) { applyCart(id, res); });
    };

    var applyCart = function (id, res) {
        var row = document.querySelector('[data-cart-row="' + id + '"]');
        setBadge(res.cart_count);
        if (res.item_count === 0) { location.reload(); return; } // hết hàng trong giỏ → tải lại để hiện "Giỏ trống"
        if (res.quantity === 0) row.remove();
        else {
            row.querySelector('[data-cart-qty]').value = res.quantity;
            row.querySelector('[data-line-total]').textContent = res.line_total;
        }
        summary.innerHTML = res.summary_html;                    // thay phần tổng tiền
    };

    // Ô số lượng: đợi ngừng gõ 0,5 giây rồi mới gửi (debounce)
    var timers = {};
    document.querySelectorAll('[data-cart-qty]').forEach(function (input) {
        var id = input.getAttribute('data-cart-qty');
        input.addEventListener('input', function () {
            clearTimeout(timers[id]);
            timers[id] = setTimeout(function () { update(id, 'update', input.value); }, 500);
        });
    });

    // Nút thùng rác
    document.querySelectorAll('form.cart-remove').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            update(form.querySelector('[name="product_id"]').value, 'remove');
        });
    });
}
```

**Debounce là gì?** Gõ "12" là 2 lần phím. Không có debounce → gửi 2 yêu cầu ("1" rồi "12"). Có debounce → mỗi lần gõ thì hủy hẹn giờ cũ, chỉ gửi khi đã ngừng 0,5 giây.

✅ **Tự kiểm tra:** sửa số lượng thành 999 → số tự giảm về tồn kho, có thông báo vàng; bấm thùng rác → dòng biến mất.

---

# Bước 6. Mã giảm giá

**Mục tiêu:** áp mã / bỏ mã không tải lại. Ô mã có 2 trạng thái (form nhập mã ⇄ thẻ "mã đã áp dụng") nên cũng cắt ra partial.

### 6.1. [XÓA → CHUYỂN] `cart.php` → `frontend/layout/cart_coupon.php` [MỚI]

```diff
-                <?php if ($cart['coupon']): ?>
-                    <form method="post" class="coupon-applied"> … nút bỏ mã … </form>
-                <?php else: ?>
-                    <form method="post" class="coupon-form"> … ô nhập mã + nút Áp dụng … </form>
-                <?php endif; ?>
+                <div class="summary-rows" id="cart-coupon">
+                    <?php require '../layout/cart_coupon.php'; ?>
+                </div>
```

### 6.2. [SỬA] `cart-api.php` thêm 2 nhánh – dùng lại đúng `find_coupon()` như `cart.php`

```diff
 } elseif ($action === 'remove') {
     cart_remove($id);
     $error = null;
+} elseif ($action === 'apply_coupon') {
+    $code = strtoupper(post_str('coupon'));
+    list($promo, $error) = find_coupon($code, cart_details());
+    if (!$error) {
+        $_SESSION['coupon'] = $code;
+    }
+} elseif ($action === 'remove_coupon') {
+    unset($_SESSION['coupon']);
+    $error = null;
 }
```
và vẽ lại ô mã:
```php
ob_start();
require __DIR__ . '/../layout/cart_coupon.php';
$couponHtml = csrf_inject(ob_get_clean());   // ô mã có FORM → phải chèn token cho lần gửi sau
```
`'coupon_html' => $couponHtml` được thêm vào JSON.

> Vì sao `cart_summary.php` không cần `csrf_inject` còn `cart_coupon.php` thì cần? Vì phần tổng tiền **không có form**, còn ô mã **có form POST**.

### 6.3. [SỬA] JavaScript

```js
var couponBox = document.getElementById('cart-coupon');
couponBox.addEventListener('submit', function (ev) {      // gắn trên KHUNG, bắt cả 2 form bên trong
    ev.preventDefault();
    var form = ev.target;
    cartSend(new FormData(form)).then(function (res) {
        if (res.ok) {
            couponBox.innerHTML = res.coupon_html;          // form nhập ⇄ thẻ mã
            summary.innerHTML = res.summary_html;           // dòng "Mã giảm giá" + tổng cộng
        }
        toast(res.ok ? 'success' : 'error', res.message);
    }).catch(function () { form.submit(); });
});
```

✅ **Tự kiểm tra:** nhập mã sai → 422, thông báo đỏ; nhập `TECH500K` (giỏ ≥ 10 triệu) → giảm 500.000 ₫; bấm ✕ → bỏ mã.

---

# Bước 7. Khung chat AI nổi

Khung chat AI (nút tròn góc phải) đã có từ trước. Nó dùng cách thứ hai để làm AJAX: **không tạo file mới, mà thêm một nhánh vào trang cũ**.

### Hai cách làm "cửa nhận" AJAX trong dự án

| Cách | Khi nào dùng | Ví dụ |
|---|---|---|
| **A. File riêng** `xxx-api.php` | Chức năng được gọi từ nhiều trang | `cart-api.php`, `search-api.php`, `product-api.php` |
| **B. Thêm nhánh `ajax=1`** vào chính trang cũ | Trang đã có sẵn xử lý POST, chỉ cần đổi cách trả lời | `ai.php`, `build-pc.php`, `compare.php` |

Cách B: JS gửi thêm `ajax=1`; trong PHP đặt **ngay trước** `redirect(...)` cũ:
```php
if (post_str('ajax') === '1') {
    json_response([...]);      // gửi bằng JS → nhận JSON
}
redirect('ai.php#chat-end');   // gửi form thường → chuyển trang như cũ
```

### [SỬA] `frontend/pages/ai.php` – cho đồng bộ với các phần khác

```diff
     if (post_str('ajax') === '1') {
-        header('Content-Type: application/json; charset=utf-8');
-        echo json_encode(['html' => $added ? ai_msg_html($added) : '']);
-        exit;
+        json_response(['ok' => true, 'html' => $added ? ai_msg_html($added) : '']);
     }
```
Ba dòng cũ làm đúng việc của `json_response()` nên thay bằng 1 dòng, và có thêm khóa `ok` cho thống nhất.

**Lưu ý đã gặp:** form chat có ô `<input name="action">` nên trong JS, `form.action` trả về **chính ô input đó** chứ không phải địa chỉ. Phải dùng `form.getAttribute('action')`.

---

# Bước 8. Tìm kiếm gợi ý khi gõ

**Mục tiêu:** gõ ≥ 2 ký tự vào ô tìm kiếm → hiện tối đa 6 sản phẩm bên dưới. Nối với Chương 5 (tìm kiếm `LIKE ?`).

### 8.1. `frontend/pages/search-api.php` – [MỚI] (dùng **GET** vì chỉ đọc)

```php
<?php
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
        'price'    => money(sale_price($p)),       // định dạng tiền sẵn ở PHP
        'image'    => img_url($p['image']),
        'url'      => url('product.php?id=' . $p['id']),
        'in_stock' => $p['stock_quantity'] > 0,
    ];
}
json_response(['ok' => true, 'q' => $q, 'total' => $total, 'items' => $items,
               'all_url' => url('products.php?q=' . urlencode($q))]);
```
- Cùng câu điều kiện `LIKE ?` với `products.php`, dùng tham số `?` để chống SQL Injection.
- `ORDER BY p.stock_quantity > 0 DESC` – sản phẩm còn hàng lên trước.
- **Không cần `SKIP_CSRF` / `csrf_valid()`**: `csrf_verify()` trong config chỉ kiểm tra POST, còn đây là GET.

### 8.2. [SỬA] `frontend/layout/header.php`

```diff
-        <form action="<?= url('products.php') ?>" method="get" class="header-search">
+        <form action="<?= url('products.php') ?>" method="get" class="header-search" data-search="<?= url('search-api.php') ?>">
             <i class="bi bi-search"></i>
-            <input type="search" name="q" placeholder="Tìm CPU, laptop, card đồ họa..." value="<?= e(get_str('q')) ?>">
+            <input type="search" name="q" placeholder="Tìm CPU, laptop, card đồ họa..." value="<?= e(get_str('q')) ?>"
+                   autocomplete="off" role="combobox" aria-expanded="false" aria-controls="search-suggest">
+            <div class="search-suggest" id="search-suggest" role="listbox" hidden></div>
         </form>
```
- `data-search` – địa chỉ API cho JS.
- `autocomplete="off"` – tắt gợi ý riêng của trình duyệt (kẻo đè lên gợi ý của web).
- `role`, `aria-*` – cho trình đọc màn hình biết đây là ô có danh sách gợi ý.
- `<div class="search-suggest" hidden>` – khung rỗng, JS đổ kết quả vào.

### 8.3. [SỬA] JavaScript – 4 điểm đáng học

```js
// 1) Debounce 250ms
sInput.addEventListener('input', function () {
    var q = sInput.value.trim();
    clearTimeout(sTimer);
    if (q.length < 2) { sShow(false); return; }
    sTimer = setTimeout(function () {
        fetch(searchForm.getAttribute('data-search') + '?q=' + encodeURIComponent(q))  // GET: dữ liệu nằm trên URL
            .then(function (r) { return r.json(); })
            .then(render);
    }, 250);
});

// 2) Bỏ kết quả cũ: gõ nhanh thì yêu cầu trước có thể về SAU yêu cầu mới
if (res.q !== sInput.value.trim()) return;

// 3) Chống chèn mã (XSS) khi ghép tên sản phẩm vào innerHTML
var esc = function (t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; };

// 4) Phím mũi tên lên/xuống chọn dòng, Enter mở, Esc đóng
sInput.addEventListener('keydown', function (ev) { … });
```

`encodeURIComponent(q)` – đổi dấu cách, chữ có dấu thành dạng an toàn trên URL (`rtx 50` → `rtx%2050`).

**CSS** [SỬA]: `.header-search { position: relative; }` + kiểu khung `.search-suggest`, dòng `.suggest-item`.

✅ **Tự kiểm tra:** gõ "rtx" → Network thấy `search-api.php?q=rtx` (GET, 200); Enter khi chưa chọn dòng nào vẫn sang `products.php?q=rtx` như cũ.

---

# Bước 9. Build PC chọn linh kiện không tải lại

**Mục tiêu:** chọn/bỏ linh kiện → danh sách, kiểm tra tương thích, công suất, tổng tiền đổi ngay.

Đây là bước **sửa nhiều nhất** vì phải tách code. Làm theo 4 bước nhỏ:

### 9.1. [XÓA → CHUYỂN] Đưa phần tính toán vào hàm `build_view()`

Trang `build-pc.php` cũ tự tính 5 biến ngay trong trang. Vì file AJAX cũng cần đúng 5 biến đó, ta gom vào 1 hàm.

`frontend/pages/build-pc.php`:
```diff
-// Danh sách sản phẩm cho từng vị trí (còn hàng)
-$options = [];
-foreach ($slots as $code => $name) {
-    $options[$code] = db_all(product_sql() . " WHERE c.code = ? AND … ORDER BY p.price", [$code]);
-}
-
-// Sản phẩm đã chọn
-$selected = [];
-$total = 0;
-foreach ($build as $code => $productId) {
-    … $selected[$code] = $p; $total += $p['sale_price']; …
-}
-$compat = check_compatibility($build);
+['options' => $options, 'selected' => $selected, 'total' => $total, 'compat' => $compat, 'keySpecs' => $keySpecs] = build_view($build);
 $aiResult = isset($_SESSION['build_ai']) ? $_SESSION['build_ai'] : null;
-
-// Thông số chính hiển thị dưới tên linh kiện
-$keySpecs = ['socket', 'ram_type', 'form_factor', 'capacity', 'tdp', 'wattage', 'vram', 'length', 'cores', 'speed'];
```

`backend/modules/compatibility.php` – [SỬA] thêm hàm chứa **nguyên văn** đoạn vừa xóa:
```php
/** Dữ liệu hiển thị Build PC: sản phẩm từng vị trí, linh kiện đã chọn, tổng tiền, kết quả kiểm tra */
function build_view(array $build)
{
    $slots = build_slots();
    $options = [];  … (y như trên) …
    $selected = []; $total = 0; … (y như trên) …
    $compat = check_compatibility($build);
    $keySpecs = ['socket', 'ram_type', …];
    return ['options' => $options, 'selected' => $selected, 'total' => $total, 'compat' => $compat, 'keySpecs' => $keySpecs];
}
```

Dòng `['options' => $options, …] = build_view($build);` là cú pháp **gán nhiều biến từ mảng có khóa** (PHP 7.1+): sau dòng này các biến `$options`, `$selected`… có giá trị như trước, nên phần HTML bên dưới không phải sửa gì.

### 9.2. [XÓA → CHUYỂN] Cắt 2 khối HTML ra 2 partial

```diff
-        <div class="card no-pad">
-            <?php foreach ($slots as $code => $name): … ?>
-                … (khoảng 35 dòng: mỗi vị trí linh kiện là ô chọn hoặc sản phẩm đã chọn + nút ✕) …
-            <?php endforeach; ?>
-        </div>
+        <div class="card no-pad" id="build-rows">
+            <?php require '../layout/build_rows.php'; ?>
+        </div>
 …
-                <?php if (!$build): ?> … (khoảng 18 dòng: thông báo tương thích, công suất, thanh nguồn) … <?php endif; ?>
-                <div class="summary-row summary-total"><span>Tổng tiền …</span><b>…</b></div>
+                <div class="summary-rows" id="build-check">
+                    <?php require '../layout/build_check.php'; ?>
+                </div>
```
- `frontend/layout/build_rows.php` [MỚI] – nguyên văn khối thứ nhất, **chỉ đổi 1 chỗ** (xem 9.4).
- `frontend/layout/build_check.php` [MỚI] – nguyên văn khối thứ hai.

### 9.3. [SỬA] Thêm nhánh `ajax=1` (cách B) và các `id`

Trong phần xử lý POST của `build-pc.php`, ngay trước `redirect('build-pc.php')`:
```diff
+    if (post_str('ajax') === '1') {
+        $build = isset($_SESSION['build']) ? $_SESSION['build'] : [];
+        ['options' => $options, 'selected' => $selected, 'total' => $total, 'compat' => $compat, 'keySpecs' => $keySpecs] = build_view($build);
+        ob_start();
+        require '../layout/build_rows.php';
+        $rowsHtml = csrf_inject(ob_get_clean());       // mỗi dòng có form ✕ → cần token
+        ob_start();
+        require '../layout/build_check.php';
+        $checkHtml = ob_get_clean();
+        json_response(['ok' => true, 'count' => count($selected), 'rows_html' => $rowsHtml, 'check_html' => $checkHtml]);
+    }
     redirect('build-pc.php');
```

Các chỗ sửa nhỏ để JS điều khiển được:

| Chỗ | Trước | Sau | Lý do |
|---|---|---|---|
| Nút "Làm lại" | bọc trong `<?php if ($build): ?> … <?php endif; ?>` | luôn in ra, có `id="build-clear"` và `hidden` khi chưa chọn gì | chọn linh kiện đầu tiên thì JS hiện nút lên |
| Nút "Thêm cấu hình vào giỏ" | không có id | `id="build-add-all"` | JS bật/tắt `disabled` |
| Kết quả AI | không có khung bọc | `<div id="build-ai-result">` | đổi cấu hình thì kết quả AI cũ không còn đúng → JS xóa đi |

### 9.4. [SỬA] Đổi `data-autosubmit` → `data-build-select` (trong `build_rows.php`)

```diff
-<select name="product_id" class="select" data-autosubmit="nonempty" …>
+<select name="product_id" class="select" data-build-select …>
```
**Vì sao?** `main.js` có sẵn đoạn: ô nào có `data-autosubmit` thì đổi giá trị là `form.submit()` → **tải lại trang**. Phải bỏ nhãn đó để đoạn cũ không chạy, rồi dùng nhãn mới cho JS AJAX.

### 9.5. [SỬA] JavaScript – và khái niệm **ủy quyền sự kiện**

```js
var buildRows = document.getElementById('build-rows');
if (buildRows) {
    var buildCheck = document.getElementById('build-check');
    var buildSend = function (form) {
        var data = new FormData(form);             // action, slot, product_id, _csrf
        data.append('ajax', '1');                  // báo PHP trả JSON
        fetch(location.pathname, { method: 'POST', body: data })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                buildRows.innerHTML = res.rows_html;      // thay TOÀN BỘ các dòng
                buildCheck.innerHTML = res.check_html;
                document.getElementById('build-clear').hidden = res.count === 0;
                document.getElementById('build-add-all').disabled = res.count === 0;
            })
            .catch(function () { form.submit(); });
    };
    buildRows.addEventListener('change', function (ev) {    // gắn trên KHUNG CHA
        var sel = ev.target.closest('[data-build-select]');
        if (sel && sel.value !== '') buildSend(sel.form);
    });
    buildRows.addEventListener('submit', function (ev) {    // nút ✕ bỏ chọn
        ev.preventDefault();
        buildSend(ev.target);
    });
}
```

**Ủy quyền sự kiện là gì, vì sao cần?**
Sau mỗi lần chọn, `buildRows.innerHTML = …` **thay toàn bộ** các ô chọn bằng ô mới. Nếu gắn sự kiện trực tiếp vào từng `<select>` cũ, ô mới sẽ **không có sự kiện** → lần chọn thứ hai không hoạt động. Giải pháp: gắn **một lần** vào khung cha `#build-rows` (không bị thay), sự kiện của ô con "nổi bọt" lên khung cha, rồi dùng `ev.target.closest(...)` để biết ô nào vừa đổi.

```
#build-rows  ← gắn sự kiện ở đây (không bao giờ bị thay)
 ├─ select CPU     ← bị thay mới sau mỗi lần chọn
 ├─ select RAM     ← bị thay mới
 └─ form ✕         ← bị thay mới
```

✅ **Tự kiểm tra:** chọn CPU rồi chọn tiếp RAM (lần chọn thứ hai vẫn chạy = ủy quyền sự kiện đúng); bấm ✕; Network thấy `build-pc.php` POST trả JSON.

---

# Bước 10. Thêm / bỏ so sánh

**Mục tiêu:** ở trang chi tiết, bấm "Thêm vào so sánh" → số ở biểu tượng So sánh đổi, nút đổi chữ, không tải lại.

### 10.1. [SỬA] `frontend/pages/compare.php` – thêm nhánh `ajax=1` (cách B)

```diff
     } elseif ($action === 'clear') {
         unset($_SESSION['compare']);
         set_flash('success', 'Đã xóa danh sách so sánh.');
     }
+    if (post_str('ajax') === '1') {
+        $flash = $_SESSION['flash'];
+        unset($_SESSION['flash']);
+        json_response([
+            'ok'            => $flash['type'] === 'success',
+            'message'       => $flash['message'],
+            'compare_count' => compare_count(),
+            'in_compare'    => in_array($productId, $_SESSION['compare'] ?? []),
+        ], $flash['type'] === 'success' ? 200 : 422);
+    }
     redirect_back('compare.php');
```
**Mẹo:** code cũ đã `set_flash()` sẵn câu thông báo → lấy lại câu đó cho JSON (không viết lại), rồi `unset` để thông báo không hiện thêm lần nữa ở trang sau.

### 10.2. [SỬA] `frontend/pages/product.php` và `header.php`

```diff
-            <form action="<?= url('compare.php') ?>" method="post" class="mt-8">
+            <form action="<?= url('compare.php') ?>" method="post" class="mt-8 compare-toggle">
 …
-                <button class="btn btn-outline"><i class="bi bi-arrow-left-right"></i> <?= $inCompare ? 'Bỏ khỏi so sánh' : 'Thêm vào so sánh' ?></button>
+                <button class="btn btn-outline"><i class="bi bi-arrow-left-right"></i> <span><?= $inCompare ? 'Bỏ khỏi so sánh' : 'Thêm vào so sánh' ?></span></button>
```
→ Bọc chữ trong `<span>` để JS đổi chữ mà không xóa mất biểu tượng `<i>`.

```diff
-                <?php if (compare_count()): ?><b class="count"><?= compare_count() ?></b><?php endif; ?>
+                <b class="count" id="compare-count" <?= compare_count() ? '' : 'hidden' ?>><?= compare_count() ?></b>
```
→ Cùng lý do như số giỏ hàng ở Bước 3.

### 10.3. [SỬA] JavaScript

```js
document.querySelectorAll('form.compare-toggle').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var data = new FormData(form);
        data.append('ajax', '1');
        fetch(form.getAttribute('action'), { method: 'POST', body: data })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                badge.textContent = res.compare_count;
                badge.hidden = res.compare_count === 0;
                // lần bấm sau gửi hành động NGƯỢC lại
                form.querySelector('[name="action"]').value = res.in_compare ? 'remove' : 'add';
                btn.querySelector('span').textContent = res.in_compare ? 'Bỏ khỏi so sánh' : 'Thêm vào so sánh';
                toast(res.ok ? 'success' : 'warning', res.message, …, 'Xem so sánh');
            })
            .catch(function () { form.submit(); });
    });
});
```
Hàm `toast()` ở Bước 4 được thêm tham số thứ 4 `linkText` để link ghi "Xem so sánh" thay cho "Xem giỏ".

✅ **Tự kiểm tra:** bấm 2 lần → thêm rồi bỏ; thêm sản phẩm thứ 5 → 422 "Chỉ so sánh tối đa 4 sản phẩm".

---

# Bước 11. Xem nhanh sản phẩm

Ý tưởng lấy từ bài tập "Card + Xem chi tiết + AJAX": mỗi thẻ sản phẩm có nút **con mắt**, bấm vào hiện khung nổi với thông tin chính và nút "Thêm vào giỏ".

### 11.1. `frontend/pages/product-api.php` – [MỚI] (cách A, GET)

```php
<?php
require '../../config.php';

$id = (int) get_str('id', '0');
$product = db_one(product_sql() . " WHERE p.id = ? AND p.status = 'active'", [$id]);
if (!$product) {
    json_response(['ok' => false, 'message' => 'Sản phẩm không tồn tại hoặc đã ngừng kinh doanh.'], 404);
}
$salePrice = sale_price($product);
$specs = db_all("SELECT sd.name, sd.unit, ps.value FROM product_specs ps
                 JOIN spec_definitions sd ON sd.id = ps.spec_id
                 WHERE ps.product_id = ? ORDER BY sd.sort_order LIMIT 6", [$id]);
ob_start();
require '../layout/quick_view.php';
$html = csrf_inject(ob_get_clean());    // có form "Thêm vào giỏ" → cần token
json_response(['ok' => true, 'id' => $product['id'], 'name' => $product['name'], 'html' => $html]);
```

### 11.2. `frontend/layout/quick_view.php` – [MỚI]

Nội dung khung: ảnh, tên, giá (dùng lại class `pd-price`, `price-now` của trang chi tiết nên cùng kiểu), `stock_badge()`, 6 thông số, và:
```html
<form action="<?= url('cart.php') ?>" method="post" class="add-to-cart"> … Thêm vào giỏ … </form>
<a href="<?= url('product.php?id=' . $product['id']) ?>" class="btn btn-outline">Xem đầy đủ</a>
```
Form có `class="add-to-cart"` → **dùng lại luôn** JavaScript giỏ hàng ở Bước 4.

### 11.3. [SỬA] `product_card.php` và `footer.php`

```diff
     </a>
+    <button type="button" class="quick-view-btn" data-quick-view="<?= $p['id'] ?>" title="Xem nhanh" aria-label="Xem nhanh <?= e($p['name']) ?>">
+        <i class="bi bi-eye"></i>
+    </button>
     <div class="product-body">
```
Nút đặt **ngoài** thẻ `<a class="product-thumb">` vì HTML không cho nút bấm nằm trong liên kết; CSS `position: absolute` đặt nó nổi lên góc ảnh nên bố cục thẻ không đổi.

`footer.php` thêm khung rỗng (một lần cho cả trang):
```diff
+<div class="qv-modal" id="quick-view" data-quick-url="<?= url('product-api.php') ?>" hidden>
+    <div class="qv-backdrop" data-qv-close></div>
+    <div class="qv-box" role="dialog" aria-modal="true" aria-labelledby="qv-title">
+        <button type="button" class="qv-close" data-qv-close aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
+        <div class="qv-body" data-qv-body></div>
+    </div>
+</div>
```

### 11.4. [SỬA] **Đổi cách gắn sự kiện "Thêm vào giỏ"** (sửa lại code của Bước 4)

Form trong khung Xem nhanh được tạo **sau** khi trang đã tải xong. Cách gắn từng form lúc đầu không bắt được form mới này → bấm "Thêm vào giỏ" trong khung sẽ tải lại trang. Phải chuyển sang ủy quyền sự kiện (như Bước 9):

```diff
-    document.querySelectorAll('form.add-to-cart').forEach(function (form) {
-        form.addEventListener('submit', function (ev) {
-            if (ev.submitter && ev.submitter.name === 'buy_now') return;
-            ev.preventDefault();
-            …
-        });
-    });
+    document.addEventListener('submit', function (ev) {
+        var form = ev.target;
+        if (!form.matches('form.add-to-cart')) return;
+        if (ev.submitter && ev.submitter.name === 'buy_now') return;
+        ev.preventDefault();
+        …   (phần bên trong giữ nguyên)
+    });
```

### 11.5. [SỬA] JavaScript mở khung

```js
document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-quick-view]');
    if (btn) { qvOpen(btn.getAttribute('data-quick-view'), btn); return; }
    if (!qv.hidden && ev.target.closest('[data-qv-close]')) qvClose();
});

var qvOpen = function (id, trigger) {
    qvLast = trigger;                       // nhớ nút đã bấm để trả con trỏ về khi đóng
    qv.hidden = false;
    qvBody.innerHTML = '…khung xám nhấp nháy (skeleton)…';
    fetch(qv.getAttribute('data-quick-url') + '?id=' + encodeURIComponent(id))
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res.ok) throw new Error(res.message);   // 404 → nhảy xuống catch
            qvBody.innerHTML = res.html;
        })
        .catch(function (err) { /* hiện "Không xem được sản phẩm" */ });
};
```

### 11.6. [SỬA] CSS – một chỗ phải sửa giá trị cũ

```diff
-.toast-stack { position: fixed; top: 84px; right: 20px; z-index: 1000; …
+.toast-stack { position: fixed; top: 84px; right: 20px; z-index: 1200; …
```
Khung Xem nhanh có `z-index: 1100`. Nếu thông báo vẫn 1000 thì "Đã thêm vào giỏ" bị **che sau** khung. `z-index` lớn hơn thì nằm trên.

✅ **Tự kiểm tra:** bấm con mắt → khung hiện; bấm "Thêm vào giỏ" trong khung → số trên giỏ tăng, thông báo nổi trên khung; Esc → đóng, con trỏ quay về nút con mắt.

---

# Phần 2. Kiểm thử tổng hợp

| # | Chức năng | Thao tác thử | Mong đợi | Kết quả |
|---|---|---|---|---|
| 1 | Thêm vào giỏ | Bấm ở thẻ / chi tiết / so sánh | 200, số giỏ tăng, không tải lại | Đạt |
| 2 | Thêm vào giỏ | Sản phẩm hết hàng | 422 "đã hết hàng" | Đạt |
| 3 | Thêm vào giỏ | Sửa `_csrf` | 403 | Đạt |
| 4 | Giỏ hàng | Sửa số lượng vượt tồn kho | Tự giảm về tối đa + thông báo | Đạt |
| 5 | Giỏ hàng | Xóa sản phẩm cuối | Hiện "Giỏ hàng đang trống" | Đạt |
| 6 | Mã giảm giá | Mã sai / mã đúng / bỏ mã | 422 / giảm tiền / về như cũ | Đạt |
| 7 | Chat AI | Gửi câu hỏi | Trả lời hiện trong khung | Đạt |
| 8 | Tìm kiếm | Gõ "rtx" | 6 gợi ý + "Xem tất cả 23 kết quả" | Đạt |
| 9 | Build PC | Chọn 2 linh kiện liên tiếp, bỏ 1 | Cập nhật mỗi lần, không tải lại | Đạt |
| 10 | So sánh | Thêm, bỏ, thêm cái thứ 5 | Số đổi; lần 5 báo tối đa 4 | Đạt |
| 11 | Xem nhanh | Mở, thêm vào giỏ, Esc | Hoạt động, không tải lại | Đạt |
| 12 | Tất cả | Tắt JavaScript | Các form vẫn gửi theo cách cũ | Đạt (form giữ nguyên `action`) |
| 13 | Giao diện | Chụp trang trước/sau khi thêm AJAX | Trùng khớp từng điểm ảnh | Đạt |

**Cách tự thử bằng F12:** tab **Network** → lọc **Fetch/XHR** → bấm nút → chọn dòng vừa hiện → tab **Payload** (dữ liệu gửi đi) và **Response** (JSON nhận về).

---

# Phần 3. Gỡ lỗi

| Triệu chứng | Nguyên nhân / cách sửa |
|---|---|
| Console báo `Unexpected token <` | Server trả HTML thay vì JSON: PHP có warning, hoặc bị `redirect` (VD: sai token mà dùng `csrf_verify`). Xem tab Response |
| Trang vẫn tải lại | Thiếu `ev.preventDefault()`, form thiếu class (`add-to-cart`…), hoặc JS lỗi ở đoạn trước (xem Console) |
| 403 "Phiên làm việc đã hết hạn" | Thiếu `_csrf` trong dữ liệu gửi – form phải có `method="post"` để `csrf_inject` chèn token, hoặc tự `data.append('_csrf', token)` |
| 404 khi gọi `xxx-api.php` | Chưa tải file mới lên hosting / chưa commit file mới lên GitHub |
| Lần bấm đầu chạy, lần sau không | Vùng đã bị thay `innerHTML` → sự kiện gắn trên phần tử cũ mất. Gắn vào khung cha (Bước 9) |
| JSON hỏng, lẫn `<input type="hidden" name="_csrf"…>` | HTML trong JSON có form và đi qua `csrf_inject` → phải dùng `json_response()` (Bước 1) |
| Số trên giỏ / so sánh không đổi | Thiếu `id="cart-count"` / `id="compare-count"` hoặc thẻ không được in ra khi số = 0 (Bước 3) |
| Gợi ý tìm kiếm hiện kết quả của từ cũ | Thiếu dòng so sánh `res.q` với ô nhập hiện tại (Bước 8) |
| `form.action` ra `[object HTMLInputElement]` | Form có ô `name="action"` → dùng `form.getAttribute('action')` (Bước 7) |
| Thông báo bị che sau khung nổi | `z-index` thông báo phải lớn hơn khung (Bước 11) |

---

# Phần 4. Tự luyện

Đã hiểu 11 bước thì thử tự làm những phần sau (chưa làm trong dự án), theo đúng khuôn 4 mảnh ghép ở Phần 0:

1. **Đăng ký – báo "Email đã được dùng" ngay khi gõ xong ô email**
   Gợi ý: tạo `email-check.php` (GET `?email=`), trả `{"ok": true, "exists": true}`; JS bắt sự kiện `blur` của ô email.
2. **Admin – đổi trạng thái đơn hàng ngay trong danh sách**
   Gợi ý: cách B, thêm nhánh `ajax=1` vào `admin/orders.php`; trả `{"ok": true, "status_html": "<span class=\"badge …\">"}`.
3. **Admin – công tắc ẩn/hiện sản phẩm**
   Gợi ý: POST `action=toggle_status`, trả trạng thái mới; JS đổi nhãn tại chỗ.

Mỗi bài tự kiểm tra bằng F12 → Network giống Phần 2.
