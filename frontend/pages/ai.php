<?php
/** TƯ VẤN AI - [AI 1] AI tư vấn sản phẩm theo nhu cầu và ngân sách */
require '../../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_str('action', 'ask');

    $added = null;
    if ($action === 'clear') {
        unset($_SESSION['ai_chat']);
    } else {
        $question = mb_substr(post_str('question'), 0, 500);
        if ($question !== '') {
            $history = isset($_SESSION['ai_chat']) ? $_SESSION['ai_chat'] : [];
            $quota  = ai_quota_error();
            $result = $quota ? ['error' => $quota] : ai_advise($question, $history);
            $_SESSION['ai_chat'][] = isset($result['text'])
                ? ['q' => $question, 'a' => $result['text']]
                : ['q' => $question, 'error' => $result['error']];
            $_SESSION['ai_chat'] = array_slice($_SESSION['ai_chat'], -20);
            $added = end($_SESSION['ai_chat']);
        }
    }
    // Khung chat nổi gửi bằng JavaScript: trả về tin nhắn mới thay vì tải lại trang
    if (post_str('ajax') === '1') {
        json_response(['ok' => true, 'html' => $added ? ai_msg_html($added) : '']);
    }
    redirect('ai.php#chat-end');
}

$chat = isset($_SESSION['ai_chat']) ? $_SESSION['ai_chat'] : [];
$suggestions = [
    'Tư vấn laptop gaming dưới 25 triệu',
    'Build PC chơi game tầm 20 triệu cần những gì?',
    'Nên chọn RAM DDR4 hay DDR5?',
    'Laptop mỏng nhẹ cho sinh viên',
];

$title = 'Tư vấn AI';
require '../layout/header.php';
?>

<section class="container section">
    <div class="chat-wrap">
        <div class="page-heading page-heading-row">
            <div>
                <h1><i class="bi bi-stars text-blue"></i> Tư vấn AI</h1>
                <p class="text-muted">Hỏi về nhu cầu, ngân sách, AI sẽ gợi ý sản phẩm đang bán tại <?= APP_NAME ?>.</p>
            </div>
            <?php if ($chat): ?>
                <form method="post">
                    <input type="hidden" name="action" value="clear">
                    <button class="btn btn-outline"><i class="bi bi-trash"></i> Cuộc trò chuyện mới</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (!ai_enabled()): ?>
            <div class="notice notice-warning"><i class="bi bi-info-circle"></i> Chức năng AI chưa được cấu hình khóa API. Câu hỏi sẽ chưa có câu trả lời.</div>
        <?php endif; ?>

        <div class="card chat-box">
            <div class="msg msg-ai">
                <span class="msg-avatar"><i class="bi bi-stars"></i></span>
                <div class="msg-bubble">Xin chào! Mình là trợ lý tư vấn của <?= APP_NAME ?>. Bạn cần tìm laptop, linh kiện hay build PC? Hãy cho mình biết nhu cầu và ngân sách nhé.</div>
            </div>

            <?php foreach ($chat as $m): ?>
                <div class="msg msg-user"><div class="msg-bubble"><?= nl2br(e($m['q'])) ?></div></div>
                <div class="msg msg-ai">
                    <span class="msg-avatar"><i class="bi bi-stars"></i></span>
                    <div class="msg-bubble <?= isset($m['error']) ? 'msg-error' : '' ?>">
                        <?= isset($m['error']) ? e($m['error']) : ai_format($m['a']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <div id="chat-end"></div>
        </div>

        <?php if (!$chat): ?>
            <div class="chips">
                <?php foreach ($suggestions as $s): ?>
                    <form method="post"><input type="hidden" name="question" value="<?= e($s) ?>"><button class="chip"><?= e($s) ?></button></form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="chat-input card" data-loading="AI đang trả lời...">
            <input type="hidden" name="action" value="ask">
            <textarea name="question" rows="2" class="textarea" placeholder="Nhập câu hỏi, VD: Laptop lập trình tầm 20 triệu?" required maxlength="500"></textarea>
            <button class="btn btn-primary"><i class="bi bi-send"></i> Gửi</button>
        </form>
    </div>
</section>

<?php require '../layout/footer.php'; ?>
