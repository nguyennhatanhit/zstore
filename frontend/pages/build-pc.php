<?php
/**
 * BUILD PC
 *   [CN 13] Kiểm tra khả năng tương thích giữa các linh kiện
 *   [AI 2]  Kiểm tra và tối ưu cấu hình PC bằng AI
 */
require '../../config.php';

$slots = build_slots();
$build = isset($_SESSION['build']) ? $_SESSION['build'] : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_str('action', '');
    $slot   = post_str('slot', '');

    if ($action === 'set' && isset($slots[$slot])) {
        $productId = (int) post_str('product_id', '0');
        // Chỉ nhận sản phẩm đúng loại linh kiện
        $ok = db_value("SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id
                        WHERE p.id = ? AND c.code = ? AND p.status = 'active'", [$productId, $slot]);
        if ($ok) {
            $_SESSION['build'][$slot] = $productId;
        } elseif (!$productId) {
            unset($_SESSION['build'][$slot]);
        }
        unset($_SESSION['build_ai']);
    } elseif ($action === 'remove') {
        unset($_SESSION['build'][$slot], $_SESSION['build_ai']);
    } elseif ($action === 'clear') {
        unset($_SESSION['build'], $_SESSION['build_ai']);
        set_flash('success', 'Đã xóa cấu hình.');
    } elseif ($action === 'add_all' && $build) {
        $messages = [];
        foreach ($build as $productId) {
            if ($msg = cart_add($productId, 1)) {
                $messages[] = $msg;
            }
        }
        set_flash($messages ? 'warning' : 'success', $messages ? implode(' ', $messages) : 'Đã thêm toàn bộ cấu hình vào giỏ hàng.');
        redirect('cart.php');
    } elseif ($action === 'optimize') {
        $budget  = (int) post_str('budget', '0');
        $purpose = mb_substr(post_str('purpose'), 0, 200);
        $quota   = ai_quota_error();
        $result  = $quota ? ['error' => $quota] : ai_optimize_build($build, check_compatibility($build), $budget, $purpose);
        $_SESSION['build_ai'] = $result + ['budget' => $budget, 'purpose' => $purpose];
    }
    // Chọn / bỏ linh kiện bằng JavaScript: trả về phần danh sách và phần kiểm tra mới
    if (post_str('ajax') === '1') {
        $build = isset($_SESSION['build']) ? $_SESSION['build'] : [];
        ['options' => $options, 'selected' => $selected, 'total' => $total, 'compat' => $compat, 'keySpecs' => $keySpecs] = build_view($build);
        ob_start();
        require '../layout/build_rows.php';
        $rowsHtml = csrf_inject(ob_get_clean());
        ob_start();
        require '../layout/build_check.php';
        $checkHtml = ob_get_clean();
        json_response(['ok' => true, 'count' => count($selected), 'rows_html' => $rowsHtml, 'check_html' => $checkHtml]);
    }
    redirect('build-pc.php');
}

['options' => $options, 'selected' => $selected, 'total' => $total, 'compat' => $compat, 'keySpecs' => $keySpecs] = build_view($build);
$aiResult = isset($_SESSION['build_ai']) ? $_SESSION['build_ai'] : null;

$title = 'Build PC';
require '../layout/header.php';
?>

<section class="container section">
    <div class="page-heading page-heading-row">
        <div>
            <h1>Build PC</h1>
            <p class="text-muted">Chọn từng linh kiện, hệ thống tự kiểm tra tương thích và AI giúp tối ưu cấu hình.</p>
        </div>
        <form method="post" data-confirm="Xóa toàn bộ cấu hình đang chọn?" id="build-clear" <?= $build ? '' : 'hidden' ?>>
            <input type="hidden" name="action" value="clear">
            <button class="btn btn-outline"><i class="bi bi-arrow-counterclockwise"></i> Làm lại</button>
        </form>
    </div>

    <div class="cart-layout">
        <div class="card no-pad" id="build-rows">
            <?php require '../layout/build_rows.php'; ?>
        </div>

        <aside class="stack">
            <section class="card summary">
                <h2 class="card-title">Kiểm tra tương thích</h2>
                <div class="summary-rows" id="build-check">
                    <?php require '../layout/build_check.php'; ?>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="add_all">
                    <button class="btn btn-primary btn-block btn-lg" id="build-add-all" <?= $build ? '' : 'disabled' ?>><i class="bi bi-cart-plus"></i> Thêm cấu hình vào giỏ</button>
                </form>
            </section>

            <section class="card">
                <h2 class="card-title"><i class="bi bi-stars text-blue"></i> AI tối ưu cấu hình</h2>
                <?php if (!ai_enabled()): ?>
                    <div class="notice notice-warning"><i class="bi bi-info-circle"></i> Chức năng AI chưa được cấu hình khóa API.</div>
                <?php endif; ?>
                <form method="post" class="form">
                    <input type="hidden" name="action" value="optimize">
                    <div class="form-group">
                        <label for="purpose">Nhu cầu sử dụng</label>
                        <select id="purpose" name="purpose" class="select">
                            <?php foreach (['Chơi game', 'Đồ họa, dựng video', 'Lập trình', 'Văn phòng, học tập', 'Livestream'] as $opt): ?>
                                <option <?= ($aiResult && $aiResult['purpose'] === $opt) ? 'selected' : '' ?>><?= $opt ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="budget">Ngân sách (₫)</label>
                        <input id="budget" type="number" name="budget" class="input" min="0" step="1000000"
                               value="<?= $aiResult ? (int) $aiResult['budget'] : '' ?>" placeholder="VD: 25000000">
                    </div>
                    <button class="btn btn-soft btn-block" <?= ai_enabled() ? '' : 'disabled' ?>><i class="bi bi-magic"></i> Phân tích &amp; tối ưu</button>
                </form>
                <?php if ($aiResult): ?><div id="build-ai-result">
                    <?php if (isset($aiResult['error'])): ?>
                        <div class="notice notice-danger mt-8"><?= e($aiResult['error']) ?></div>
                    <?php else: ?>
                        <div class="ai-answer mt-8"><?= ai_format($aiResult['text']) ?></div>
                    <?php endif; ?>
                </div><?php endif; ?>
            </section>
        </aside>
    </div>
</section>

<?php require '../layout/footer.php'; ?>
