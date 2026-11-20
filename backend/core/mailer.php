<?php
/** GỬI EMAIL QUA SMTP (Gmail) - không cần cài thư viện ngoài. */

function mail_configured()
{
    return MAIL_USERNAME !== '' && MAIL_PASSWORD !== '';
}

function send_mail($to, $subject, $html)
{
    if (!mail_configured()) {
        $text = trim(html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/div|\/h\d|\/tr)>/i', "\n", $html)), ENT_QUOTES, 'UTF-8'));
        $text = preg_replace("/\n\s*\n+/", "\n", $text);
        @file_put_contents(ROOT . '/logs/mail.log', '[' . date('Y-m-d H:i:s') . "] To: $to | $subject\n$text\n\n", FILE_APPEND);
        return true;
    }

    try {
        smtp_send($to, $subject, $html);
        return true;
    } catch (Throwable $e) {
        error_log('[' . APP_NAME . '] Gửi email thất bại: ' . $e->getMessage());
        return false;
    }
}

function smtp_send($to, $subject, $html)
{
    $host = (MAIL_ENCRYPTION === 'ssl' ? 'ssl://' : '') . MAIL_HOST;
    $fp   = @stream_socket_client($host . ':' . MAIL_PORT, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, mail_ssl_context());
    if (!$fp) {
        throw new RuntimeException("Không kết nối được máy chủ email ($errstr)");
    }
    stream_set_timeout($fp, 15);

    // Đọc phản hồi (có thể nhiều dòng "250-...") và kiểm tra mã trạng thái
    $expect = function ($codes) use ($fp) {
        $res = '';
        while (($line = fgets($fp, 515)) !== false) {
            $res .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        if (!in_array((int) substr($res, 0, 3), (array) $codes, true)) {
            throw new RuntimeException('SMTP: ' . trim($res));
        }
        return $res;
    };
    $cmd = function ($line, $codes) use ($fp, $expect) {
        fwrite($fp, $line . "\r\n");
        return $expect($codes);
    };

    $me = 'ats-estore.local';
    $expect(220);
    $cmd("EHLO $me", 250);
    if (MAIL_ENCRYPTION === 'tls') {
        $cmd('STARTTLS', 220);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('Không bật được TLS');
        }
        $cmd("EHLO $me", 250);
    }
    $cmd('AUTH LOGIN', 334);
    $cmd(base64_encode(MAIL_USERNAME), 334);
    $cmd(base64_encode(MAIL_PASSWORD), 235);

    $from = MAIL_USERNAME;
    $cmd("MAIL FROM:<$from>", 250);
    $cmd("RCPT TO:<$to>", [250, 251]);
    $cmd('DATA', 354);

    $enc = function ($s) { return '=?UTF-8?B?' . base64_encode($s) . '?='; };
    $headers = [
        'Date: ' . date('r'),
        'From: ' . $enc(MAIL_FROM_NAME) . " <$from>",
        "To: <$to>",
        'Subject: ' . $enc($subject),
        'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . substr(strrchr($from, '@'), 1) . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    $body = implode("\r\n", $headers) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($html), 76, "\r\n"));
    $cmd($body . "\r\n.", 250);
    $cmd('QUIT', 221);
    fclose($fp);
}

/** XAMPP trên Windows thường chưa khai báo openssl.cafile nên PHP không kiểm tra được chứng chỉ của Gmail. */
function mail_ssl_context()
{
    $ssl = ['verify_peer' => true, 'verify_peer_name' => true];
    if (!ini_get('openssl.cafile')) {
        foreach ([ini_get('curl.cainfo'), 'C:/xampp/apache/bin/curl-ca-bundle.crt', 'C:/xampp/php/extras/ssl/cacert.pem'] as $ca) {
            if ($ca && is_file($ca)) { $ssl['cafile'] = $ca; break; }
        }
    }
    return stream_context_create(['ssl' => $ssl]);
}

/** Khung email chung, màu theo giao diện cửa hàng */
function mail_layout($heading, $content)
{
    $name = htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8');
    return '<!DOCTYPE html><html><body style="margin:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#111827">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:24px 12px"><tr><td align="center">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px">'
        . '<tr><td style="padding:20px 28px;border-bottom:1px solid #e2e8f0">'
        . '<span style="display:inline-block;width:28px;height:28px;line-height:28px;text-align:center;background:#2563eb;color:#fff;border-radius:8px;font-weight:bold">&#9889;</span>'
        . ' <b style="font-size:17px;vertical-align:middle">' . $name . '</b></td></tr>'
        . '<tr><td style="padding:28px">'
        . '<h1 style="margin:0 0 12px;font-size:20px">' . $heading . '</h1>' . $content
        . '</td></tr>'
        . '<tr><td style="padding:16px 28px;border-top:1px solid #e2e8f0;color:#64748b;font-size:12px">'
        . 'Email tự động từ ' . $name . ', vui lòng không trả lời. Hỗ trợ: ' . htmlspecialchars(SHOP_HOTLINE, ENT_QUOTES, 'UTF-8')
        . '</td></tr></table></td></tr></table></body></html>';
}
