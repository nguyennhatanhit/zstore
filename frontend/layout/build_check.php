<?php /** Phần kiểm tra tương thích + tổng tiền của Build PC (trang build-pc và AJAX dùng chung) */ ?>
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
