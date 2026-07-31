# دليل التشغيل على cPanel 🚀

دليل عملي من أول رفع الملفات إلى سحب التحديثات لاحقًا بضغطة زر.

---

## الطريقة الأولى: Git من cPanel (موصى بها — التحديثات بضغطة زر)

### 1. اربط المستودع

cPanel ← **Git™ Version Control** ← **Create**:

| الحقل | القيمة |
|---|---|
| Clone URL | `https://github.com/karimabdelsalam/rowad.git` |
| Repository Path | `repositories/rowad` |
| Repository Name | `rowad` |

> لو المستودع خاص، أنشئ **Personal Access Token** من GitHub واستخدم:
> `https://TOKEN@github.com/karimabdelsalam/rowad.git`

### 2. اضبط مسار النشر مرة واحدة

عدّل ملف `.cpanel.yml` في جذر المستودع وغيّر `USERNAME` إلى اسم مستخدم cPanel:

```yaml
- export USERNAME=اسم_المستخدم_عندك
- export DEPLOYPATH=/home/$USERNAME/public_html/clinic
```

احفظ التعديل وارفعه على GitHub (أو عدّله مباشرة من File Manager بعد أول Clone).

### 3. انشر

cPanel ← Git Version Control ← **Manage** ← تبويب **Pull or Deploy**:

1. **Update from Remote** — يسحب آخر التعديلات من GitHub.
2. **Deploy HEAD Commit** — ينسخ الملفات إلى `public_html/clinic`.

### 4. أنشئ قاعدة البيانات

cPanel ← **MySQL® Databases**:

1. أنشئ قاعدة بيانات جديدة.
2. أنشئ مستخدمًا وكلمة مرور قوية.
3. أضف المستخدم للقاعدة بصلاحيات **ALL PRIVILEGES**.

### 5. افحص ثم ثبّت

1. افتح `https://موقعك/clinic/check.php` — يتأكد أن السيرفر يدعم كل المطلوب.
2. افتح `https://موقعك/clinic/install.php` وأدخل بيانات القاعدة والعيادة وحساب المدير.
3. **احذف `install.php` و`check.php`** من File Manager.

### 6. التحديثات بعد ذلك

أي تعديل يُرفع على GitHub تسحبه على السيرفر بخطوتين فقط:

> Git Version Control ← Manage ← **Update from Remote** ← **Deploy HEAD Commit**

ملف `inc/config.php` **مستثنى من النشر**، فبيانات قاعدة بياناتك لا تُمسح أبدًا.
وترقية بنية قاعدة البيانات تحدث تلقائيًا عند فتح أول صفحة بعد التحديث.

---

## الطريقة الثانية: رفع يدوي (بدون Git)

1. نزّل المستودع من GitHub كملف ZIP.
2. من File Manager ارفع محتويات مجلد `clinic/` إلى `public_html/clinic`.
3. أكمل من الخطوة 4 أعلاه.

للتحديث لاحقًا: ارفع الملفات الجديدة فوق القديمة، **ولا تستبدل `inc/config.php`**.

---

## بعد التشغيل مباشرة ✅

- [ ] احذف `install.php` و`check.php`.
- [ ] الإعدادات ← أدخل اسم العيادة والهاتف والعنوان والعملة وأسعار الكشف.
- [ ] المستخدمون ← أنشئ حسابات الأطباء والاستقبال واضبط صلاحياتهم.
- [ ] المرضى ← أسنِد كل مريض لطبيبه المعالج.
- [ ] الأدوية ← عرّف أدوية الحقن (وحدات القلم، سعر الوحدة، تكلفة الشراء).
- [ ] الباقات ← اضبط كتالوج باقات الجلسات وأسعارها.
- [ ] فعّل **HTTPS** من cPanel ← SSL/TLS Status (مجاني عبر AutoSSL).
- [ ] خُذ **نسخة احتياطية** أولى من صفحة «نسخة احتياطية».

## التذكير التلقائي (اختياري)

cPanel ← **Cron Jobs** ← أضف مهمة يومية (مثلًا 6 مساءً):

```
0 18 * * *  /usr/local/bin/php /home/USERNAME/public_html/clinic/cron/reminders.php
```

ثم فعّل الإرسال واضبط المزوّد من صفحة الإعدادات.

---

## حل المشكلات الشائعة

| المشكلة | الحل |
|---|---|
| صفحة بيضاء | cPanel ← MultiPHP Manager ← اضبط إصدار PHP على **8.1+** |
| «تعذر الاتصال بقاعدة البيانات» | راجع `inc/config.php`؛ تأكد أن اسم القاعدة والمستخدم يبدآن ببادئة حسابك |
| الخط لا يظهر | تأكد أن مجلد `assets/fonts/` مرفوع بملفاته الأربعة |
| «انتهت صلاحية الجلسة» | تأكد أن الوقت في السيرفر صحيح، وأن الكوكيز مفعّلة |
| التصدير ينزل CSV بدل Excel | امتداد `zip` غير مفعّل — فعّله من *Select PHP Version ← Extensions* |
| الكرون لا يرسل | جرّب السطر يدويًا من Terminal، وراجع «سجل الرسائل» في الإعدادات |

## نسخة احتياطية دورية

- من داخل النظام: صفحة **نسخة احتياطية** ← تنزيل (ملف `.sql` كامل).
- من cPanel: **Backup Wizard** لجدولة نسخ تلقائية شاملة.
- الاستعادة: phpMyAdmin ← اختر القاعدة ← **Import** ← اختر الملف.
