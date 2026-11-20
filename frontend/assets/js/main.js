// Mở/đóng menu thả xuống (tài khoản)
document.addEventListener('click', function (ev) {
    var trigger = ev.target.closest('[data-dropdown]');
    document.querySelectorAll('.dropdown.open').forEach(function (d) {
        if (!trigger || d !== trigger.parentElement) d.classList.remove('open');
    });
    if (trigger) trigger.parentElement.classList.toggle('open');

    // Mở/đóng sidebar admin trên điện thoại
    if (ev.target.closest('[data-toggle-sidebar]')) {
        document.body.classList.toggle('sidebar-open');
    }
});

// Nút đóng thông báo + tự ẩn sau 6 giây
document.querySelectorAll('.alert').forEach(function (alert) {
    var btn = alert.querySelector('.alert-close');
    if (btn) btn.addEventListener('click', function () { alert.remove(); });
    if (btn) setTimeout(function () { alert.remove(); }, 6000);
});

// Hỏi lại trước khi thực hiện: <form data-confirm="..."> hoặc <a data-confirm="...">
document.addEventListener('click', function (ev) {
    var el = ev.target.closest('a[data-confirm]');
    if (el && !confirm(el.getAttribute('data-confirm'))) ev.preventDefault();
});
document.addEventListener('submit', function (ev) {
    var form = ev.target;
    var msg = form.getAttribute('data-confirm');
    if (msg && !confirm(msg)) { ev.preventDefault(); return; }

    // Form chờ lâu (AI): khóa nút và hiện chữ đang xử lý
    var loading = form.getAttribute('data-loading');
    if (loading) {
        var btn = form.querySelector('button');
        if (btn) { btn.disabled = true; btn.textContent = loading; }
    }
});

// Ô chọn tự gửi form khi thay đổi: <select data-autosubmit>
document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () {
        if (el.value === '' && el.getAttribute('data-autosubmit') === 'nonempty') return;
        el.form.submit();
    });
});

// Bộ ảnh sản phẩm: bấm ảnh nhỏ để đổi ảnh lớn
document.querySelectorAll('[data-gallery]').forEach(function (box) {
    var main = box.querySelector('[data-gallery-main]');
    box.querySelectorAll('[data-gallery-thumb]').forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            main.src = thumb.src;
            box.querySelectorAll('[data-gallery-thumb]').forEach(function (t) { t.classList.remove('active'); });
            thumb.classList.add('active');
        });
    });
});

// Form sản phẩm (admin): chỉ hiện ô thông số của danh mục đang chọn
var specSwitch = document.querySelector('[data-spec-switch]');
if (specSwitch) {
    var showSpecs = function () {
        var found = false;
        document.querySelectorAll('[data-spec-group]').forEach(function (g) {
            var on = g.getAttribute('data-spec-group') === specSwitch.value;
            g.classList.toggle('hidden', !on);
            g.querySelectorAll('input').forEach(function (i) { i.disabled = !on; });
            if (on) found = true;
        });
        var empty = document.querySelector('[data-spec-empty]');
        if (empty) empty.classList.toggle('hidden', found);
    };
    specSwitch.addEventListener('change', showSpecs);
    showSpecs();
}

// Khung chat AI: cuộn xuống tin nhắn mới nhất
var chat = document.querySelector('.chat-box');
if (chat) chat.scrollTop = chat.scrollHeight;

// Nút hiện / ẩn mật khẩu (đăng nhập, quên mật khẩu)
document.querySelectorAll('.pw-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var input = btn.parentNode.querySelector('input');
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-label', show ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
        btn.querySelector('i').className = 'bi ' + (show ? 'bi-eye-slash' : 'bi-eye');
        input.focus();
    });
});

// Đặt mật khẩu mới: đánh dấu điều kiện đạt và báo khớp / không khớp ngay khi gõ
var pwForm = document.querySelector('[data-pw-form]');
if (pwForm) {
    var pwNew = pwForm.querySelector('[data-pw-new]');
    var pwConfirm = pwForm.querySelector('[data-pw-confirm]');
    var pwMatch = pwForm.querySelector('[data-pw-match]');
    var checkPw = function () {
        var v = pwNew.value;
        var rules = { len: v.length >= 6, mix: /[a-zA-Z]/.test(v) && /\d/.test(v) };
        Object.keys(rules).forEach(function (k) {
            var li = pwForm.querySelector('[data-rule="' + k + '"]');
            li.classList.toggle('is-ok', rules[k]);
            li.querySelector('i').className = 'bi ' + (rules[k] ? 'bi-check-circle-fill' : 'bi-check-circle');
        });
        if (pwConfirm.value === '') { pwMatch.hidden = true; return; }
        var same = pwConfirm.value === v;
        pwMatch.hidden = false;
        pwMatch.textContent = same ? 'Mật khẩu đã khớp' : 'Mật khẩu nhập lại chưa khớp';
        pwMatch.classList.toggle('is-ok', same);
    };
    pwNew.addEventListener('input', checkPw);
    pwConfirm.addEventListener('input', checkPw);
    checkPw();
}

