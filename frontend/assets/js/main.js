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

// Giỏ hàng AJAX: gửi tới cart-api.php, nhận JSON, cập nhật trang không tải lại
var cartApi = document.querySelector('meta[name="cart-api"]');
if (cartApi) {
    var cartUrl = cartApi.content;

    var toast = function (type, message, link, linkText) {
        var stack = document.querySelector('.toast-stack');
        if (!stack) { stack = document.createElement('div'); stack.className = 'toast-stack'; stack.setAttribute('aria-live', 'polite'); document.body.appendChild(stack); }
        var el = document.createElement('div');
        el.className = 'alert alert-' + type;
        el.innerHTML = '<i class="bi ' + (type === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle') + '"></i><span></span>';
        el.querySelector('span').textContent = message;
        if (link) { var a = document.createElement('a'); a.href = link; a.textContent = linkText || 'Xem giỏ'; el.appendChild(a); }
        stack.appendChild(el);
        setTimeout(function () { el.remove(); }, 3500);
    };

    var setBadge = function (n) {
        var badge = document.getElementById('cart-count');
        if (!badge) return;
        badge.textContent = n;
        badge.hidden = n <= 0;
        badge.classList.remove('is-bump'); void badge.offsetWidth; badge.classList.add('is-bump');
    };

    var cartSend = function (data) {
        return fetch(cartUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    };

    // Nút "Thêm vào giỏ" (thẻ sản phẩm, trang chi tiết, so sánh). Nút "Mua ngay" vẫn gửi form bình thường.
    // Gắn trên document (ủy quyền sự kiện) để bắt cả form trong khung Xem nhanh được tạo sau bằng AJAX
    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!form.matches('form.add-to-cart')) return;
        if (ev.submitter && ev.submitter.name === 'buy_now') return;
        ev.preventDefault();
        var btn = ev.submitter || form.querySelector('button');
        if (btn.disabled) return;
        btn.disabled = true;
        cartSend(new FormData(form)).then(function (res) {
            setBadge(res.cart_count);
            toast(res.ok ? 'success' : 'warning', res.message, res.ok ? form.getAttribute('action') : null);
        }).catch(function () {
            form.submit();
        }).then(function () { btn.disabled = false; });
    });

    // Trang giỏ: đổi số lượng, xóa sản phẩm
    var summary = document.getElementById('cart-summary');
    if (summary) {
        var token = document.querySelector('#cart-form input[name="_csrf"]').value;
        var applyCart = function (id, res) {
            var row = document.querySelector('[data-cart-row="' + id + '"]');
            setBadge(res.cart_count);
            if (res.item_count === 0) { location.reload(); return; }
            if (res.coupon_error) { location.reload(); return; }
            if (row) {
                row.classList.remove('is-updating');
                if (res.quantity === 0) row.remove();
                else {
                    row.querySelector('[data-cart-qty]').value = res.quantity;
                    row.querySelector('[data-line-total]').textContent = res.line_total;
                }
            }
            summary.innerHTML = res.summary_html;
            var count = document.querySelector('[data-cart-count-text]');
            if (count) count.textContent = res.item_count;
            if (!res.ok || res.quantity === 0) toast(res.ok ? 'success' : 'warning', res.message);
        };
        var update = function (id, action, qty) {
            var data = new FormData();
            data.append('_csrf', token);
            data.append('action', action);
            data.append('product_id', id);
            if (qty !== undefined) data.append('quantity', qty);
            var row = document.querySelector('[data-cart-row="' + id + '"]');
            if (row) row.classList.add('is-updating');
            cartSend(data).then(function (res) { applyCart(id, res); })
                .catch(function () { location.reload(); });
        };

        var timers = {};
        document.querySelectorAll('[data-cart-qty]').forEach(function (input) {
            var id = input.getAttribute('data-cart-qty');
            input.addEventListener('input', function () {
                clearTimeout(timers[id]);
                if (input.value === '') return;
                timers[id] = setTimeout(function () { update(id, 'update', input.value); }, 500);
            });
            input.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') ev.preventDefault(); });
        });
        // Mã giảm giá: áp dụng / bỏ mã, ô mã và tổng tiền đổi ngay
        var couponBox = document.getElementById('cart-coupon');
        couponBox.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var form = ev.target;
            var btn = form.querySelector('button');
            btn.disabled = true;
            cartSend(new FormData(form)).then(function (res) {
                if (res.ok) {
                    couponBox.innerHTML = res.coupon_html;
                    summary.innerHTML = res.summary_html;
                }
                toast(res.ok ? 'success' : 'error', res.message);
            }).catch(function () { form.submit(); })
              .then(function () { btn.disabled = false; });
        });

        document.querySelectorAll('form.cart-remove').forEach(function (form) {
            form.addEventListener('submit', function (ev) {
                ev.preventDefault();
                update(form.querySelector('[name="product_id"]').value, 'remove');
            });
        });
    }
}

