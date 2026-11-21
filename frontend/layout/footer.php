</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <a href="<?= url('index.php') ?>" class="brand brand-light">
                <span class="brand-mark"><i class="bi bi-lightning-charge-fill"></i></span><?= APP_NAME ?>
            </a>
            <p>Linh kiện, máy tính và laptop chính hãng. Tư vấn cấu hình bằng AI.</p>
        </div>
        <div>
            <h4>Mua sắm</h4>
            <a href="<?= url('products.php?category=laptop') ?>">Laptop</a>
            <a href="<?= url('products.php') ?>">Linh kiện</a>
            <a href="<?= url('build-pc.php') ?>">Build PC</a>
        </div>
        <div>
            <h4>Hỗ trợ</h4>
            <a href="<?= url('orders.php') ?>">Tra cứu đơn hàng</a>
            <a href="<?= url('ai.php') ?>">Tư vấn AI</a>
        </div>
        <div>
            <h4>Liên hệ</h4>
            <p>Hotline: <?= e(SHOP_HOTLINE) ?><br>Email: <?= e(SHOP_EMAIL) ?></p>
        </div>
    </div>
    <div class="container footer-bottom">© <?= date('Y') ?> <?= APP_NAME ?> · Đồ án môn học</div>
</footer>

<div class="qv-modal" id="quick-view" data-quick-url="<?= url('product-api.php') ?>" hidden>
    <div class="qv-backdrop" data-qv-close></div>
    <div class="qv-box" role="dialog" aria-modal="true" aria-labelledby="qv-title">
        <button type="button" class="qv-close" data-qv-close aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
        <div class="qv-body" data-qv-body></div>
    </div>
</div>

<?php if (basename($_SERVER['SCRIPT_NAME']) !== 'ai.php'): ?>
<?php $aiChat = $_SESSION['ai_chat'] ?? []; ?>
<div class="ai-float" data-ai-float>
    <section class="ai-panel" id="ai-panel" role="dialog" aria-label="Tư vấn AI" hidden>
        <header class="ai-panel-head">
            <span class="msg-avatar"><i class="bi bi-stars"></i></span>
            <div class="ai-panel-title">
                <b>Tư vấn AI</b>
                <small>Gợi ý sản phẩm đang bán tại <?= e(APP_NAME) ?></small>
            </div>
            <form method="post" action="<?= url('ai.php') ?>" data-ai-clear>
                <input type="hidden" name="action" value="clear">
                <button type="submit" class="ai-icon-btn" title="Cuộc trò chuyện mới" aria-label="Cuộc trò chuyện mới"><i class="bi bi-arrow-counterclockwise"></i></button>
            </form>
            <a href="<?= url('ai.php') ?>" class="ai-icon-btn" title="Mở trang Tư vấn AI" aria-label="Mở trang Tư vấn AI"><i class="bi bi-arrows-angle-expand"></i></a>
            <button type="button" class="ai-icon-btn" data-ai-close title="Thu nhỏ" aria-label="Thu nhỏ"><i class="bi bi-x-lg"></i></button>
        </header>

        <div class="ai-panel-body" data-ai-body>
            <div class="msg msg-ai">
                <span class="msg-avatar"><i class="bi bi-stars"></i></span>
                <div class="msg-bubble">Xin chào! Bạn cần tìm laptop, linh kiện hay build PC? Cho mình biết nhu cầu và ngân sách nhé.</div>
            </div>
            <?php foreach ($aiChat as $m): ?>
                <?= ai_msg_html($m) ?>
            <?php endforeach; ?>
        </div>

        <div class="ai-panel-chips" data-ai-chips <?= $aiChat ? 'hidden' : '' ?>>
            <?php foreach (['Laptop gaming dưới 25 triệu', 'Build PC chơi game 20 triệu', 'RAM DDR4 hay DDR5?'] as $s): ?>
                <button type="button" class="chip" data-ai-ask><?= e($s) ?></button>
            <?php endforeach; ?>
        </div>

        <form method="post" action="<?= url('ai.php') ?>" class="ai-panel-input" data-ai-form>
            <input type="hidden" name="action" value="ask">
            <textarea name="question" rows="1" class="textarea" placeholder="Nhập câu hỏi..." maxlength="500" required></textarea>
            <button type="submit" class="btn btn-primary" aria-label="Gửi"><i class="bi bi-send"></i></button>
        </form>
    </section>

    <button type="button" class="ai-bubble" data-ai-toggle aria-controls="ai-panel" aria-expanded="false" aria-label="Mở Tư vấn AI">
        <i class="bi bi-stars"></i>
    </button>
</div>
<?php endif; ?>

<script src="<?= url('frontend/assets/js/main.js') ?>"></script>
</body>
</html>