// Quên mật khẩu: ô mã chỉ nhận số (dán mã cũng được), đếm ngược nút "Gửi lại mã"
var otp = document.querySelector('[data-otp]');
if (otp) {
    otp.addEventListener('input', function () {
        otp.value = otp.value.replace(/\D/g, '').slice(0, 6);
        if (otp.value.length === 6 && otp.form.requestSubmit) otp.form.requestSubmit();
    });
}
var resend = document.querySelector('[data-wait]');
if (resend) {
    var left = parseInt(resend.getAttribute('data-wait'), 10) || 0;
    var tick = function () {
        if (left <= 0) { resend.disabled = false; resend.textContent = 'Gửi lại mã'; return; }
        resend.textContent = 'Gửi lại mã (' + left + 's)';
        left--; setTimeout(tick, 1000);
    };
    tick();
}

// Khung chat AI nổi: mở/đóng, gửi câu hỏi không tải lại trang
var aiFloat = document.querySelector('[data-ai-float]');
if (aiFloat) {
    var aiPanel = aiFloat.querySelector('.ai-panel');
    var aiToggle = aiFloat.querySelector('[data-ai-toggle]');
    var aiBody = aiFloat.querySelector('[data-ai-body]');
    var aiForm = aiFloat.querySelector('[data-ai-form]');
    var aiInput = aiForm.querySelector('textarea');
    var aiChips = aiFloat.querySelector('[data-ai-chips]');
    var aiBusy = false;
    var aiRemember = function (open) { try { sessionStorage.setItem('aiOpen', open ? '1' : ''); } catch (e) {} };
    var aiScroll = function () { aiBody.scrollTop = aiBody.scrollHeight; };
    var aiOpen = function (open, focus) {
        aiPanel.hidden = !open;
        aiFloat.classList.toggle('is-open', open);
        aiToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        aiToggle.setAttribute('aria-label', open ? 'Đóng Tư vấn AI' : 'Mở Tư vấn AI');
        aiToggle.querySelector('i').className = 'bi ' + (open ? 'bi-x-lg' : 'bi-stars');
        aiRemember(open);
        if (open) { aiScroll(); if (focus) aiInput.focus(); }
    };
    aiToggle.addEventListener('click', function () { aiOpen(aiPanel.hidden, true); });
    aiFloat.querySelector('[data-ai-close]').addEventListener('click', function () { aiOpen(false); aiToggle.focus(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !aiPanel.hidden) { aiOpen(false); aiToggle.focus(); } });
    try { if (sessionStorage.getItem('aiOpen') === '1') aiOpen(true, false); } catch (e) {}

    var aiPost = function (form, extra) {
        var data = new FormData(form);
        data.append('ajax', '1');
        Object.keys(extra || {}).forEach(function (k) { data.set(k, extra[k]); });
        return fetch(form.getAttribute('action'), { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    };

    var aiAsk = function (question) {
        question = question.trim();
        if (!question || aiBusy) return;
        aiBusy = true;
        aiChips.hidden = true;
        var user = document.createElement('div');
        user.className = 'msg msg-user';
        user.innerHTML = '<div class="msg-bubble"></div>';
        user.firstChild.textContent = question;
        var wait = document.createElement('div');
        wait.className = 'msg msg-ai';
        wait.innerHTML = '<span class="msg-avatar"><i class="bi bi-stars"></i></span><div class="msg-bubble"><span class="ai-typing"><span></span><span></span><span></span></span></div>';
        aiBody.appendChild(user);
        aiBody.appendChild(wait);
        aiInput.value = '';
        aiScroll();
        aiPost(aiForm, { question: question }).then(function (res) {
            user.remove();
            wait.remove();
            aiBody.insertAdjacentHTML('beforeend', res.html);
        }).catch(function () {
            wait.querySelector('.msg-bubble').className = 'msg-bubble msg-error';
            wait.querySelector('.msg-bubble').textContent = 'Không gửi được câu hỏi. Vui lòng tải lại trang và thử lại.';
        }).then(function () { aiBusy = false; aiScroll(); aiInput.focus(); });
    };

    aiForm.addEventListener('submit', function (e) { e.preventDefault(); aiAsk(aiInput.value); });
    aiInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); aiAsk(aiInput.value); }
    });
    aiFloat.querySelectorAll('[data-ai-ask]').forEach(function (b) {
        b.addEventListener('click', function () { aiAsk(b.textContent); });
    });
    aiFloat.querySelector('[data-ai-clear]').addEventListener('submit', function (e) {
        e.preventDefault();
        aiPost(e.target).then(function () {
            aiBody.querySelectorAll('.msg').forEach(function (m, i) { if (i > 0) m.remove(); });
            aiChips.hidden = false;
        });
    });
}
