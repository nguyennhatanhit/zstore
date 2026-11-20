-- =====================================================================
--  CẬP NHẬT CSDL ĐANG DÙNG (không mất dữ liệu) - chạy 1 lần
--  phpMyAdmin > chọn database techstore > tab SQL > dán > Thực hiện
--  (Import lại techstore.sql mới thì KHÔNG cần chạy file này)
-- =====================================================================
-- Giới hạn tần suất: đếm số lần đăng nhập sai, số câu hỏi AI... (bản ghi cũ hơn 1 ngày tự xóa)
CREATE TABLE IF NOT EXISTS rate_limits (
    id         BIGINT AUTO_INCREMENT PRIMARY KEY,
    action     VARCHAR(30)  NOT NULL,                -- login | login_ip | ai
    ident      VARCHAR(190) NOT NULL,                -- email hoặc địa chỉ IP
    created_at DATETIME     NOT NULL,
    INDEX idx_rate (action, ident, created_at)
) ENGINE=InnoDB;

-- AI ---
CREATE TABLE IF NOT EXISTS rate_limits (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    action      VARCHAR(50)  NOT NULL,
    identifier  VARCHAR(255) NOT NULL,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action_id_time (action, identifier, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
