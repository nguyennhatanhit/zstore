-- =====================================================================
--  CSDL: techstore — Website cửa hàng công nghệ (Linh kiện, PC, Laptop)
--  Dùng cho XAMPP / MySQL 5.7+ / MariaDB 10.4+
--  Import: phpMyAdmin > Import > chọn file này (tự tạo lại database techstore)
--  LƯU Ý: import sẽ XÓA database techstore cũ và tạo lại từ đầu
-- =====================================================================

DROP DATABASE IF EXISTS techstore;
CREATE DATABASE techstore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE techstore;

-- ---------------------------------------------------------------------
-- 1. TÀI KHOẢN
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(100) NOT NULL,
    email           VARCHAR(150) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    phone           VARCHAR(20),
    address         VARCHAR(255),
    avatar          VARCHAR(255),
    role            ENUM('customer','admin') NOT NULL DEFAULT 'customer',
    status          ENUM('active','locked')  NOT NULL DEFAULT 'active',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. DANH MỤC & THƯƠNG HIỆU
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    parent_id       INT NULL,
    name            VARCHAR(100) NOT NULL,
    slug            VARCHAR(120) NOT NULL UNIQUE,
    code            VARCHAR(30)  NULL UNIQUE,      -- cpu, mainboard, ram... (dùng cho luật tương thích)
    description     TEXT,
    sort_order      INT DEFAULT 0,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE brands (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL UNIQUE,
    slug            VARCHAR(120) NOT NULL UNIQUE,
    logo            VARCHAR(255)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. SẢN PHẨM, HÌNH ẢNH, THÔNG SỐ KỸ THUẬT
-- ---------------------------------------------------------------------
CREATE TABLE products (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    category_id     INT NOT NULL,
    brand_id        INT NULL,
    name            VARCHAR(255) NOT NULL,
    slug            VARCHAR(280) NOT NULL UNIQUE,
    sku             VARCHAR(50)  NOT NULL UNIQUE,
    short_desc      VARCHAR(500),
    description     LONGTEXT,
    price           DECIMAL(15,0) NOT NULL DEFAULT 0,     -- VNĐ
    stock_quantity  INT NOT NULL DEFAULT 0,
    warranty_months INT DEFAULT 12,
    status          ENUM('active','hidden') NOT NULL DEFAULT 'active',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (brand_id)    REFERENCES brands(id) ON DELETE SET NULL,
    INDEX idx_price (price),
    FULLTEXT INDEX ft_search (name, short_desc)          -- tìm kiếm theo từ khóa
) ENGINE=InnoDB;

CREATE TABLE product_images (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    product_id      INT NOT NULL,
    image_url       VARCHAR(255) NOT NULL,
    is_primary      TINYINT(1) DEFAULT 0,
    sort_order      INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Định nghĩa thông số theo từng danh mục (CPU có socket, RAM có ram_type...)
CREATE TABLE spec_definitions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    category_id     INT NOT NULL,
    code            VARCHAR(50)  NOT NULL,           -- socket, ram_type, tdp, wattage...
    name            VARCHAR(100) NOT NULL,           -- "Socket", "Loại RAM"
    unit            VARCHAR(20),                     -- W, GB, MHz
    data_type       ENUM('text','number') DEFAULT 'text',
    is_filterable   TINYINT(1) DEFAULT 1,            -- hiện ở bộ lọc
    sort_order      INT DEFAULT 0,
    UNIQUE KEY uq_cat_code (category_id, code),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Giá trị thông số của từng sản phẩm
CREATE TABLE product_specs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    product_id      INT NOT NULL,
    spec_id         INT NOT NULL,
    value           VARCHAR(255) NOT NULL,
    UNIQUE KEY uq_product_spec (product_id, spec_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (spec_id)    REFERENCES spec_definitions(id) ON DELETE CASCADE,
    INDEX idx_value (value)
) ENGINE=InnoDB;

-- Luật kiểm tra tương thích giữa 2 loại linh kiện
--   operator: eq  = giá trị A phải bằng B
--             lte = A <= B   (vd: chiều dài VGA <= chiều dài tối đa của case)
--             in  = A nằm trong danh sách B (B dạng "ATX,mATX,ITX")
CREATE TABLE compatibility_rules (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    category_a      VARCHAR(30) NOT NULL,            -- categories.code
    spec_code_a     VARCHAR(50) NOT NULL,
    operator        ENUM('eq','lte','gte','in') NOT NULL DEFAULT 'eq',
    category_b      VARCHAR(30) NOT NULL,
    spec_code_b     VARCHAR(50) NOT NULL,
    message         VARCHAR(255) NOT NULL,           -- thông báo khi KHÔNG tương thích
    is_active       TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. KHO HÀNG
-- ---------------------------------------------------------------------
CREATE TABLE inventory_logs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    product_id      INT NOT NULL,
    change_qty      INT NOT NULL,                    -- +nhập / -xuất
    type            ENUM('import','export','adjust','order','cancel') NOT NULL,
    note            VARCHAR(255),
    created_by      INT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. KHUYẾN MÃI
-- ---------------------------------------------------------------------
CREATE TABLE promotions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    code            VARCHAR(50) NULL UNIQUE,         -- mã giảm giá (NULL = tự áp dụng)
    discount_type   ENUM('percent','fixed') NOT NULL,
    discount_value  DECIMAL(15,0) NOT NULL,
    min_order_value DECIMAL(15,0) DEFAULT 0,
    max_discount    DECIMAL(15,0) NULL,
    start_date      DATETIME NOT NULL,
    end_date        DATETIME NOT NULL,
    usage_limit     INT NULL,
    used_count      INT DEFAULT 0,
    status          ENUM('active','inactive') DEFAULT 'active',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Khuyến mãi áp dụng cho sản phẩm cụ thể (không có dòng nào = áp dụng toàn đơn)
CREATE TABLE promotion_products (
    promotion_id    INT NOT NULL,
    product_id      INT NOT NULL,
    PRIMARY KEY (promotion_id, product_id),
    FOREIGN KEY (promotion_id) REFERENCES promotions(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id)   REFERENCES products(id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. ĐƠN HÀNG & THANH TOÁN
--    (Giỏ hàng & danh sách so sánh lưu trong $_SESSION nên không cần bảng)
-- ---------------------------------------------------------------------
CREATE TABLE orders (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    order_code       VARCHAR(20) NOT NULL UNIQUE,    -- vd: TS20261115001
    user_id          INT NOT NULL,
    receiver_name    VARCHAR(100) NOT NULL,
    receiver_phone   VARCHAR(20)  NOT NULL,
    shipping_address VARCHAR(255) NOT NULL,
    note             VARCHAR(500),
    promotion_id     INT NULL,
    subtotal         DECIMAL(15,0) NOT NULL,
    discount         DECIMAL(15,0) DEFAULT 0,
    shipping_fee     DECIMAL(15,0) DEFAULT 0,
    total            DECIMAL(15,0) NOT NULL,
    status           ENUM('pending','confirmed','shipping','completed','cancelled') DEFAULT 'pending',
    cancel_reason    VARCHAR(255),
    payos_order_code BIGINT NULL,                    -- mã đơn gửi sang payOS
    payos_checkout_url VARCHAR(500) NULL,             -- link Hosted Checkout PayOS
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)      REFERENCES users(id),
    FOREIGN KEY (promotion_id) REFERENCES promotions(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_payos (payos_order_code)
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    order_id        INT NOT NULL,
    product_id      INT NULL,
    product_name    VARCHAR(255) NOT NULL,           -- lưu lại tại thời điểm mua
    price           DECIMAL(15,0) NOT NULL,
    quantity        INT NOT NULL,
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE order_status_history (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    order_id        INT NOT NULL,
    status          ENUM('pending','confirmed','shipping','completed','cancelled') NOT NULL,
    note            VARCHAR(255),
    changed_by      INT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)   REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id)  ON DELETE SET NULL
) ENGINE=InnoDB;

-- Mỗi đơn có 1 thông tin thanh toán
CREATE TABLE payments (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    order_id         INT NOT NULL UNIQUE,
    method           ENUM('cod','bank_transfer','payos') NOT NULL DEFAULT 'cod',   -- COD / chuyển khoản / QR payOS
    amount           DECIMAL(15,0) NOT NULL,
    status           ENUM('unpaid','paid','refunded') DEFAULT 'unpaid',
    note             VARCHAR(255),                                         -- ghi chú của admin
    transaction_code VARCHAR(100),
    paid_at          DATETIME NULL,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Giới hạn tần suất: đếm số lần đăng nhập sai, số câu hỏi AI... (bản ghi cũ hơn 1 ngày tự xóa)
CREATE TABLE rate_limits (
    id         BIGINT AUTO_INCREMENT PRIMARY KEY,
    action     VARCHAR(30)  NOT NULL,                -- login | login_ip | ai
    ident      VARCHAR(190) NOT NULL,                -- email hoặc địa chỉ IP
    created_at DATETIME     NOT NULL,
    INDEX idx_rate (action, ident, created_at)
) ENGINE=InnoDB;

-- =====================================================================
--  DỮ LIỆU MẪU
-- =====================================================================

-- Mật khẩu: admin@techstore.vn / admin123   |   khach@gmail.com / 123456
INSERT INTO users (full_name, email, password_hash, phone, address, role) VALUES
('Quản trị viên', 'admin@techstore.vn', '$2y$10$.COAf/QnSVU0ggPOwexiKODphQkXminks4xbJ5eUvJvk0jmCk4rqi', '0900000000', 'TP. Hồ Chí Minh', 'admin'),
('Nguyễn Văn A',  'khach@gmail.com',    '$2y$10$YzfeSEgwuGwDOR63.LcR/uv4flJs3VeY.4HBrhRsrRfiNh.euztcC', '0911111111', '123 Lê Lợi, Q.1, TP.HCM', 'customer');

INSERT INTO categories (id, parent_id, name, slug, code, sort_order) VALUES
(1,  NULL, 'Linh kiện máy tính', 'linh-kien',  NULL,        1),
(2,  NULL, 'Laptop',             'laptop',     'laptop',    2),
(3,  NULL, 'PC lắp sẵn',         'pc',         'pc',        3),
(4,  1,    'CPU - Bộ vi xử lý',  'cpu',        'cpu',       1),
(5,  1,    'Mainboard',          'mainboard',  'mainboard', 2),
(6,  1,    'RAM',                'ram',        'ram',       3),
(7,  1,    'Card đồ họa (VGA)',  'vga',        'vga',       4),
(8,  1,    'Ổ cứng SSD/HDD',     'storage',    'storage',   5),
(9,  1,    'Nguồn (PSU)',        'psu',        'psu',       6),
(10, 1,    'Vỏ case',            'case',       'case',      7),
(11, 1,    'Tản nhiệt',          'cooler',     'cooler',    8);

INSERT INTO brands (id, name, slug) VALUES
(1,'Intel','intel'),(2,'AMD','amd'),(3,'ASUS','asus'),(4,'MSI','msi'),
(5,'Gigabyte','gigabyte'),(6,'Kingston','kingston'),(7,'Corsair','corsair'),
(8,'Samsung','samsung'),(9,'Dell','dell'),(10,'Lenovo','lenovo'),
(11,'NVIDIA','nvidia'),(12,'Cooler Master','cooler-master'),(13,'NZXT','nzxt');

-- Định nghĩa thông số
INSERT INTO spec_definitions (id, category_id, code, name, unit, data_type, sort_order) VALUES
-- CPU
(1,  4, 'socket',       'Socket',             NULL,  'text',   1),
(2,  4, 'cores',        'Số nhân',            NULL,  'number', 2),
(3,  4, 'threads',      'Số luồng',           NULL,  'number', 3),
(4,  4, 'base_clock',   'Xung nhịp cơ bản',   'GHz', 'number', 4),
(5,  4, 'tdp',          'TDP',                'W',   'number', 5),
-- Mainboard
(10, 5, 'socket',       'Socket',             NULL,  'text',   1),
(11, 5, 'chipset',      'Chipset',            NULL,  'text',   2),
(12, 5, 'ram_type',     'Loại RAM hỗ trợ',    NULL,  'text',   3),
(13, 5, 'ram_slots',    'Số khe RAM',         NULL,  'number', 4),
(14, 5, 'form_factor',  'Kích thước',         NULL,  'text',   5),
-- RAM
(20, 6, 'ram_type',     'Loại RAM',           NULL,  'text',   1),
(21, 6, 'capacity',     'Dung lượng',         'GB',  'number', 2),
(22, 6, 'speed',        'Bus',                'MHz', 'number', 3),
-- VGA
(30, 7, 'chip',         'GPU',                NULL,  'text',   1),
(31, 7, 'vram',         'Bộ nhớ',             'GB',  'number', 2),
(32, 7, 'tdp',          'Công suất',          'W',   'number', 3),
(33, 7, 'length',       'Chiều dài',          'mm',  'number', 4),
-- Storage
(40, 8, 'type',         'Loại',               NULL,  'text',   1),
(41, 8, 'capacity',     'Dung lượng',         'GB',  'number', 2),
(42, 8, 'interface',    'Chuẩn kết nối',      NULL,  'text',   3),
-- PSU
(50, 9, 'wattage',      'Công suất',          'W',   'number', 1),
(51, 9, 'efficiency',   'Chuẩn hiệu suất',    NULL,  'text',   2),
-- Case
(60, 10,'supported_form','Hỗ trợ mainboard',  NULL,  'text',   1),
(61, 10,'max_gpu_length','Chiều dài VGA tối đa','mm','number', 2),
-- Cooler
(70, 11,'supported_sockets','Socket hỗ trợ',  NULL,  'text',   1),
(71, 11,'max_tdp',      'TDP tối đa',         'W',   'number', 2),
-- Laptop
(80, 2, 'cpu',          'CPU',                NULL,  'text',   1),
(81, 2, 'ram',          'RAM',                'GB',  'number', 2),
(82, 2, 'storage',      'Ổ cứng',             'GB',  'number', 3),
(83, 2, 'gpu',          'Card đồ họa',        NULL,  'text',   4),
(84, 2, 'screen',       'Màn hình',           'inch','number', 5),
(85, 2, 'weight',       'Trọng lượng',        'kg',  'number', 6);

-- Luật tương thích
INSERT INTO compatibility_rules (category_a, spec_code_a, operator, category_b, spec_code_b, message) VALUES
('cpu',       'socket',      'eq',  'mainboard', 'socket',            'CPU và Mainboard khác socket'),
('ram',       'ram_type',    'eq',  'mainboard', 'ram_type',          'Loại RAM không được Mainboard hỗ trợ'),
('mainboard', 'form_factor', 'in',  'case',      'supported_form',    'Vỏ case không vừa kích thước Mainboard'),
('vga',       'length',      'lte', 'case',      'max_gpu_length',    'Card đồ họa quá dài so với vỏ case'),
('cpu',       'socket',      'in',  'cooler',    'supported_sockets', 'Tản nhiệt không hỗ trợ socket CPU'),
('cpu',       'tdp',         'lte', 'cooler',    'max_tdp',           'Tản nhiệt không đủ sức cho TDP của CPU');
-- Ghi chú: kiểm tra công suất nguồn (tổng TDP CPU + VGA + ~100W <= PSU.wattage) xử lý riêng trong backend/modules/compatibility.php.

-- Sản phẩm
INSERT INTO products (id, category_id, brand_id, name, slug, sku, short_desc, price, stock_quantity, warranty_months) VALUES
(1,  4, 1,  'Intel Core i5-14400F',              'intel-core-i5-14400f',        'CPU-I5-14400F',  '10 nhân 16 luồng, socket LGA1700',             4290000,  25, 36),
(2,  4, 1,  'Intel Core i7-14700K',              'intel-core-i7-14700k',        'CPU-I7-14700K',  '20 nhân 28 luồng, socket LGA1700',             9990000,  10, 36),
(3,  4, 2,  'AMD Ryzen 5 7600X',                 'amd-ryzen-5-7600x',           'CPU-R5-7600X',   '6 nhân 12 luồng, socket AM5',                  5490000,  18, 36),
(4,  5, 3,  'ASUS PRIME B760M-A WIFI D4',        'asus-prime-b760m-a-wifi-d4',  'MB-ASUS-B760M',  'LGA1700, DDR4, mATX',                          3590000,  12, 36),
(5,  5, 4,  'MSI MAG B650 TOMAHAWK WIFI',        'msi-mag-b650-tomahawk-wifi',  'MB-MSI-B650',    'AM5, DDR5, ATX',                               5290000,   8, 36),
(6,  6, 6,  'Kingston Fury Beast 16GB DDR4 3200','kingston-fury-16gb-ddr4',     'RAM-KF-16-D4',   '1x16GB DDR4 3200MHz',                           990000,  40, 36),
(7,  6, 7,  'Corsair Vengeance 32GB DDR5 6000',  'corsair-vengeance-32gb-ddr5', 'RAM-CS-32-D5',   '2x16GB DDR5 6000MHz',                          2890000,  20, 36),
(8,  7, 3,  'ASUS Dual RTX 4060 8GB',            'asus-dual-rtx-4060-8gb',      'VGA-ASUS-4060',  'RTX 4060, 8GB GDDR6',                          7990000,  15, 36),
(9,  7, 4,  'MSI RTX 4070 SUPER Gaming X Slim',  'msi-rtx-4070-super-gaming',   'VGA-MSI-4070S',  'RTX 4070 SUPER, 12GB GDDR6X',                 16990000,   6, 36),
(10, 8, 8,  'Samsung 990 EVO 1TB NVMe',          'samsung-990-evo-1tb',         'SSD-SS-990-1T',  'NVMe PCIe 4.0, 1TB',                           2190000,  30, 60),
(11, 9, 7,  'Corsair RM750e 750W 80+ Gold',      'corsair-rm750e',              'PSU-CS-RM750E',  '750W, 80 Plus Gold, full modular',             2590000,  14, 84),
(12, 9, 12, 'Cooler Master MWE 550 Bronze V2',   'cm-mwe-550-bronze',           'PSU-CM-MWE550',  '550W, 80 Plus Bronze',                         1190000,  20, 36),
(13, 10,13, 'NZXT H5 Flow',                      'nzxt-h5-flow',                'CASE-NZXT-H5',   'Mid tower, hỗ trợ ATX',                        2290000,  10, 24),
(14, 11,12, 'Cooler Master Hyper 212 Black',     'cm-hyper-212-black',          'COOL-CM-212',    'Tản khí, hỗ trợ LGA1700/AM5',                   890000,  25, 24),
(15, 2, 9,  'Dell Inspiron 15 3530',             'dell-inspiron-15-3530',       'LT-DELL-3530',   'i5-1335U, 16GB, 512GB SSD, 15.6" FHD',        15990000,  12, 12),
(16, 2, 10, 'Lenovo LOQ 15IAX9',                 'lenovo-loq-15iax9',           'LT-LNV-LOQ15',   'i5-12450HX, 16GB, RTX 3050 6GB, 15.6" 144Hz',  20490000,   7, 24),
(17, 2, 3,  'ASUS TUF Gaming A15 FA507NV',       'asus-tuf-a15-fa507nv',        'LT-ASUS-A15',    'Ryzen 7 7735HS, 16GB, RTX 4060, 15.6" 144Hz',  25990000,   0, 24);

INSERT INTO product_images (product_id, image_url, is_primary) VALUES
(1,'uploads/products/i5-14400f.jpg',1),(2,'uploads/products/i7-14700k.jpg',1),
(3,'uploads/products/r5-7600x.jpg',1),(4,'uploads/products/b760m.jpg',1),
(5,'uploads/products/b650.jpg',1),(6,'uploads/products/fury-ddr4.jpg',1),
(7,'uploads/products/vengeance-ddr5.jpg',1),(8,'uploads/products/rtx4060.jpg',1),
(9,'uploads/products/rtx4070s.jpg',1),(10,'uploads/products/990evo.jpg',1),
(11,'uploads/products/rm750e.jpg',1),(12,'uploads/products/mwe550.jpg',1),
(13,'uploads/products/h5flow.jpg',1),(14,'uploads/products/hyper212.jpg',1),
(15,'uploads/products/inspiron3530.jpg',1),(16,'uploads/products/loq15.jpg',1),
(17,'uploads/products/tuf-a15.jpg',1);

INSERT INTO product_specs (product_id, spec_id, value) VALUES
-- CPU
(1,1,'LGA1700'),(1,2,'10'),(1,3,'16'),(1,4,'2.5'),(1,5,'65'),
(2,1,'LGA1700'),(2,2,'20'),(2,3,'28'),(2,4,'3.4'),(2,5,'125'),
(3,1,'AM5'),(3,2,'6'),(3,3,'12'),(3,4,'4.7'),(3,5,'105'),
-- Mainboard
(4,10,'LGA1700'),(4,11,'B760'),(4,12,'DDR4'),(4,13,'4'),(4,14,'mATX'),
(5,10,'AM5'),(5,11,'B650'),(5,12,'DDR5'),(5,13,'4'),(5,14,'ATX'),
-- RAM
(6,20,'DDR4'),(6,21,'16'),(6,22,'3200'),
(7,20,'DDR5'),(7,21,'32'),(7,22,'6000'),
-- VGA
(8,30,'RTX 4060'),(8,31,'8'),(8,32,'115'),(8,33,'227'),
(9,30,'RTX 4070 SUPER'),(9,31,'12'),(9,32,'220'),(9,33,'307'),
-- Storage
(10,40,'SSD NVMe'),(10,41,'1000'),(10,42,'PCIe 4.0'),
-- PSU
(11,50,'750'),(11,51,'80 Plus Gold'),
(12,50,'550'),(12,51,'80 Plus Bronze'),
-- Case
(13,60,'ATX,mATX,ITX'),(13,61,'365'),
-- Cooler
(14,70,'LGA1700,AM5,AM4'),(14,71,'150'),
-- Laptop
(15,80,'Intel Core i5-1335U'),(15,81,'16'),(15,82,'512'),(15,83,'Intel Iris Xe'),(15,84,'15.6'),(15,85,'1.65'),
(16,80,'Intel Core i5-12450HX'),(16,81,'16'),(16,82,'512'),(16,83,'RTX 3050 6GB'),(16,84,'15.6'),(16,85,'2.38'),
(17,80,'AMD Ryzen 7 7735HS'),(17,81,'16'),(17,82,'512'),(17,83,'RTX 4060 8GB'),(17,84,'15.6'),(17,85,'2.2');

-- Nhập kho ban đầu
INSERT INTO inventory_logs (product_id, change_qty, type, note, created_by)
SELECT id, stock_quantity, 'import', 'Nhập kho ban đầu', 1 FROM products WHERE stock_quantity > 0;

-- Khuyến mãi
INSERT INTO promotions (id, name, code, discount_type, discount_value, min_order_value, max_discount, start_date, end_date) VALUES
(1, 'Black Friday - Giảm 10% VGA', NULL,       'percent', 10,     0,        2000000, '2026-11-20 00:00:00', '2026-11-30 23:59:59'),
(2, 'Mã giảm 500K đơn từ 10 triệu', 'TECH500K', 'fixed',   500000, 10000000, NULL,    '2026-11-01 00:00:00', '2026-12-31 23:59:59');
INSERT INTO promotion_products (promotion_id, product_id) VALUES (1,8),(1,9);

-- Đơn hàng mẫu
INSERT INTO orders (id, order_code, user_id, receiver_name, receiver_phone, shipping_address, subtotal, discount, shipping_fee, total, status, created_at) VALUES
(1, 'TS20261110001', 2, 'Nguyễn Văn A', '0911111111', '123 Lê Lợi, Q.1, TP.HCM', 5280000, 0, 0, 5280000, 'completed', '2026-11-10 09:15:00'),
(2, 'TS20261114001', 2, 'Nguyễn Văn A', '0911111111', '123 Lê Lợi, Q.1, TP.HCM', 7990000, 0, 0, 7990000, 'shipping', '2026-11-14 20:40:00');

INSERT INTO order_items (order_id, product_id, product_name, price, quantity) VALUES
(1, 1, 'Intel Core i5-14400F', 4290000, 1),
(1, 6, 'Kingston Fury Beast 16GB DDR4 3200', 990000, 1),
(2, 8, 'ASUS Dual RTX 4060 8GB', 7990000, 1);

INSERT INTO order_status_history (order_id, status, note, changed_by, created_at) VALUES
(1,'pending',  'Đặt hàng thành công', 2, '2026-11-10 09:15:00'),
(1,'confirmed','Đã xác nhận đơn',     1, '2026-11-10 10:00:00'),
(1,'shipping', 'Đã giao cho vận chuyển', 1, '2026-11-10 15:00:00'),
(1,'completed','Giao hàng thành công',1, '2026-11-12 11:30:00'),
(2,'pending',  'Đặt hàng thành công', 2, '2026-11-14 20:40:00'),
(2,'confirmed','Đã xác nhận đơn',     1, '2026-11-15 08:30:00'),
(2,'shipping', 'Đã giao cho vận chuyển', 1, '2026-11-15 13:00:00');

INSERT INTO payments (order_id, method, amount, status, transaction_code, paid_at) VALUES
(1, 'bank_transfer', 5280000, 'paid',   'FT26314001', '2026-11-10 09:20:00'),
(2, 'cod',           7990000, 'unpaid', NULL,         NULL);


-- =====================================================================
--  VIEW hỗ trợ thống kê (trang quản trị)
-- =====================================================================
CREATE VIEW v_revenue_daily AS
SELECT DATE(o.created_at) AS day, COUNT(*) AS orders, SUM(o.total) AS revenue
FROM orders o
WHERE o.status = 'completed'
GROUP BY DATE(o.created_at);

CREATE VIEW v_low_stock AS
SELECT p.id, p.name, p.sku, p.stock_quantity, c.name AS category
FROM products p JOIN categories c ON c.id = p.category_id
WHERE p.stock_quantity <= 5;
