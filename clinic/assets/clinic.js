// ربط حقول البحث عن مريض (datalist) بالحقل المخفي الذي يُرسل للسيرفر
document.querySelectorAll('.patient-pick').forEach(function (input) {
    var hidden = document.getElementById(input.dataset.target);
    var list = document.getElementById(input.getAttribute('list'));
    if (!hidden || !list) return;

    function sync() {
        var match = Array.prototype.find.call(list.options, function (o) {
            return o.value === input.value;
        });
        hidden.value = match ? match.dataset.id : '';
    }
    input.addEventListener('input', sync);
    sync();
});

// تأكيد قبل الحذف
document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
        if (!confirm(f.dataset.confirm)) ev.preventDefault();
    });
});
