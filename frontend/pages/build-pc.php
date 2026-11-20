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
    redirect('build-pc.php');
}

// Danh sách sản phẩm cho từng vị trí (còn hàng)
$options = [];
foreach ($slots as $code => $name) {
    $options[$code] = db_all(product_sql() . " WHERE c.code = ? AND p.status = 'active' AND p.stock_quantity > 0 ORDER BY p.price", [$code]);
}

// Sản phẩm đã chọn
$selected = [];
$total = 0;
foreach ($build as $code => $productId) {
    $p = db_one(product_sql() . " WHERE p.id = ?", [$productId]);
    if ($p) {
        $p['sale_price'] = sale_price($p);
        $p['specs'] = product_spec_map($p['id']);
        $selected[$code] = $p;
        $total += $p['sale_price'];
    }
}
$compat = check_compatibility($build);
$aiResult = isset($_SESSION['build_ai']) ? $_SESSION['build_ai'] : null;

// Thông số chính hiển thị dưới tên linh kiện
$keySpecs = ['socket', 'ram_type', 'form_factor', 'capacity', 'tdp', 'wattage', 'vram', 'length', 'cores', 'speed'];

$title = 'Build PC';
require '../layout/header.php';
?>

<section class="container section">
    <div class="page-heading page-heading-row">
        <div>
            <h1>Build PC</h1>
            <p class="text-muted">Chọn từng linh kiện, hệ thống tự kiểm tra tương thích và AI giúp tối ưu cấu hình.</p>
        </div>
        <?php if ($build): ?>
            <form method="post" data-confirm="Xóa toàn bộ cấu hình đang chọn?">
                <input type="hidden" name="action" value="clear">
                <button class="btn btn-outline"><i class="bi bi-arrow-counterclockwise"></i> Làm lại</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="cart-layout">
        <div class="card no-pad">
            <?php foreach ($slots as $code => $name): $p = isset($selected[$code]) ? $selected[$code] : null; ?>
                <div class="build-row <?= $p ? 'chosen' : '' ?>">
                    <div class="build-slot"><?= e($name) ?></div>
                    <?php if ($p): ?>
                        <div class="build-product">
                            <img src="<?= img_url($p['image']) ?>" alt="" onerror="this.onerror=null;this.src='<?= img_url('') ?>'">
                            <div>
                                <a href="<?= url('product.php?id=' . $p['id']) ?>"><b><?= e($p['name']) ?></b></a>
                                <div class="cell-sub">
                                    <?php foreach ($keySpecs as $k) { if (isset($p['specs'][$k])) echo '<span class="tag">' . e($k) . ': ' . e($p['specs'][$k]) . '</span> '; } ?>
                                </div>
                            </div>
                            <b class="build-price"><?= money($p['sale_price']) ?></b>
                            <form method="post">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="slot" value="<?= $code ?>">
                                <button class="icon-btn" title="Bỏ chọn"><i class="bi bi-x-lg"></i></button>
                            </form>
                        </div>
                    <?php else: ?>
                        <form method="post" class="build-select">
                            <input type="hidden" name="action" value="set">
                            <input type="hidden" name="slot" value="<?= $code ?>">
                            <select name="product_id" class="select" data-autosubmit="nonempty" <?= $options[$code] ? '' : 'disabled' ?>>
                                <option value=""><?= $options[$code] ? '+ Chọn ' . e(mb_strtolower($name)) : 'Chưa có sản phẩm còn hàng' ?></option>
                                <?php foreach ($options[$code] as $o): ?>
                                    <option value="<?= $o['id'] ?>"><?= e($o['name']) ?> — <?= money(sale_price($o)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <aside class="stack">
            <section class="card summary">
                <h2 class="card-title">Kiểm tra tương thích</h2>
                <?php if (!$build): ?>
                    <p class="text-muted">Chọn linh kiện để bắt đầu kiểm tra.</p>
                <?php else: ?>
                    <?php if ($compat['ok']): ?>
                        <div class="notice notice-success"><i class="bi bi-check-circle"></i> Các linh kiện đã chọn tương thích với nhau.</div>
                    <?php endif; ?>
                    <?php foreach ($compat['errors'] as $msg): ?>
                        <div class="notice notice-danger"><i class="bi bi-x-circle"></i> <?= e($msg) ?></div>
                    <?php endforeach; ?>
                    <?php foreach ($compat['warnings'] as $msg): ?>
                        <div class="notice notice-warning"><i class="bi bi-exclamation-triangle"></i> <?= e($msg) ?></div>
                    <?php endforeach; ?>
                    <div class="summary-row"><span>Công suất ước tính</span><b><?= $compat['total_power'] ?>W</b></div>
                    <?php if ($compat['psu_watt']): ?>
                        <div class="power-bar"><span style="width: <?= min(100, round($compat['total_power'] / $compat['psu_watt'] * 100)) ?>%"></span></div>
                        <div class="form-hint">Nguồn <?= $compat['psu_watt'] ?>W · dùng <?= round($compat['total_power'] / $compat['psu_watt'] * 100) ?>%</div>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="summary-row summary-total"><span>Tổng tiền (<?= count($selected) ?> linh kiện)</span><b><?= money($total) ?></b></div>
                <form method="post">
                    <input type="hidden" name="action" value="add_all">
                    <button class="btn btn-primary btn-block btn-lg" <?= $build ? '' : 'disabled' ?>><i class="bi bi-cart-plus"></i> Thêm cấu hình vào giỏ</button>
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
                <?php if ($aiResult): ?>
                    <?php if (isset($aiResult['error'])): ?>
                        <div class="notice notice-danger mt-8"><?= e($aiResult['error']) ?></div>
                    <?php else: ?>
                        <div class="ai-answer mt-8"><?= ai_format($aiResult['text']) ?></div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </aside>
    </div>
</section>

<?php require '../layout/footer.php'; ?>
