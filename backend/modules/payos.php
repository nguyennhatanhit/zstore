<?php
/** TÍCH HỢP PAYOS - thanh toán QR Hosted Checkout. */

function payos_enabled()
{
    return defined('PAYOS_CLIENT_ID') && PAYOS_CLIENT_ID !== ''
        && defined('PAYOS_API_KEY') && PAYOS_API_KEY !== ''
        && defined('PAYOS_CHECKSUM_KEY') && PAYOS_CHECKSUM_KEY !== '';
}

/** Tạo chữ ký HMAC-SHA256 theo quy tắc PayOS. */
function payos_signature(array $data)
{
    ksort($data);
    $pairs = [];
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if ($value === null || $value === 'null' || $value === 'undefined') {
            $value = '';
        }
        $pairs[] = $key . '=' . $value;
    }
    return hash_hmac('sha256', implode('&', $pairs), PAYOS_CHECKSUM_KEY);
}

/** URL tuyệt đối để PayOS đưa khách quay về. */
function payos_site_url($path)
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . url($path);
}

/** Gửi request REST tới PayOS. */
function payos_api_request($method, $path, array $body = null)
{
    if (!function_exists('curl_init')) {
        return ['error' => 'PHP chưa bật extension cURL.'];
    }

    $ch = curl_init('https://api-merchant.payos.vn' . $path);
    $headers = [
        'Content-Type: application/json',
        'x-client-id: ' . PAYOS_CLIENT_ID,
        'x-api-key: ' . PAYOS_API_KEY,
    ];
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ];
    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode(
            $body,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['error' => 'Không kết nối được PayOS: ' . $curlError];
    }
    $result = json_decode($response, true);
    if (!is_array($result)) {
        return ['error' => 'PayOS trả về dữ liệu không hợp lệ (HTTP ' . $httpCode . ').'];
    }
    if ($httpCode < 200 || $httpCode >= 300 || !isset($result['code']) || $result['code'] !== '00') {
        return ['error' => 'PayOS báo lỗi: ' . (isset($result['desc']) ? $result['desc'] : 'HTTP ' . $httpCode)];
    }
    return ['data' => isset($result['data']) ? $result['data'] : []];
}

/** Tạo hoặc lấy lại link thanh toán cho đơn. */
function payos_checkout_for_order(array $order, array $payment)
{
    global $pdo;

    if (!payos_enabled()) {
        return ['error' => 'PayOS chưa được cấu hình. Hãy điền 4 hằng số PAYOS_* trong config.php.'];
    }
    if ((int) $payment['amount'] < 2000 || (int) $payment['amount'] > 1000000000) {
        return ['error' => 'Số tiền thanh toán phải từ 2.000 đến 1.000.000.000 VNĐ.'];
    }
    if ($payment['status'] === 'paid') {
        return ['error' => 'Đơn hàng này đã được thanh toán.'];
    }
    if ($order['status'] === 'cancelled') {
        return ['error' => 'Đơn hàng đã bị hủy, không thể thanh toán.'];
    }

    // Đã có link: chỉ dùng lại khi link còn chờ thanh toán
    if (!empty($order['payos_checkout_url']) && !empty($order['payos_order_code'])) {
        $old = payos_get_status($order['payos_order_code']);
        $oldStatus = isset($old['data']['status']) ? $old['data']['status'] : '';
        if ($oldStatus === 'PENDING') {
            return ['url' => $order['payos_checkout_url']];
        }
        if ($oldStatus === 'PAID') {
            return ['paid' => true];
        }
        // CANCELLED / EXPIRED → tạo link mới với mã mới (PayOS không cho dùng lại orderCode)
    }

    $orderCode = random_int(100000000, 999999999);
    while (db_value('SELECT COUNT(*) FROM orders WHERE payos_order_code = ?', [$orderCode])) {
        $orderCode = random_int(100000000, 999999999);
    }

    // Dùng tên tham số riêng "order" / "tz_cancel": PayOS tự gắn thêm code=00, cancel=false, status=...
    $returnUrl = payos_site_url('payos-return.php?order=' . rawurlencode($order['order_code']));
    $cancelUrl = $returnUrl . '&tz_cancel=1';
    // Nội dung chuyển khoản tối đa 9 ký tự, không dấu: tiền tố trong .env (VD ATS) + mã đơn
    $prefix      = substr(preg_replace('/[^A-Za-z0-9]/', '', PAYOS_DESC_PREFIX), 0, 3);
    $description = $prefix . substr((string) (int) $order['id'], -(9 - strlen($prefix)));

    $signatureData = [
        'amount' => (int) $payment['amount'],
        'cancelUrl' => $cancelUrl,
        'description' => $description,
        'orderCode' => $orderCode,
        'returnUrl' => $returnUrl,
    ];
    $body = $signatureData;
    $body['signature'] = payos_signature($signatureData);

    $result = payos_api_request('POST', '/v2/payment-requests', $body);
    if (isset($result['error'])) {
        return $result;
    }

    $data = $result['data'];
    if (empty($data['checkoutUrl'])) {
        return ['error' => 'PayOS không trả về checkoutUrl.'];
    }

    db_run(
        'UPDATE orders SET payos_order_code = ?, payos_checkout_url = ? WHERE id = ?',
        [$orderCode, $data['checkoutUrl'], $order['id']]
    );

    return ['url' => $data['checkoutUrl']];
}

/** Hỏi PayOS trạng thái link: data.status = PENDING | PAID | CANCELLED | EXPIRED ... */
function payos_get_status($orderCode)
{
    return payos_api_request('GET', '/v2/payment-requests/' . (int) $orderCode);
}

/** Hủy link (khi đơn bị hủy) để khách không quét trả tiền cho đơn đã hủy */
function payos_cancel_link($orderCode, $reason)
{
    return payos_api_request('POST', '/v2/payment-requests/' . (int) $orderCode . '/cancel', ['cancellationReason' => $reason]);
}

/** Ghi nhận đã thanh toán - dùng chung cho Webhook và trang quay về. */
function payos_mark_paid(array $order, array $payment, $amount, $reference = null)
{
    if ($payment['status'] === 'paid') {
        return true;
    }
    if ($payment['status'] !== 'unpaid' || (int) $amount < (int) round((float) $payment['amount'])) {
        return false;
    }
    db_run(
        "UPDATE payments SET status = 'paid', transaction_code = ?, paid_at = ? WHERE id = ? AND status = 'unpaid'",
        [$reference, db_now(), $payment['id']]
    );
    $note = 'Khách đã thanh toán qua PayOS (' . money($amount) . ')';
    if ($order['status'] === 'cancelled') {
        $note .= ' - ĐƠN ĐÃ HỦY, cần hoàn tiền cho khách';
    }
    order_add_history($order['id'], $order['status'], $note);
    return true;
}

/** Kiểm tra chữ ký Webhook bằng checksum key. */
function payos_verify_webhook(array $payload)
{
    if (empty($payload['signature']) || !isset($payload['data']) || !is_array($payload['data'])) {
        return false;
    }
    return hash_equals(payos_signature($payload['data']), (string) $payload['signature']);
}