// Tìm kiếm gợi ý (AJAX): gõ từ 2 ký tự, hiện tối đa 6 sản phẩm dưới ô tìm kiếm
var searchForm = document.querySelector('form[data-search]');
if (searchForm) {
    var sInput = searchForm.querySelector('input[name="q"]');
    var sBox = searchForm.querySelector('.search-suggest');
    var sTimer, sActive = -1, sLast = '';

    var esc = function (t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; };
    var mark = function (text, q) {
        var i = text.toLowerCase().indexOf(q.toLowerCase());
        if (i < 0) return esc(text);
        return esc(text.slice(0, i)) + '<mark>' + esc(text.slice(i, i + q.length)) + '</mark>' + esc(text.slice(i + q.length));
    };
    var sShow = function (open) { sBox.hidden = !open; sInput.setAttribute('aria-expanded', open ? 'true' : 'false'); sActive = -1; };
    var sLinks = function () { return sBox.querySelectorAll('a'); };

    var render = function (res) {
        if (res.q !== sInput.value.trim()) return;         // bỏ kết quả cũ khi người dùng đã gõ tiếp
        if (!res.items.length) {
            sBox.innerHTML = '<div class="suggest-empty">Không tìm thấy sản phẩm cho "' + esc(res.q) + '"</div>';
        } else {
            sBox.innerHTML = res.items.map(function (p) {
                return '<a class="suggest-item" role="option" href="' + p.url + '">'
                    + '<img src="' + p.image + '" alt="">'
                    + '<span class="suggest-text"><b>' + mark(p.name, res.q) + '</b><small>' + esc(p.category) + (p.in_stock ? '' : ' · Hết hàng') + '</small></span>'
                    + '<span class="suggest-price">' + p.price + '</span></a>';
            }).join('') + '<a class="suggest-all" href="' + res.all_url + '">Xem tất cả ' + res.total + ' kết quả</a>';
        }
        sShow(true);
    };

    sInput.addEventListener('input', function () {
        var q = sInput.value.trim();
        clearTimeout(sTimer);
        if (q.length < 2) { sShow(false); sLast = ''; return; }
        sTimer = setTimeout(function () {
            if (q === sLast) { sShow(true); return; }
            sLast = q;
            fetch(searchForm.getAttribute('data-search') + '?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(render)
                .catch(function () { sShow(false); });
        }, 250);
    });

    // Phím mũi tên lên/xuống để chọn, Enter để mở, Esc để đóng
    sInput.addEventListener('keydown', function (ev) {
        var links = sLinks();
        if (sBox.hidden || !links.length) return;
        if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
            ev.preventDefault();
            sActive = (sActive + (ev.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
            links.forEach(function (a, i) { a.classList.toggle('is-active', i === sActive); });
        } else if (ev.key === 'Enter' && sActive >= 0) {
            ev.preventDefault();
            location.href = links[sActive].href;
        } else if (ev.key === 'Escape') {
            sShow(false);
        }
    });
    sInput.addEventListener('focus', function () { if (sBox.innerHTML && sInput.value.trim().length >= 2) sShow(true); });
    document.addEventListener('click', function (ev) { if (!searchForm.contains(ev.target)) sShow(false); });
}

// Build PC (AJAX): chọn / bỏ linh kiện, kiểm tra tương thích và tổng tiền cập nhật ngay
var buildRows = document.getElementById('build-rows');
if (buildRows) {
    var buildCheck = document.getElementById('build-check');
    var buildSend = function (form) {
        var data = new FormData(form);
        data.append('ajax', '1');
        buildRows.classList.add('is-loading');
        fetch(location.pathname, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                buildRows.innerHTML = res.rows_html;
                buildCheck.innerHTML = res.check_html;
                document.getElementById('build-clear').hidden = res.count === 0;
                document.getElementById('build-add-all').disabled = res.count === 0;
                var ai = document.getElementById('build-ai-result');   // cấu hình đổi thì kết quả AI cũ không còn đúng
                if (ai) ai.remove();
            })
            .catch(function () { form.submit(); })
            .then(function () { buildRows.classList.remove('is-loading'); });
    };
    // Dùng "ủy quyền sự kiện" trên khung cha vì các dòng được thay mới sau mỗi lần chọn
    buildRows.addEventListener('change', function (ev) {
        var sel = ev.target.closest('[data-build-select]');
        if (sel && sel.value !== '') buildSend(sel.form);
    });
    buildRows.addEventListener('submit', function (ev) {
        ev.preventDefault();
        buildSend(ev.target);
    });
}

// So sánh (AJAX): nút "Thêm vào so sánh / Bỏ khỏi so sánh" ở trang chi tiết sản phẩm
document.querySelectorAll('form.compare-toggle').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var btn = form.querySelector('button');
        var data = new FormData(form);
        data.append('ajax', '1');
        btn.disabled = true;
        fetch(form.getAttribute('action'), { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var badge = document.getElementById('compare-count');
                if (badge) { badge.textContent = res.compare_count; badge.hidden = res.compare_count === 0; }
                form.querySelector('[name="action"]').value = res.in_compare ? 'remove' : 'add';
                btn.querySelector('span').textContent = res.in_compare ? 'Bỏ khỏi so sánh' : 'Thêm vào so sánh';
                if (window.toast) toast(res.ok ? 'success' : 'warning', res.message, res.ok && res.in_compare ? form.getAttribute('action') : null, 'Xem so sánh');
            })
            .catch(function () { form.submit(); })
            .then(function () { btn.disabled = false; });
    });
});

