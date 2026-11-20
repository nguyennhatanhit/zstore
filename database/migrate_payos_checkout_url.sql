-- Chạy một lần trên database techstore hiện tại để bổ sung URL thanh toán PayOS.
-- Không ảnh hưởng dữ liệu hoặc các phương thức thanh toán khác.
USE techstore;

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS payos_checkout_url VARCHAR(500) NULL
    AFTER payos_order_code;

UPDATE orders o JOIN payments p ON p.order_id = o.id
SET o.payos_checkout_url = NULL, o.payos_order_code = NULL
WHERE p.method = 'payos' AND p.status = 'unpaid';