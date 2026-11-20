<?php
/** Webhook PayOS: chỉ cập nhật trạng thái sau khi xác minh chữ ký, */
define('SKIP_CSRF', true);
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

function payos_webhook_response($status, array $body)
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    payos_webhook_response(405, ['message' => 'Method not allowed']);
}

if (!payos_enabled()) {
    payos_webhook_response(500, ['message' => 'PayOS is not configured']);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload) || !payos_verify_webhook($payload)) {
    payos_webhook_response(400, ['message' => 'Invalid signature']);
}

$data = $payload['data'];
if (!isset($data['orderCode'], $data['amount'])) {
    payos_webhook_response(400, ['message' => 'Missing orderCode or amount']);
}

// Webhook có thể được gửi cho cả giao dịch thất bại; chỉ xử lý giao dịch thành công.
if (
    empty($payload['success']) ||
    !isset($data['code']) ||
    (string) $data['code'] !== '00'
) {
    payos_webhook_response(200, ['received' => true]);
}

global $pdo;

try {
    $pdo->beginTransaction();

    $order = db_one(
        'SELECT * FROM orders WHERE payos_order_code = ? FOR UPDATE',
        [(int) $data['orderCode']]
    );

    if (!$order) {
        $pdo->rollBack();
        payos_webhook_response(200, ['received' => true, 'message' => 'Order not found']);
    }

    $payment = db_one(
        'SELECT * FROM payments WHERE order_id = ? FOR UPDATE',
        [$order['id']]
    );

    if (!$payment || $payment['method'] !== 'payos') {
        $pdo->rollBack();
        payos_webhook_response(400, ['message' => 'Payment record not found']);
    }

    // Webhook có thể gửi lặp lại; payos_mark_paid() không ghi nhận 2 lần
    $ref = isset($data['reference']) ? (string) $data['reference'] : null;
    if (!payos_mark_paid($order, $payment, (int) $data['amount'], $ref)) {
        $pdo->rollBack();
        payos_webhook_response(400, ['message' => 'Payment amount mismatch']);
    }

    $pdo->commit();
    payos_webhook_response(200, ['received' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('PayOS webhook error: ' . $e->getMessage());
    payos_webhook_response(500, ['message' => 'Internal server error']);
}