// Xem nhanh sản phẩm (AJAX): bấm biểu tượng con mắt trên thẻ sản phẩm, xem thông tin chính không rời trang
var qv = document.getElementById('quick-view');
if (qv) {
    var qvBody = qv.querySelector('[data-qv-body]');
    var qvLast = null;
    var qvClose = function () {
        qv.hidden = true;
        document.body.style.overflow = '';
        if (qvLast) qvLast.focus();
    };
    var qvOpen = function (id, trigger) {
        qvLast = trigger;
        qv.hidden = false;
        document.body.style.overflow = 'hidden';
        qvBody.innerHTML = '<div class="qv qv-loading"><div class="qv-img"><span class="skeleton"></span></div><div class="qv-info">'
            + '<span class="skeleton" style="width:40%"></span><span class="skeleton" style="height:26px"></span>'
            + '<span class="skeleton" style="width:55%;height:30px"></span><span class="skeleton"></span><span class="skeleton" style="width:80%"></span></div></div>';
        qv.querySelector('.qv-close').focus();
        fetch(qv.getAttribute('data-quick-url') + '?id=' + encodeURIComponent(id), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) throw new Error(res.message);
                qvBody.innerHTML = res.html;
            })
            .catch(function (err) {
                qvBody.innerHTML = '<div class="qv-error"><h2 id="qv-title">Không xem được sản phẩm</h2><p class="text-muted"></p></div>';
                qvBody.querySelector('p').textContent = err.message || 'Lỗi kết nối, vui lòng thử lại.';
            });
    };
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('[data-quick-view]');
        if (btn) { ev.preventDefault(); qvOpen(btn.getAttribute('data-quick-view'), btn); return; }
        if (!qv.hidden && ev.target.closest('[data-qv-close]')) qvClose();
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && !qv.hidden) qvClose();
    });
}
