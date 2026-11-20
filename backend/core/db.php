<?php
/** TRUY VẤN CSDL (luôn dùng dấu ? để chống SQL injection) */

/** Lấy nhiều dòng: db_all("SELECT * FROM products WHERE price < ?", [5000000]) */
function db_all($sql, $params = [])
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Lấy 1 dòng (không có thì trả về null) */
function db_one($sql, $params = [])
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Lấy 1 giá trị: db_value("SELECT COUNT(*) FROM orders") */
function db_value($sql, $params = [])
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

/** Chạy INSERT/UPDATE/DELETE, trả về số dòng bị ảnh hưởng */
function db_run($sql, $params = [])
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/** Id vừa thêm */
function db_last_id()
{
    global $pdo;
    return $pdo->lastInsertId();
}

/** Chuỗi "?, ?, ?" cho câu IN (...): "WHERE id IN (" . placeholders($ids) . ")" */
function placeholders($array)
{
    return implode(',', array_fill(0, count($array), '?'));
}

/** Thời điểm hiện tại dạng lưu CSDL */
function db_now()
{
    return date('Y-m-d H:i:s');
}
