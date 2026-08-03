/* ------------------------------------------- البحث السريع عن مريض */
/*
 * يجلب النتائج من السيرفر أثناء الكتابة بدل تحميل كل المرضى في الصفحة.
 * الحقل المخفي هو ما يُرسل فعليًا، ويُفرَّغ عند تغيير النص حتى لا يُرسل
 * اختيار قديم بالخطأ.
 */
document.querySelectorAll('.pfind').forEach(function (box) {
    var input  = box.querySelector('.pfind-input');
    var list   = box.querySelector('.pfind-list');
    var hidden = box.querySelector('input[type=hidden]');
    var timer = null, items = [], active = -1;

    function close() {
        list.hidden = true;
        active = -1;
    }

    function choose(p) {
        input.value = p.name + ' — ' + p.code + (p.phone ? ' — ' + p.phone : '');
        hidden.value = p.id;
        close();
        input.dispatchEvent(new CustomEvent('patient:selected', { bubbles: true, detail: p }));
    }

    function render() {
        if (!items.length) {
            list.innerHTML = '<span class="pfind-empty">لا توجد نتائج</span>';
            list.hidden = false;
            return;
        }
        list.innerHTML = '';
        items.forEach(function (p, i) {
            var el = document.createElement('span');
            el.className = 'pfind-item' + (i === active ? ' on' : '');
            el.innerHTML = '<b></b><small></small>';
            el.querySelector('b').textContent = p.name;
            el.querySelector('small').textContent = p.code + (p.phone ? ' · ' + p.phone : '');
            el.addEventListener('mousedown', function (ev) { ev.preventDefault(); choose(p); });
            list.appendChild(el);
        });
        list.hidden = false;
    }

    function search() {
        var q = input.value.trim();
        fetch('patient_search.php?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : []; })
            .then(function (rows) { items = rows || []; active = -1; render(); })
            .catch(function () { close(); });
    }

    input.addEventListener('input', function () {
        hidden.value = '';                 // النص تغيّر: الاختيار السابق لم يعد صالحًا
        clearTimeout(timer);
        timer = setTimeout(search, 220);   // مهلة قصيرة تمنع طلبًا لكل حرف
    });
    input.addEventListener('focus', function () {
        if (!input.value.trim()) search();
    });
    input.addEventListener('blur', function () { setTimeout(close, 150); });
    input.addEventListener('keydown', function (ev) {
        if (list.hidden || !items.length) return;
        if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
            ev.preventDefault();
            active += ev.key === 'ArrowDown' ? 1 : -1;
            if (active < 0) active = items.length - 1;
            if (active >= items.length) active = 0;
            render();
        } else if (ev.key === 'Enter' && active > -1) {
            ev.preventDefault();
            choose(items[active]);
        } else if (ev.key === 'Escape') {
            close();
        }
    });

    // منع إرسال النموذج بمريض غير مختار
    var form = box.closest('form');
    if (form && input.required) {
        form.addEventListener('submit', function (ev) {
            if (!hidden.value) {
                ev.preventDefault();
                input.focus();
                search();
                alert('اختر المريض من قائمة البحث.');
            }
        });
    }
});

/* ------------------------------------------------- تأكيد قبل الحذف */
document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
        if (!confirm(f.dataset.confirm)) ev.preventDefault();
    });
});
