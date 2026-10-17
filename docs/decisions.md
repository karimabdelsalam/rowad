# سجل القرارات — إعادة بناء رواد على Laravel

كل قرار يختلف فيه السلوك الجديد عن النظام القديم موثّق هنا بسببه.
القاعدة العامة: **المنطق المالي منقول حرفيًا** ومثبت باختبارات (`tests/Feature/InstallmentSchedulerTest.php`,
`tests/Feature/OwnerLedgerTest.php`) بأرقام محسوبة من الكود القديم.

## البنية

| القرار | السبب |
|---|---|
| Laravel 13 + PHP ≥ 8.3 | أحدث إصدار مدعوم حتى 2028؛ 8.3 لأن الاستضافة الحالية `ea-php83`. |
| قاعدة بيانات لكل مكتب (stancl/tenancy) | عزل كامل للبيانات المالية، نسخ احتياطي/تصدير لكل عميل. |
| الكاش والجلسات داخل قاعدة المكتب | `CacheTenancyBootstrapper` يتطلب Redis؛ هذا يعطي عزلًا بدون اعتماد إضافي. يمكن التحويل لـ Redis لاحقًا. |
| طابور المهام على القاعدة المركزية | العامل (worker) واحد لكل المكاتب، وكل مهمة تحمل معرف المكتب. |
| جدول `users` واحد لكل الأنواع (موظف/مالك/عميل) | بديل `user.groupid` 1/2/3، ويسمح بصلاحيات spatie وتوكنات Sanctum موحدة. |
| Morph map ثابت | أسماء مستقرة في أعمدة `*_type` لا تنكسر عند إعادة تسمية الكلاسات. |

## إعادة تسمية الجداول

| القديم | الجديد | ملاحظة |
|---|---|---|
| `building` | `units` | كان يمثل الوحدة الفعلية (دور، غرف، إيجار). |
| `building_locs` | `buildings` | المبنى/المجمع. |
| `buyer` | `clients` | مستأجر أو مشتري، فرد أو شركة. |
| `payments` | `installments` | مستحقات على المستأجر/المشتري. |
| `transactions` | `owner_transactions` | دفتر حساب المالك. |
| `statement_in` / `statement_out` | `receipt_vouchers` / `payment_vouchers` | سندات القبض والصرف. |
| `building.owner` JSON | `owner_unit` | نسبة الملكية + نسبة تحمل المصروفات. |
| `contract.buyer` JSON | `contract_clients` | أكثر من مستأجر/مشتري بنسب. |
| `building.meters` JSON | `unit_meters` | |
| `email_templates` | `message_templates` | مفتاح الحدث صار snake_case (انظر `NotificationEvent::fromLegacy`). |
| `print_samples` | `print_templates` + `print_template_versions` | النسخ السابقة صارت سجلًا كاملًا بدل نسخة واحدة. |

## اختلافات سلوكية مقصودة

1. **إضافة الشهور بدون overflow (ميلادي):** القديم كان يستخدم `mktime` فـ 31 يناير + شهر = 3 مارس.
   الجديد = 28 فبراير (`addMonthsNoOverflow`). هذا هو المتوقع محاسبيًا.
2. **الشهور الهجرية:** القديم كان يحتفظ برقم اليوم وإذا كان الشهر أقصر ينزلق لأول الشهر التالي.
   الجديد يثبّت آخر يوم في الشهر (30 جمادى الأولى + شهر = 29 جمادى الآخرة). التحويل عبر ICU (أم القرى الرسمي).
3. **التحصيل الجزئي:** إذا اكتمل المبلغ المدفوع جزئيًا يُغلق القسط تلقائيًا ويُوزع على الملاك.
   القديم كان يتطلب ضغط "ترحيل" منفصل. خيار الإغلاق اليدوي وتسوية حساب الملاك ما زالا متاحين.
4. **سجل الدفعات الجزئية:** كل دفعة جزئية تُحفظ في `installment_payments` (مع السند) بدل رقم إجمالي فقط.
5. **بيع دفعة واحدة:** تاريخ الاستحقاق = تاريخ العقد بدل "تاريخ اليوم" وقت الإدخال (يتطابقان عادةً).
6. **ترقيم المستندات:** أرقام تسلسلية بدون فجوات لكل نوع وسنة (`RV-2026-00001`) — مطلوب لـ ZATCA.
7. **QR الزكاة:** ترميز TLV أصلي بدل مكتبة Salla، مع حساب الطول بالبايت (صحيح للأسماء العربية).

## سلوكيات قديمة محفوظة كما هي (قد تحتاج قرارك لاحقًا)

- **الدفعة الأولى في البيع بالأقساط** لا تُسجّل كقسط (تعتبر مسددة عند التوقيع وتظهر في نص العقد فقط)،
  وأول قسط يبدأ بعد دورة كاملة من تاريخ البداية.
- **ضريبة دخل المكتب على عمولة المالك** (`comp_tax`) تُسجّل على حساب المالك نفسه — كما في `collectPayment` القديم.
  أما على عمولة المستأجر ورسوم الإدارة فتُسجّل على المكتب.
- **الإيجار اليومي** يُفوتر كقسط واحد في آخر يوم بإجمالي المدة.
- **العمولة على المالك** تُخصم فقط إذا كانت نسبة تحمل المصروفات للمالك > 0.

## الإشعارات والمهام المجدولة

- **التذكيرات:** استعلامات الكرون القديم كانت مكتوبة بعلامة معكوسة (`paydate >= NOW() + 15 DAY`)
  فكانت ترسل تذكير "قبل 15 يوم" لكل الأقساط البعيدة فور إنشائها. الجديد ينفذ المقصود:
  التذكير عندما يصبح الاستحقاق خلال 15 يومًا، ثم قبل يوم، ثم يوم الاستحقاق، ثم تذكير يومي بالتأخر
  (أقساط البيع) — نفس تسلسل `alert` القديم ونفس التكرار اليومي للمتأخرات.
- **رسوم الإدارة المتأخرة** تُحتسب بأثر رجعي لكل الفترات الفائتة (إن توقف الكرون أيامًا) بدل فترة واحدة.
- **إرسال الرسائل:** كل رسالة تُسجَّل في `outgoing_messages` وتُرسل عبر الطابور مع 3 محاولات، بدل
  `email_queue` الذي كان يُحذف بعد الإرسال (لا سجل للفشل).
- **مزودو SMS:** الـ 13 مزودًا منقولون بنفس صيغ الطلب، مع HTTPS لكل الروابط (كان Unifonic و MSG91 على HTTP).
- **كلمات مرور SMTP و SMS** مشفرة في قاعدة البيانات.

### سلوكيات قديمة لم تُنقل عمدًا (مهمة)

- **إرسال سجل الأخطاء للمطوّر الأصلي:** `cron/daily.php` كان يرسل ملف `error_log.log` كاملًا إلى
  `smartiolabs@gmail.com` كل 7 أيام. السجل قد يحتوي بيانات استعلامات وعملاء. **لم يُنقل.**
- **التحديث التلقائي من سيرفر المطوّر:** `cron/hourly.php` كان يتصل بـ `smartiolabs.com` كل ساعة
  ويحدّث كود النظام تلقائيًا ويوقف النظام عند انتهاء الترخيص. **لم يُنقل** — يحل محله نظام الخطط والاشتراكات.

## نقل بيانات المكاتب من النظام القديم

- الأمر: `php artisan rowad:import-legacy <office-id|domain> --database=<db> --username=<user> --media=<old>/media`
  (كلمة مرور قاعدة البيانات القديمة من `LEGACY_DB_PASSWORD` أو تُطلب سرًّا — لا تُكتب في سجل الأوامر).
- **المعرّفات القديمة محفوظة** (وحدة، عقد، قسط، سند، قيد) فتبقى كل الروابط بينها سليمة.
  قوائم المكتب (مدن، بنوك، تصنيفات...) تحل محل القوائم الافتراضية إذا كانت موجودة في القديم.
- **أرقام المستندات القديمة** تُحفظ بصيغة `RC-<id>` / `RV-<id>`، والترقيم الجديد (`RV-2026-00301`)
  يكمل بعد أكبر رقم مستورد.
- **كلمات المرور:** القديم يخزن MD5 بدون ملح. تُحفظ كما هي في `legacy_password` وعند أول دخول ناجح
  تُستبدل بـ bcrypt وتُحذف القديمة — المستخدم لا يلاحظ شيئًا ولا يحتاج لإعادة تعيين.
- **الدخول باسم المستخدم:** القديم كان يسجل الدخول بـ `username`؛ لوحات المكتب تقبل الآن البريد أو اسم المستخدم.
- **الصلاحيات:** نظام الصلاحيات القديم (`cp_permission` لكل وحدة برمجية) لا يطابق الأدوار الجديدة؛
  كل موظفي المكتب يُعطَون دور "مدير" ويعدّل صاحب المكتب الأدوار بعد النقل.
- **المرفقات** (`attachments`) تُنسخ إلى مكتبة الوسائط: صور الوحدات → معرض الوحدة، وباقي الملفات →
  مرفقات المالك/العميل/الموظف/العقد/السند. أضيف حقل المرفقات لهذه الشاشات (كان موجودًا في القديم).
- أي صف لا يمكن نقله (مرجع مفقود، بريد مكرر...) لا يوقف النقل؛ يظهر كتحذير في نهاية الأمر.
- البريد المكرر بين مستخدمين يُحذف من المستخدم الأحدث (الجدول الجديد يتطلب بريدًا فريدًا) ويبقى دخوله باسم المستخدم.

## واجهة الموبايل (API)

- بدل `?output=json` على كل صفحة، واجهة محددة `/api/v1` موثقة في `docs/api.md` بتوكنات Sanctum
  مخزنة في قاعدة بيانات المكتب. مفتاح `rest_api_key` العام لم يُنقل: التوكن الشخصي يكفي، ومفتاح مشترك
  داخل تطبيق منشور لا يحمي شيئًا.
- صلاحيات الموظف في التطبيق = صلاحياته في لوحة الإدارة (نفس أسماء الصلاحيات).
- **الإشعارات الفورية (Push):** النظام القديم كان يسجّل `device_tokens` فقط ولا يرسل أي push فعليًا.
  الجديد يسجّل التوكنات بنفس الطريقة؛ الإرسال عبر Firebase يحتاج مشروع Firebase وبيانات حساب الخدمة
  الخاصة بكم — **قرار/بيانات من المالك** قبل تفعيله.
- سجل نشاط الدخول (`login_activity`) يُسجَّل لكل دخول من اللوحات أو التطبيق، ويظهر آخر 10 في لوحة
  التحكم مع زر مسح، كما في القديم.

## مراجعة التطابق مع الجرد (ما أُضيف وما تغيّر عمدًا)

أُضيف بعد مراجعة `docs/feature-inventory.md` بندًا بندًا:
- تصدير Excel وطباعة لكل قائمة (نفس الأعمدة الظاهرة والفلاتر، كل الصفحات). تصدير الوحدات يتطلب صلاحية `units.export` كما في القديم.
- استرجاع كلمة المرور بالإيميل **أو برمز SMS** في اللوحات الثلاث وفي تطبيق الموبايل. الرد لا يكشف هل الرقم/الإيميل
  مسجل (القديم كان يكشف ذلك — تحسين أمني). نجاح الاسترجاع يُخرج الحساب من كل الأجهزة.
- بوابتا المالك والعميل صار لهما "استرجاع كلمة المرور" و"حسابي" (تغيير كلمة المرور).
- لوحة التحكم: مهامي، ملاحظاتي الخاصة، سجل الدخول، ورموز QR لروابط بوابات الملاك والعملاء والموقع (مثل `dashboard/qrcode`).
- رسالة إغلاق الموقع العام، وفحص رصيد/اتصال مزود SMS من الإعدادات (فحص smsglobal القديم كان يرسل رسالة فعلية فلم يُنقل).
- صور مصغرة لصور الوحدات (بديل PhpThumb).

تغيّر عمدًا لأن المنصة صارت SaaS:
- **النسخ الاحتياطي وإصلاح الجداول** (`maintenance`): تُدار مركزيًا على مستوى المنصة لكل قواعد المكاتب
  (نسخ يومي + احتفاظ)، وليس زرًا داخل كل مكتب. **يحتاج قرار المالك:** مكان التخزين (S3/خادم آخر) ومدة الاحتفاظ.
- **رفع ملفات الترجمة `.mo`:** الترجمة في `lang/ar.json` و`lang/en.json` ضمن الكود ويحدّثها فريق المنصة.
- **إدارة مهام الكرون (`cronjob`):** المهام المجدولة واحدة للمنصة (`routes/console.php`) وتعمل لكل المكاتب.
- **صفحة الصيانة:** `php artisan down` على مستوى المنصة.
- **التحديث الذاتي والترخيص (`updatecore`):** يحل محله نظام الخطط والاشتراكات في لوحة المنصة.
- **إلغاء ترحيل حركة مالك مرتبطة بسند** مرفوض (يُحذف السند أولًا)؛ القديم كان يسمح فيبقى السند معلقًا بلا حركة.
- **إرجاع قسط محصّل إلى مستحق** مرفوض إذا رُحّلت حصة مالك منه (نفس شرط القديم)، وعمولة المكتب من المشتري لا تمنعه.
- **الملفات الخاصة** (`/media/{id}`): القديم والإصدار الأول هنا كانا يسمحان لأي مستخدم مسجل بتنزيل أي ملف برقمه.
  الآن: الموظفون فقط، أو المالك/العميل صاحب الملف (ملفه، صور وحداته، تقارير وحداته).
- **رد الموظف على تذاكر الدعم** يظهر في بوابة المالك/العميل مع إشعار داخلي (القديم لم يكن فيه رد داخل التذكرة).
- **حجز الوحدة من الخطابات** (`makeBuildingBusy`): معطّل (مُعلَّق) في كود النظام القديم نفسه، فلم يُنقل.

## نقل حقيقي لمكتب (نسخة قاعدة بيانات e-rowad)

جُرِّب النقل على نسخة حقيقية (178 وحدة، 15 مبنى، 11 مستخدمًا، 531 قيمة حقل مخصص، 13 قالب طباعة):
الأعداد والمجاميع تطابق القديم (مجموع الإيجارات 10,419,000). ما كشفه وأُصلح:
- **قوالب الطباعة:** القديم يطبع كل مستند برقم قالب ثابت (8 عقد إيجار، 4 سند قبض، 3 سند صرف، 5/6/7/10/11 خطابات،
  12/13 فاتورة)، والقالبان 1 و2 رأس وذيل مشتركان. قوالب المكتب تُنقل كنسخ `legacy_*` وتبقى التصاميم الحديثة هي المفعّلة (بطلب المالك)؛ الخيار `--legacy-templates` يجعلها هي المستخدمة، وتُطبع بنفس
  المتغيرات القديمة (`{buyers}`, `{total_rent}`, كتل `<!--{owners}-->`…) — القالب المنقول معلَّم `legacy_syntax`
  فيأخذ كل متغير معناه القديم (مثلًا `{total}` في الفاتورة = قبل الضريبة).
- **الصكوك والقطع:** الوحدة في القديم لها عدة صكوك (نوع/رقم/مصدر/تاريخ) وعدة قطع بحدودها الأربعة؛ أضيف لها جدولان
  وشاشة إدخال. وصف الحد يتبع `processDimensions` القديمة مع تصحيح خطأ فيها (كان رقم القطعة المجاورة لا يظهر أبدًا).
- **العدادات:** ملكية العداد (خاص/مشترك) ورمز ورقم حساب السداد، وقراءات العدادات في عقد الإيجار عند التسليم.
- **طباعة الخطابات** (رسمي، إخلاء طرف، تغيير إيجار، تسليم عقار، تقييم عقار) لم تكن منفذة — أضيفت بشاشة حقيقية.
- الجنسية مخزنة كرقم دولة في القديم → تُحوَّل لاسم الدولة. نصوص القوالب مخزنة مُرمّزة HTML → تُفك.
- مستخدم المكتب الذي أُنشئ عند التسجيل (رقم 1) يُنقل لرقم جديد كي يحتفظ مستخدم القديم رقم 1 برقمه.
- **ملاحظة للمكتب:** قالب "تقييم عقار" عندهم فيه الحدان الشرقي والغربي مبدَّلان (خطأ في قالبهم نفسه، كان يُطبع
  هكذا في القديم أيضًا) — يُصلَح من محرر القوالب.
- الشعار والمرفقات تحتاج مجلد `media` من السيرفر القديم (`--media=`)؛ النسخة المرسلة قاعدة بيانات فقط.

## التصميم (طلب المالك)

- **الخط Cairo** في كل اللوحات والموقع العام والمطبوعات.
- **المطبوعات الحديثة:** تصميم جديد لكل المستندات (`resources/print-templates/*.html` + نظام تنسيق في
  `print/document.blade.php`) يحتفظ بكل القيم التي كانت في المطبوعات القديمة (الأطراف بالجنسية والهوية والجوال،
  بيانات العقار، الصكوك، العدادات وقراءاتها، القيم والمدفوع والمتبقي والمبالغ كتابةً، جدول الدفعات، البنود، التواقيع، QR التحقق).
  الأقسام الفارغة تُخفى تلقائيًا. المكاتب الموجودة تحولت للتصميم الجديد مع حفظ النسخة السابقة لكل قالب (قابلة للاسترجاع).
  قوالب المكتب القديمة تُنقل كنسخ احتياطية فقط ما لم يُطلب `--legacy-templates`.
- **لوحة التحكم:** شريط ترحيب بالتاريخين وإجراءات سريعة، 8 مؤشرات بأيقونات واتجاه آخر 6 أشهر، و4 رسوم
  (التحصيل الشهري، حالة الوحدات، نسبة التحصيل، أعلى المباني تحصيلًا). ألوان الرسوم من لوحة معتمدة ومختبرة لعمى الألوان
  بترتيب ثابت (أزرق، برتقالي، أخضر مائي، أصفر) والرسوم ذات السلسلة الواحدة بلون الهوية؛ المحاور من اليمين لليسار بالعربية.
- **الوضع الداكن** أُوقف في اللوحات: الرسوم والمطبوعات مصممة للخلفية الفاتحة.
- حدود القطع الافتراضية (شارع، ممر، جار، قطعة...) تُضاف لكل مكتب كما في القديم.

## القائمة الجانبية ومراجعة الشاشات

- **القائمة بنفس امتداد القديم:** التقارير العشرة صارت بنودًا مستقلة تحت "التقارير" بترتيب القائمة القديمة (كانت مجمعة
  في بند واحد)، والقوائم المرجعية الثلاث عشرة صارت مجموعة "قواعد البيانات" (اسمها القديم) قابلة للطي. الروابط بقيت
  `/admin/reports/...` كما هي.
- **بندان كانا ناقصين فعلًا بعد المقارنة مع قائمة القديم (`cp_module`):**
  - **اعداد الترقيم** (`home/serial`): الرقم التالي لكل مستند في السنة الحالية. يُرفع فقط ولا يُرجع لرقم مستخدم (نفس قاعدة القديم).
  - **سحابة الملفات** (`home/upload`): ملفات المكتب المشتركة للموظفين فقط، مع رابط قابل للنسخ. ملفات القديم
    (`attachments` لوحدة `home`) تُنقل إليها عند الاستيراد. الصلاحية الجديدة `office_files.*` تُعطى لدور المدير في المكاتب الحالية.
- **عرض الوحدة والعقود:** أقسام منظمة بدل قائمة الحقول الخام، العقد الحالي كرابط، التحصيل ونسبته، والدفعة القادمة.
- **إصلاح:** القسط المحصل بالكامل كان يظهر مدفوعه 0 ومتبقيه كامل (`paid_amount` في القديم للدفعات الجزئية فقط)، والآن
  يُحسب كمدفوع بالكامل في كل الشاشات والتقارير والـ API. وتاريخ الدفعة القادمة في العقد يتحدث مع كل تحصيل أو إلغاء تحصيل.
- **التواريخ** بشكل واحد `2026-10-15` في كل المتصفحات (منتقي Filament بدل منتقي المتصفح)، والأسبوع يبدأ السبت.

## صفحة المنصة والتسجيل الذاتي

- الصفحة الرئيسية للمنصة كانت صفحة Laravel الافتراضية؛ صارت صفحة تسويق عربية (المزايا، المطبوعات، البوابات، خطوات البدء،
  الأسعار من جدول الباقات مع تبديل شهري/سنوي، الأسئلة الشائعة) وصفحة تسجيل تنشئ المؤسسة فورًا بتجربة مجانية على الباقة المختارة
  عبر `OfficeProvisioner` نفسه المستخدم في لوحة المنصة.
- **الصياغة (طلب المالك):** "مؤسستك/المؤسسة" بدل "مكتبك/المكتب" في المنصة وداخل النظام كله (الإعدادات، لوحة المنصة، التقارير،
  رسائل الإيقاف، بند العمولة في العقود)، والجمع "المنشآت العقارية" (لأن "المؤسسات" اسم الباقة العليا).
  تبقى "مكتب" حيث تعني نوع وحدة عقارية أو مصروف "إيجار مكتب".
  المنتج موجه للسعودية: ريال، هجري، زاتكا، وجوال سعودي إلزامي في التسجيل (05xxxxxxxx أو 9665xxxxxxxx).
- لا ادعاءات لم تُنفذ: الفوترة الإلكترونية "المرحلة الأولى (QR)"، والنقل "من نظام رواد السابق" فقط.
- حماية التسجيل: حقل مخفي للبوتات، حد 5 محاولات كل 10 دقائق لكل IP، ورسائل خطأ واضحة للنطاق المحجوز أو المستخدم.
- روابط صفحات المنصة نسبية: أسماء المسارات مكررة لكل نطاق مركزي، و`route()` كان يولد نطاقًا آخر فيضيع الكوكي ويفشل CSRF.
- **رسائل التحقق بالعربية:** لم يكن هناك `lang/ar/validation.php` فكانت كل رسائل الأخطاء في النظام كله بالإنجليزية؛ أضيفت ملفات
  validation وauth وpasswords وpagination بالعربية.

## مراجعة الأمان والأداء

**الأمان — ما وُجد وأُصلح (لكل بند اختبار في `SecurityHardeningTest` و`TenantIsolationTest`):**
- **تسرب الصلاحيات بين المؤسسات:** كاش الصلاحيات كان مشتركًا (مخزن الكاش مركزي، وعامل الطوابير يخدم مؤسسات كثيرة)، فتعديل
  دور في مؤسسة ينعكس على أخرى. صار لكل مؤسسة مفتاح كاش، وتُمسح النسخة في الذاكرة مع كل تبديل، وكذلك الإعدادات.
- **الملفات المرفوعة:** كانت تُعرض داخل المتصفح على نطاق المؤسسة نفسه، فملف SVG أو HTML يمكن أن يشغّل سكربت. الآن تُعرض الصور
  وPDF فقط، وكل ما عداها يُنزّل، مع `nosniff` وسياسة CSP معزولة. صور الوحدات لا تقبل SVG.
- **المطبوعات:** رقم الشيك ورقم العقد صارا يُهرَّبان، والبنود والشروط الإضافية ونص الخطاب تُنظَّف من السكربتات.
- **الدخول كمالك/عميل:** يعيد التحقق من صلاحية `login_as` حتى مع رابط موقّع، ويجدد الجلسة.
- رؤوس أمان افتراضية لكل الصفحات، والمستخدم الموقوف لا يطبع.
- **تم التحقق وسليم:** عزل البوابات (كل مالك/عميل يرى بياناته فقط)، كل الموارد عليها صلاحيات، رموز SMS (6 أرقام عشوائية مشفرة
  بحد محاولات وصلاحية)، حدود المحاولات في الـ API ونموذج الاستفسار، لا SQL خام بمدخلات المستخدم، ولا إسناد جماعي من الطلب.

**الأداء — قيس على مؤسسة اختبار (1000 وحدة، 800 عقد، 9600 قسط، 4000 حركة مالك):**
- كل الصفحات الرئيسية بين 140 و250 ملي ثانية على السيرفر، وبحد أقصى 59 استعلامًا (لا يوجد N+1).
- **تقرير الوحدات السنوي** كان يعرض كل الوحدات دفعة واحدة (12 ثانية في المتصفح)؛ صار بصفحات وصف الإجمالي أولًا، والبحث فيه
  يعمل (كان لا يعمل)، وتصدير CSV للتقارير المحسوبة كان يتعطل.
- **رسم التحصيل الشهري** كان يحمّل كل أقساط السنة؛ صار مجموعًا شهريًا من قاعدة البيانات (400 → 60 ملي ثانية).
- فهارس جديدة: الأقساط (المحصل + الاستحقاق، تاريخ التحصيل)، العقود (النهاية، الدفعة القادمة)، حركات الملاك (المالك + التسوية).
- أداة قياس للتطوير: `ROWAD_PERF_LOG=true` يسجل عدد الاستعلامات والزمن لكل طلب في `storage/tenant<id>/logs/perf.log`.

## اسم المنتج

- **اسم المنتج: عقار نوفا / Aqar Nova** (طلب المالك): "عقار نوفا" في الواجهة العربية و"Aqar Nova" في الإنجليزية، بشعار نصي "عقار **نوفا**" / "Aqar **Nova**".
- يبقى اسم "رواد" حيث يشير إلى **النظام القديم** (أمر الاستيراد، "ننقل بياناتك من نظام رواد السابق").
- المعرّفات الداخلية في الكود (`config/rowad.php`، أوامر `rowad:*`، مجلد المستودع) لم تتغير: لا تظهر للمستخدم، وتغييرها يكسر سكربتات التشغيل.

## مراجعة التطابق الثانية

- **تسجيل حساب جديد للمالك/العميل (`newAccount`):** الدالة في النظام القديم لا يستدعيها أي شيء، وقالب `newRegister` لا يُرسل من أي
  مكان؛ أي أنه لم يكن ميزة تعمل. حسابات البوابات تُنشأ من المؤسسة (إجراء "حساب البوابة") لأنها يجب أن ترتبط بسجل المالك/العميل
  الصحيح، وتُرسل بيانات الدخول بقالب "بيانات الدخول". (التسجيل الذاتي موجود للمؤسسات نفسها في صفحة المنصة.)
- **رموز QR في لوحة التحكم:** لم تكن رمز دخول، بل روابط البوابات والموقع مع **نسخ مطبوعة** (`qrcode_owner/client/sales`). أضيفت
  الصفحات المطبوعة (A4 بشعار المؤسسة وتعليمات المسح) برابط "طباعة" تحت كل رمز.
- **مفتاح خرائط Google:** كان يُحفظ فقط. صار يُستخدم لعرض خريطة موقع الوحدة في صفحة الوحدة العامة وفي عرض الوحدة؛ وبدون مفتاح
  تُعرض خريطة OpenStreetMap (لا تحتاج مفتاحًا).

## حدود الباقات ومزاياها (بديل فحوصات updatecore القديمة)

- `App\Support\PlanLimits` هو المرجع الوحيد: مؤسسة بلا باقة = بلا حدود (مؤسسات المنصة نفسها أو المنقولة قبل اختيار باقة).
- الحصص: الوحدات والمستخدمون (الموظفون فقط، لا حسابات البوابات) تمنع الإضافة عند الحد — زر الإضافة معطّل مع السبب، وصفحة الإنشاء تُرجع للقائمة، والحفظ نفسه يُرفض.
  رسائل SMS الشهرية: الرسالة الزائدة تُسجَّل "فشلت" بسبب واضح ولا تُرسل. التخزين (يُقاس كل ساعة): عند امتلائه يُرفض أي رفع جديد في كل حقول الملفات برسالة واضحة، والملفات المحفوظة تبقى قابلة للتعديل والحذف.
- المزايا: بوابة الملاك وبوابة المستأجرين وواجهة API تُغلق بـ`EnsurePlanFeature` (صفحة "غير متاح في الباقة" أو JSON 403)،
  والرسائل الجماعية تختفي من القائمة. الفاتورة الضريبية برمز زاتكا لا تُغلق أبدًا لأنها التزام نظامي.
- صفحة "الاشتراك" في الإعدادات: الباقة والحالة ونهاية التجربة/الاشتراك والاستهلاك والمزايا، وطلب ترقية لباقة أعلى سعرًا فقط.
  الطلب يُحفظ على المؤسسة ويظهر في لوحة المنصة (عمود "طلب ترقية" وإجراء "تمت معالجة الطلب")، ويُرسل بريد لـ`PLATFORM_ADMIN_EMAIL` إن وُجد.
  تغيير الباقة نفسه يبقى يدويًا من لوحة المنصة حتى يُختار مزود الدفع.

## النطاق الخاص وتسجيل دخول مدير المنصة

- النطاق الخاص يُضاف من لوحة المنصة (تعديل المؤسسة ← النطاقات). يُطبَّع المُدخل (`https://Alrowad.SA/` ← `alrowad.sa`) ويُرفض المكرر ونطاقات المنصة.
  النطاق الفرعي على المنصة لا يُحذف أبدًا. خطوات DNS وSSL في `docs/deployment.md` القسم 9.
- إصلاح: جدول مستخدمي المنصة كان ينقصه `legacy_password` الذي يقرأه مزود الدخول المشترك، فكان دخول مدير المنصة يفشل في بيئة التطوير. أُضيف العمود باختبار دخول فعلي.
- حقول التاريخ والوقت صارت بنفس شكل حقول التاريخ (`2026-10-29 18:15`) بدل تنسيق المتصفح الأمريكي.

## دورة الاشتراك المدفوع (بديل فحص انتهاء الاشتراك في updatecore)

- الدفع يدوي حتى يُختار مزود الدفع: من لوحة المنصة "تسجيل دفعة اشتراك" على المؤسسة (الباقة، شهري/سنوي، المبلغ شامل ضريبة 15٪، المرجع).
  التسجيل يفعّل المؤسسة على الباقة المدفوعة، ينهي الفترة التجريبية، يمسح طلب الترقية، ويُنشئ سجل دفعة بقيمة الضريبة المحسوبة.
- الدفع المبكر لا يضيّع أيامًا: الفترة الجديدة تبدأ من نهاية الحالية.
- `rowad:subscriptions` يوميًا 02:45: تذكير بالبريد قبل نهاية الفترة بـ7 أيام وبيوم، وقبل نهاية التجربة بـ3 أيام؛
  عند الانتهاء تصبح المؤسسة "متأخرة السداد" وتعمل 7 أيام سماح ثم تُوقف حتى الدفع. المؤسسات المفعّلة يدويًا بلا فترة مدفوعة لا تُمس.
- داخل لوحة المؤسسة يرى من يدير الإعدادات تنبيهًا أعلى كل صفحة عند اقتراب النهاية أو التأخر، مع رابط صفحة الاشتراك.
- لوحة المنصة: لوحة تحكم (المؤسسات، المدفوعة، التجريبية، الإيراد الشهري المتكرر، طلبات الترقية) وجدول "مؤسسات تحتاج متابعة".
  حذف مؤسسة يتطلب كتابة اسمها (يحذف قاعدة بياناتها نهائيًا)، والباقة المستخدمة لا تُحذف بل تُغلق.

## إصلاح: صيغ الجمع العربية كانت تظهر بالإنجليزية

`trans_choice()` في Laravel يختار اللغة الاحتياطية (الإنجليزية) إذا لم يجد المفتاح في ملفات اللغة الحالية، ولا يوجد `ar.json` لأن العربية هي لغة المفاتيح.
بعد إضافة ترجماتها الإنجليزية صارت كل عبارات الجمع ("3 أقساط متأخرة" وغيرها) تظهر بالإنجليزية في الواجهة العربية.
الحل `App\Support\Translation\Translator` يُبقي العربية لغة الجمع حين تكون الواجهة عربية، مع اختبار يمنع رجوع الخطأ.

## الذكاء الاصطناعي (المرحلة 1: النواة)

- المزود: Anthropic (Claude) عبر Messages API. المفتاح `ANTHROPIC_API_KEY` ورقم النموذج `AI_MODEL` في `.env` يختارهما المالك؛
  بدونهما يظهر "المساعد غير مفعّل" ولا يُرسل أي طلب.
- المساعد لا يرى قاعدة البيانات مباشرة ولا يكتب SQL. يرى البيانات فقط عبر "أدوات" ثابتة تعمل باسم المستخدم:
  - الموظفون: 13 أداة (نظرة عامة، التحصيل الشهري، نسبة التحصيل، الوحدات، العقود، الأقساط، الملاك والعملاء، أرصدة الملاك، الملخص المالي، أداء المباني، التذاكر)،
    وكل أداة تتحقق من نفس صلاحية الشاشة المقابلة (مسوّق بلا صلاحية التقارير لا يرى الملخص المالي).
  - المالك والمستأجر: أدوات "بياناتي" فقط، تبدأ كل استعلاماتها من سجل المستخدم نفسه، فلا يمكن الوصول لبيانات غيره مهما كان السؤال.
  - قاعدة كل مؤسسة منفصلة أصلًا، فلا تسرّب بين المؤسسات.
- المساعد للقراءة والتحليل فقط؛ لا ينفذ تحصيلًا أو تعديلًا أو إرسالًا، ويرشد للشاشة المناسبة.
- التكلفة: ميزة `ai` في كل الباقات مع حد شهري للأسئلة (الأساسية 100، الاحترافية 600، المؤسسات 3000)، يُحسب كل رد من المساعد (محادثة أو تحليل تقرير أو رؤى).
  تُسجَّل الرموز المستهلكة (tokens) لكل رد لمتابعة التكلفة.

## الذكاء الاصطناعي (المرحلة 2 و3: المحادثة والتحليلات)

- صفحة "المساعد الذكي" في لوحة المؤسسة وبوابة الملاك وبوابة المستأجرين، وزر "اسأل المساعد" أعلى كل صفحة.
  كل مستخدم يرى محادثاته فقط. رصيد الأسئلة يظهر للموظفين فقط (هو رصيد المؤسسة).
- "تحليل ذكي" في كل تقرير: يقرأ التقرير كما هو مصفّى على الشاشة (حتى 400 صف) ويفتح التحليل في المساعد لمتابعة الأسئلة.
- "رؤى ذكية" في لوحة التحكم لمن لديه صلاحية تقرير الأرباح: تُولَّد بضغطة (سؤال واحد من الرصيد) وتظهر لكل الموظفين حتى التحديث التالي،
  ولا تُولَّد تلقائيًا عند كل دخول حتى لا يُستهلك الرصيد دون طلب.
- "اسأل المساعد عن هذا" في صفحات الوحدة والمبنى وعقد الإيجار والمالك والعميل: تفتح المساعد ومعه السجل كسياق وأسئلة مقترحة له،
  بعد التحقق من صلاحية المستخدم على السجل. بوابات الملاك والمستأجرين لا تقبل هذا السياق.
- الإجابات تُعرض Markdown مع منع أي HTML أو روابط javascript.

## الاستضافة: cPanel على aqarnova.com

- النطاق `aqarnova.com`، والمؤسسات على `*.aqarnova.com`. الدليل: `docs/deployment-cpanel.md` (ودليل VPS باقٍ لمن ينتقل لاحقًا).
- cPanel لا يسمح بـ `CREATE DATABASE` من MySQL، فـ`App\Tenancy\CpanelDatabaseManager` ينشئ قاعدة كل مؤسسة ويحذفها عبر UAPI بمفتاح API،
  ويمنح مستخدم التطبيق صلاحيتها. الأسماء `USER_t<uuid بدون شرطات>` لأن cPanel يشترط بادئة الحساب.
- لا Supervisor: `QUEUE_WORKER=scheduler` يجعل cron الدقيقة الواحدة يعالج الطابور أيضًا (`queue:work --stop-when-empty`).
- إذا فشل التسجيل في أي خطوة (مثل امتلاء حصة قواعد البيانات في الاستضافة) يُحذف كل ما أُنشئ ويمكن إعادة المحاولة بنفس الرابط.
- أي نطاق فرعي لا تملكه مؤسسة يعرض صفحة 404 ودية مع رابط عقار نوفا بدل خطأ خادم (مهم مع Wildcard DNS).
- Wildcard SSL: AutoSSL إن كانت الـ DNS عند الاستضافة، وإلا Cloudflare المجاني مع `TRUSTED_PROXIES=*`.

## الإطلاق: الحساب التجريبي والصفحة الرئيسية

- `rowad:demo` ينشئ المؤسسة التجريبية `demo.<النطاق>` ويعلّمها `is_demo`. الصفحة الرئيسية تعرض قسم "جرّب النظام الآن" فقط إن وُجدت،
  مع دخول بضغطة (`/demo/admin|owner|client`) لا يعمل إلا في المؤسسة التجريبية (404 في أي مؤسسة حقيقية).
- في المؤسسة التجريبية: لا تُرسل أي رسالة SMS أو بريد، ولا يتغير بريد أو كلمة مرور الحسابات الثلاثة، و`DEMO_RESET_DAILY=true` يعيد بناءها كل ليلة.
- الصفحة الرئيسية: قسم الذكاء الاصطناعي، رابط في القائمة، سؤال شائع عن خصوصية الذكاء الاصطناعي، وسوم Open Graph ووصف للمشاركة،
  `sitemap.xml` و`robots.txt` يمنع فهرسة اللوحات.
- صفحتا شروط الاستخدام وسياسة الخصوصية (عربي/إنجليزي) كمسودة مبنية على ما يفعله النظام فعلًا ونظام حماية البيانات الشخصية،
  **تحتاج مراجعة المالك/مستشار قانوني قبل الإطلاق**. تتضمن التزامين يُنفذان يدويًا الآن: حذف بيانات المؤسسة الملغاة بعد 30 يومًا، ونسخ احتياطية يومية (من cPanel).
- بيانات التواصل في الفوتر من `PLATFORM_CONTACT_EMAIL / PHONE / WHATSAPP`.

## إعادة تصميم اللوحات (المكتب والملاك والمستأجرين)

- المرجع البصري هو شاشات النظام القديم (phantom.estate): كثافة معلومات عالية، شارات ملونة للحالات والأطراف، وقائمة إجراءات لكل صف.
  نفّذنا ذلك فوق Filament بثيم واحد `resources/views/filament/theme.blade.php` (شريط جانبي داكن بلون العلامة، بطاقات، جداول مخططة، شارات حبوب).
- كل صفحة "عرض" تبدأ ببطاقة رأس (`App\Filament\Infolists\Parts::header`) فيها اسم السجل الحقيقي وأرقامه الرئيسية بألوان الحالة،
  ثم أقسام مسماة بالعربية (الطرف والسبب، الدفع، المستند، السجل…) بدل قائمة الحقول الخام والمعرفات الرقمية.
  `Parts` يوحّد الأجزاء المتكررة: المبالغ بعملة المؤسسة، الروابط لسجلات أخرى، الهوية، الاتصال، حساب البوابة، ومن أنشأ السجل (مطوي).
- التعدادات (حالة الوحدة والعقد والتذكرة، طريقة الدفع، الاتجاه، نوع القسط…) تحمل لونها وأيقونتها بنفسها (`getColor`/`getIcon`) فتظهر موحدة في كل جدول وصفحة.
- الجداول: عمود رئيسي بخط عريض مع سطر وصف تحته (العقار تحت الوحدة، المستأجر تحت الوحدة في الأقساط)، حالة القسط كشارة (محصّل/متأخر/جزئي/مستحق)
  بدل أيقونة صح/خطأ، والإجراءات: الإجراء الأهم ظاهر والباقي في قائمة "⋮".
- تذاكر الدعم تُعرض كمحادثة (رسالة مقدّم الطلب ثم الردود) في لوحة المكتب وفي بوابتي الملاك والمستأجرين.
- بوابتا الملاك والمستأجرين: نفس بطاقة الرأس في نوافذ العرض، بطاقات الأرقام بأيقونة ووصف، وجدول الدفعات القادمة يبيّن "متأخرة/قادمة" مع المدة المتبقية.
- القوائم المتكررة داخل صفحات العرض (وحدات المالك، عقود العميل، دفعات القسط) تُحمَّل بعلاقاتها مسبقًا لأن التحميل الكسول معطّل في التطبيق.

## لوحة المنصة (المرحلة 2): الإعدادات، صفحة المؤسسة، المدفوعات، الصحة، المدراء

- **إعدادات المنصة** تُحفظ في جدول `settings` في القاعدة المركزية (`App\Support\PlatformSettings`) بدل `.env`، ولكل مفتاح قيمة افتراضية
  تساوي السلوك السابق: فتح/إغلاق التسجيل الذاتي مع رسالة، الباقة الافتراضية، مدة التجربة (أو حسب كل باقة)، أقل طول للنطاق الفرعي،
  نطاقات محجوزة إضافية، بيانات التواصل (تغلب على `PLATFORM_CONTACT_*`)، وإشعار يظهر أعلى كل لوحات المؤسسات.
  النطاق الأساسي يبقى في `.env` لأنه يحتاج DNS وشهادة.
- **صفحة المؤسسة** (عرض) هي مركز العمل: الحالة والمدفوع حتى والاستخدام من الباقة (من `rowad:usage`)، مدراء المؤسسة من قاعدتها،
  سجل الاشتراكات، والإجراءات: تسجيل دفعة، تمديد التجربة، تغيير النطاق الفرعي (مع فحص الحجز والتكرار)، الدخول كمدير المؤسسة، إيقاف/تفعيل.
- **الدخول كمدير المؤسسة** يتم برابط موقّع لمدة دقيقتين على نطاق المؤسسة نفسه (`/platform-access/{user}`) لأن الجلسة تعيش على ذلك النطاق،
  ويُسجَّل في سجل النشاط. الرابط بلا توقيع يُرفض (403).
- **المدفوعات**: قائمة للقراءة فقط من `subscription_payments` مع إجماليات الشهر/السنة/الكل؛ التسجيل يبقى من صفحة المؤسسة حتى تمتد الفترة مع الدفعة.
- **صحة النظام**: نفس فحوصات `rowad:doctor` نُقلت إلى `App\Services\Platform\HealthCheck` وتُعرض في المتصفح مع أرقام المجدول والطابور،
  وتُخزَّن 10 دقائق؛ فحص قواعد كل المؤسسات (بطيء) لا يعمل إلا عند «إعادة الفحص الكامل».
- **مديرو المنصة**: مستخدمو القاعدة المركزية `is_super_admin`؛ لا يمكن للمدير إيقاف نفسه.
- التنقل في ثلاث مجموعات (`App\Filament\Platform\Nav`): المؤسسات، الباقات والترخيص، المنصة. اللوحة الرئيسية على شبكة 12 عمودًا
  برسوم: المؤسسات الجديدة شهريًا (مدفوعة/تجريبية)، الإيرادات الشهرية، توزيع الباقات.
- درس تقني: في `modifyQueryUsing` يجب تسمية المعامل `$query` بالضبط — Filament يحقن المعاملات بالاسم، و`$q` يصل فارغًا فينهار الاستعلام.
- **إضافة متعددة** في قواعد البيانات (المدن، الأحياء، التصنيفات…): لصق اسم في كل سطر، وتُتجاهل الأسماء الموجودة (داخل نفس الأب كالمدينة للأحياء).

## الموارد البشرية (نظام العمل السعودي)

- مجموعة جديدة «الموارد البشرية» تضم الموظفين، الإجازات، الرواتب، السلف، الحسومات والجزاءات (كانت «الحسومات» معزولة في القديم)، ونهاية الخدمة.
- **ملف الموظف**: `salary` يبقى الراتب الأساسي (توافقًا مع القديم) وأُضيفت بدلات السكن والنقل وأخرى، رقم التأمينات، البنك والآيبان، نوع التوظيف والحالة، نهاية العقد.
  «الأجر الشامل» = الأساسي + البدلات وهو أجر نظام العمل (نهاية الخدمة، خصم الإجازة بلا راتب = الأجر الشامل ÷ 30 لكل يوم).
- **التأمينات (`Services\Hr\Gosi`)**: الأجر الخاضع = الأساسي + السكن بين 1,500 و45,000؛ السعودي 9.75% موظف / 11.75% منشأة، غير السعودي 2% أخطار مهنية على المنشأة فقط.
  النسب قابلة للتعديل من الإعدادات العامة → الموارد البشرية (`hr_*`) لأن التأمينات تغيّر النسب للموظفين الجدد تدريجيًا.
  الجنسية تُستنتج من نص الجنسية («سعود»/saudi) أو هوية وطنية إن كانت الجنسية فارغة.
- **الإجازات (`LeaveBalance`)**: سنوية 21 يومًا و30 بعد 5 سنوات (م 109) أو رصيد يدوي لكل موظف؛ مرضية بسلم 30/60/30 (م 117)؛ وضع 84 يومًا، مولود 3، زواج 5، وفاة 5، حج 10.
  الطلب → اعتماد/رفض؛ عند تجاوز الرصيد يُنبَّه المعتمِد ولا يُمنع. الإجازة بلا راتب المعتمدة تُخصم في المسيّر بعدد أيامها داخل الشهر.
- **الرواتب (`Services\Hr\Payroll`)**: مسيّر واحد لكل شهر يُبنى من الموظفين على رأس العمل، مع حسومات الشهر (`for_month/for_year`)، أقساط السلف، الإجازة بلا راتب، وحصتي التأمينات.
  السطور (إضافي، مكافأة، خصومات أخرى…) تُعدّل وهو مسودة، ثم اعتماد (قابل لإعادة الفتح)، ثم صرف: سند صرف لكل موظف بصافي راتبه من الحساب المختار، وتُستقطع أقساط السلف من الرصيد المتبقي.
- **ملف حماية الأجور (WPS)**: ملف CSV بسجل رأس `SIF` وسجل `EDR` لكل موظف (رقم التأمينات/الهوية، SWIFT البنك، الآيبان، الأساسي، السكن، بقية الاستحقاقات، الخصومات، الصافي) — الصيغة التي تقبلها البنوك ومنصة مدد؛ رمز SWIFT أُضيف لجدول البنوك.
- **نهاية الخدمة (`EndOfService`)**: م 84 نصف أجر شهر عن كل سنة من الخمس الأولى ثم أجر شهر، بكسور السنة؛ م 85 عند الاستقالة: لا شيء دون سنتين، ثلث من 2 إلى 5، ثلثان من 5 إلى 10، كاملة بعدها؛
  م 87 كاملة للعاملة المستقيلة خلال 6 أشهر من الزواج أو 3 من الولادة؛ م 80 لا مكافأة. الحساب يُجمَّد في التسوية (`calculation`) لأن الأجر والقانون قد يتغيران، ويُصرف بسند واحد ويُسجَّل الموظف «منتهي الخدمة».
- صلاحيات جديدة (`leave_requests`, `employee_advances`, `payroll` بالاعتماد والصرف والتصدير, `end_of_service`) تُزامَن للمؤسسات القائمة عبر migration يشغّل `RolesAndPermissionsSeeder`.
- مستندات طباعة: قسيمة راتب، المسيّر الكامل، تسوية نهاية خدمة بإقرار استلام.

## Office branding inside the panels (2026-10-16)

- Each office's identity (`logo`, new `brand_color` setting) now reaches the admin panel and both portals:
  the logo (or a monogram built from the office name) sits in the sidebar header on desktop and in the
  topbar on phones; the login page is a split layout with a brand aside. `App\Support\Branding` is the
  single source (`logoUrl()`, `initials()`, `color()`, `css()`).
- The brand colour re-skins Filament at runtime by overriding the `--primary-*` CSS variables (Filament's
  own palette generator, `Color::hex()`) from the HEAD_END render hook, so no per-office asset build is
  needed. Sidebar shades are derived from the same palette. An invalid value is ignored, never emitted.
- The sidebar is narrower (16.5rem) with tighter items, a footer card (subscription state for admins,
  office contact for portal users, and the signed-in user) and a "جديد" quick-create menu in the topbar.
- The platform console keeps the Aqar Nova brand; only tenant panels are re-skinned.

## Landing page hero (2026-10-16)

- The platform home opens with a dark, branded hero: copy and CTAs on the start side, a product
  composition on the other (the demo dashboard in a tilted browser frame, the tenant portal on a phone,
  and three floating cards: collected-this-month KPI, an automatic reminder, the AI assistant).
  The screenshots are real captures of the demo office (`public/images/platform/dashboard.jpg`,
  `client-mobile.jpg`); re-capture them when the panels change.
- A "built for Saudi regulations" strip (ZATCA e-invoicing, 15% VAT, Umm al-Qura calendar, PDPL,
  Labour Law/GOSI) sits under the hero as factual compliance, not logos of third parties.
- The sticky header is dark so it blends with the hero on every public page.

## Welcome email, signup alert and getting-started checklist (2026-10-16)

- After a successful signup the new admin gets `App\Mail\OfficeWelcome` (login link, plan, trial end,
  first steps) and the platform contact email gets `App\Mail\NewOfficeAlert` (office, plan, admin,
  phone, link to the console). Both are queued and wrapped in try/catch: mail trouble never undoes a
  finished signup. The office's own SMTP is not used here; these are platform emails.
- The admin dashboard shows `GettingStarted` (7 steps with live done-state computed from the office's
  data: identity, owner, unit, contract, SMS, team, website) to users who can edit settings, until all
  steps are done or an admin hides it (`onboarding_dismissed` setting). The website step starts done
  because the public site is on by default.

## Self-service cancellation and 30-day retention (2026-10-16)

- An office admin cancels from the subscription page (reason, optional note, typing the office name).
  The office closes at once (status `cancelled`, panels/website/API return 402), trial and paid rows
  become `cancelled`, and `purge_after` = now + 30 days (`SubscriptionManager::RETENTION_DAYS`).
- Coming back is one click: the confirmation email carries a signed link on the office's own domain
  (`/reactivate?tenant=…`, valid until the purge date, outside the accessibility gate). The closed
  page can resend that link to the office email but never shows it. Reactivation restores the rows
  (trial → trialing, paid → active) and the status (active / trial / past_due as a short grace).
  The platform console's "تفعيل" also clears a cancellation.
- `rowad:subscriptions` (daily) emails a reminder three days before the purge and then deletes the
  office (tenancy's DeleteDatabase); nothing is kept after that.
- Reasons are stored in the tenant's `cancellation` data for the platform to learn from.

## E-collection: pay links with Moyasar (2026-10-16) — roadmap phase 1

- Gateway: Moyasar (Saudi; mada, Visa/Mastercard, Apple Pay, STC Pay) through its hosted form; keys are
  per office in settings ("الدفع الإلكتروني" tab, secret and webhook token encrypted at rest). The plan
  feature `online_payments` gates it (professional and enterprise plans by default).
- One `OnlinePayment` per open instalment amount: a random 40-char token makes the public page
  `/pay/{token}` on the office domain. If the instalment changes (partial collection) the link is
  superseded and a fresh one is made; links are built from the office domain so cron reminders get them too.
- Truth comes only from the gateway: the redirect (`/pay/{token}/callback?id=…`) and the webhook
  (`/webhooks/moyasar`, `secret_token` must match) both call `Payments::confirm()`, which fetches the
  payment from Moyasar's API, checks amount and currency, then collects the instalment through
  `VoucherService::createReceipt` (method card, party = the contract's client, optional bank account).
  Idempotent under row locks; a gateway id can settle only one link.
- Where tenants meet the link: reminder templates (`{pay_line}` sentence, empty when e-collection is off;
  existing offices' templates were migrated), the client portal's "ادفع الآن", the mobile API
  (`pay_url`), and the admin instalment list ("رابط الدفع" to copy). Admin log: "المدفوعات الإلكترونية".
- Not in this phase: partial online payments, refunds from the panel, settlement reports from Moyasar.

## WhatsApp Business channel (2026-10-16) — roadmap phase 1, part 2

- Meta Cloud API, configured per office (settings tab "واتساب": phone number id, permanent token
  encrypted at rest, approved template name and language). Business-initiated messages must use an
  approved template, so the office creates one Utility template whose body is a single variable
  ({{1}}); the text rendered from the office's own notification templates (SMS body) is passed in it.
  Newlines are flattened because Meta rejects them inside variables.
- When connected, every notice that would go by SMS is queued as `whatsapp` instead; the delivery job
  falls back to SMS on any WhatsApp failure (not on WhatsApp, template not approved, expired token)
  and keeps the reason on the message. Login/password codes stay SMS (Meta requires separate
  authentication templates). WhatsApp does not count against the plan's SMS quota (Meta bills the
  office directly).
- Campaigns can use WhatsApp once connected (same mobile audience as SMS).
- Zero-setup option for every office: the pay-link window has "إرسال عبر واتساب", a wa.me link that
  opens the staff member's own WhatsApp with the tenant's number and the pay link pre-filled.

## Portals as an installable app (PWA) (2026-10-16) — roadmap phase 3

- No app-store build: each office's admin panel and both portals are installable from the browser.
  `/manifest/{panel}.webmanifest` carries the office name, panel role, brand colour and scope
  (`/client`, `/owner`, `/admin`); the icon (`/app/icon.svg`) is the office monogram on its colour,
  drawn as SVG so Arabic letters render correctly, with the Aqar Nova PNGs as fallback.
- `/sw.js` (scope `/`) is network-first for pages so financial data is never stale; it only falls
  back to `/app/offline` with no network, caches static assets, and never touches non-GET requests
  (Livewire, forms, payments).
- On phones the portals get a native-style bottom tab bar (dashboard first, then what each user opens
  most: tenant — payments, contracts, support; owner — units, statement, collections) and a one-time
  install hint (Android install prompt; iOS "Share → Add to Home Screen" instructions).
- The tenant's payments table puts "ادفع الآن" first and hides secondary columns on small screens so
  paying needs no sideways scrolling.
- Web Push is the next step on top of the service worker (the API already stores device tokens).

## Predictive layer: payment risk and suggested rent (2026-10-16) — roadmap phase 4

- Deterministic and explainable on purpose (no model call, no data leaves the office): staff must be
  able to see why a tenant is flagged or why a price is suggested.
- `Insights\PaymentRisk`: score 0–100 from the tenant's own past-due instalments — arrears beyond a
  5-day grace (weight 30 + up to 20 by age), share of late payments (35), average delay (10),
  open part-payments (5). Levels: low < 30 ≤ medium < 60 ≤ high; fewer than two past-due instalments
  and nothing overdue = "not enough history". Shown on the client page (gauge + reasons), in a dashboard
  list with a call button, and as the `payment_risk` assistant tool.
- `Insights\RentAdvisor`: suggested yearly rent from rent contracts started in the last 24 months on
  comparable units of the same office — same category, rooms ±1 (shops/offices only against units
  without rooms), half to double the area — preferring the district, then the city, then the office
  (first tier with three or more contracts). Median price per m² × area when areas are known, else the
  median rent; the 25–75% range; at least two contracts or no suggestion; confidence by sample size.
  Contracts are annualised from `yearly_rent` or their own instalments. Shown on the unit page with the
  current rent's position in the range, and as the `suggest_rent` assistant tool.

## Owner portfolio page (2026-10-16) — roadmap phase 5, part 1

- Owner portal "أداء أملاكي": per property and in total for a chosen year (to date) — the owner's share
  of collections (ledger `payment`), deductions (`tax`, `commission`, `owner_tax`, `mancost`,
  `outgoings`), net, occupancy (days covered by non-cancelled rent contracts, ended early = ended),
  vacant days, the owner's share of tenant arrears, and the market-rent gap from RentAdvisor.
- Yield needs a value: new optional `units.market_value` (staff fill it in the unit form); yield =
  annualised net ÷ (value × owner share). Without a value the page says so instead of guessing.
- Read-only from data the office already keeps; nothing new for staff to enter beyond the value.

## Multi-branch offices (2026-10-16) — roadmap phase 5, part 2

- Enterprise-plan feature `branches`. A `branches` table and a nullable `branch_id` on users, buildings,
  units, receipt and payment vouchers.
- Scoping is a global scope, so every screen, report, export, API call and assistant tool obeys it
  without per-screen code: staff with a branch see only rows of their branch; contracts and
  instalments follow their unit (`whereHas('unit')`, which applies the unit's own scope). Staff
  without a branch, super admins, portal users, queued jobs and the scheduler see everything.
- New rows take the creator's branch; otherwise they follow their unit or building, so a voucher
  posted by the payment webhook (no user) still lands in the unit's branch.
- Owners and clients stay shared across branches (one person can deal with several branches).
- Only staff without a branch manage branches; deleting a branch leaves its rows unassigned.

## ZATCA e-invoicing Phase 2 — integration (2026-10-16) — roadmap phase 2

- Built in-house (no third-party SDK or middleware): secp256k1 key + CSR with ZATCA's EGS extensions,
  UBL 2.1 XML, invoice hash (C14N without UBLExtensions/Signature/QR), XAdES enveloped signature,
  TLV QR with tags 1–9. Verified end to end against ZATCA's real sandbox: compliance CSID, all six
  compliance documents (standard/simplified × invoice/credit/debit) PASS, production CSID, clearance.
- One e-invoicing unit (EGS) per office (`zatca_devices`); the private key and secrets are stored
  encrypted with the app key. Reconnecting creates a new unit and revokes the old one.
- Onboarding is one screen (الإعدادات ← الفوترة الإلكترونية): the office pastes the OTP from the
  Fatoora portal and the system does the rest. It refuses to start until the seller profile is
  complete (name, VAT, CR, national address: street, 4-digit building, district, city, 5-digit postal).
- Every invoice is signed when it is issued, inside a row lock on the unit, so the counter (ICV) and
  previous-hash chain never fork. Sending happens after commit in a queued job; the scheduler
  (`rowad:zatca`, every 15 minutes) retries `pending`/`failed` documents in ICV order, up to 20 tries.
- Simplified (B2C, no buyer VAT number) → reporting; standard (B2B) → clearance, and ZATCA's stamped XML
  and QR replace ours for printing. HTTP 400 = rejected (kept, never resent: fix with a credit note and
  a new invoice); 409 = already accepted; anything else = failed and retried.
- Issued invoices are never edited or deleted (the bulk delete was removed). Corrections are credit /
  debit notes (`invoices.credit_note` permission) with the original's number and a mandatory reason.
- Residential rent is VAT-exempt (category E, VATEX-SA-30); commercial rent is 15%.
- Developer-portal environment: ZATCA answers every production-CSID request there with the same fixed
  sample certificate (another key), so documents signed with it can never verify. In that environment
  the unit keeps its compliance certificate and sends documents to the compliance-check endpoint, which
  validates them with the same rules. Simulation and production use the production CSID as normal.
- Offices without the plan feature `zatca` or without a connected unit keep Phase 1 (QR on the print).

## Maintenance requests (2026-10-16) — vision §ب "صيانة"

- New module available on every plan (core to property management, not an upsell). Codes `MNT-YYYY-NNNNN`.
- Flow: tenant reports from the portal/PWA with up to 6 photos (or staff open one) → the office books a
  technician and visit time (tenant notified by SMS/WhatsApp/email) → in progress → completed.
- Owner approval: setting "حد موافقة المالك على الصيانة". An estimate at or above it waits for the unit's
  owners in their portal (they are notified). A refusal returns the request to "new" so the office can
  find a cheaper quote or bill someone else. Empty limit = owners are never asked.
- Billing reuses the existing payment-voucher machinery, so accounting stays in one place: completing
  with a cost issues a payment voucher to the technician and charges it to the owners by expenses share
  (`outgoings`), to the tenant's current contract (an expenses instalment) or to the office (no charge).
- The tenant rates the finished job once (1–5); ratings of 2 or less alert the office.
- Photos are private: served only to staff, the reporting tenant and the unit's owners.
- Requests follow the unit's branch scope. New notification events `maintenance_update` (tenant) and
  `maintenance_approval` (owner) with editable templates.

## Ejar documentation (2026-10-16) — roadmap phase 2, part 1

- Ejar's integration API is only available to licensed partners under an agreement with the platform,
  so it cannot be built or tested without the owner's credentials. What every office can use today:
  - Each rent contract carries an Ejar status (not documented → awaiting the parties → documented, or
    "not required" with a reason), the Ejar contract number, date and the documented PDF.
  - "بيانات التوثيق في إيجار": a sheet of every field Ejar asks for — brokerage office (CR and FAL
    licence, a new setting), each lessor and lessee (ID type/number, date of birth, mobile; companies by
    CR), the property (deed, city, district, unit number, area, electricity meter) and the contract
    terms — with a copy button per field, so staff never retype. Empty required fields form a readiness
    checklist on the contract page.
  - Date of birth (Ejar verifies parties with it) added to owners, clients and employees.
  - Tenants and owners see the Ejar status and number in their portals and can download the PDF.
  - The contracts list has an Ejar column and filter; the dashboard counts active contracts not yet
    documented.
- When the owner obtains Ejar API access, submission plugs in behind the same sheet (it already holds
  the payload) and the status fields; no screen changes are needed.

## Electronic signature of rent contracts (2026-10-16) — vision §د

- Staff press "طلب التوقيع الإلكتروني" on a rent contract; every owner and tenant gets a private link
  (48 random characters, only its SHA-256 is stored, valid 14 days) by SMS/WhatsApp/email.
- The public page shows the full contract (the office's own print design) and the parties' status.
  The signer proves who they are with a 6-digit code sent by SMS (never WhatsApp) to the mobile the
  office has on file — 10 minutes, 5 attempts, 1-minute resend gap — then draws their signature and
  ticks an acknowledgement. Rate limits are per link, so parties on one office network don't collide.
- Evidence kept per signature: the drawing (PNG), time, IP, user agent and a SHA-256 fingerprint of the
  terms (parties, unit, dates, amounts, schedule, clauses). Requesting again skips parties who signed
  the same terms; if the terms changed, everyone signs again.
- When the last party signs, the contract's `signed_at` is set and staff are notified. Signatures print
  in the signature boxes of the default contract design (new placeholders `{owner_signature}` and
  `{renter_signature}`, added automatically to offices still on the default design), and the QR
  verification page lists who signed and when.
- Tenants and owners can also start signing from their portal ("وقّع العقد"), which issues a fresh link.
- A party can decline with a reason; the office is notified.
- This is an e-signature with identity verification by mobile code, not a Nafath-backed signature;
  Nafath would need a government integration agreement.

## Collection ladder for late rent (2026-10-16) — vision §ب "روبوت التحصيل"

- Off by default (Settings ← التحصيل): turning it on starts sending messages to tenants, so each office
  opts in. Days are configurable (defaults 3 / 10 / 20 days late).
- Stage 1: first late notice (template `rent_overdue`, with the pay link when e-collection is on).
  Stage 2: firmer notice (`rent_overdue_2`) and a follow-up task (with the tenant's mobile) for the office
  admin — employees aren't linked to user accounts, so tasks go to the admin.
  Stage 3: a formal warning, issued as an official letter (existing letters module and print design)
  listing every late instalment of the contract with a 7-day deadline; the tenant gets `rent_warning`
  and staff are alerted.
- Only active rent contracts, never expenses instalments; an instalment climbs at most one stage a day,
  so switching it on never jumps straight to a warning. Sale contracts keep their existing daily
  overdue reminders.
- Staff can issue the warning manually from an instalment row; the ladder stage shows (and links to
  the letter) in the instalments list, with a filter.

## Layout density and speed (2026-10-16) — owner feedback

- Panels use the full screen width (`maxContentWidth(Full)`): wide tables no longer scroll beside empty
  margins. Table rows are dense (Filament padded both the cell and the text; now ~7px per row edge),
  roughly doubling the rows on screen. Lists merge related values (period, amount + cycle, next
  payment + amount) instead of one column each; secondary row actions live in the "⋮" menu.
- Speed: SPA navigation (page changes swap the content only, links prefetch on hover), the bell's
  database notifications render with the page instead of a second request, the Cairo font is
  self-hosted (no fonts.bunny.net on any page or print) and avatars are local initials (no
  ui-avatars.com). Measured locally: sidebar navigation ~0.5s instead of ~1.2s, zero third-party
  requests. DB work is ~10ms a page; production servers must run opcache and `php artisan optimize`
  (already in the deployment guides) — the remaining local time is PHP without opcache.
- Record pages (rent contract, unit): a summary card with the figures people look for first, then
  tabs (overview / Ejar & signature / terms / history for contracts; overview / photos & location /
  deeds / owners & management / history for units), with the related table (payments, reports) below.

## Viewing appointments (2026-10-16) — vision §د "حجز معاينة"

- On by default for every listed unit (offices switch it off in Settings ← المعاينات). Defaults:
  Saturday–Thursday, 16:00–21:00, 30-minute slots, at least 2 hours' notice, 14 days ahead.
- One live booking per unit per slot, checked under a lock; a mobile can hold at most 3 upcoming
  website bookings (spam guard, plus the honeypot and a rate limit).
- `starts_at` is stored as the office's wall-clock time (Asia/Riyadh unless the office set another
  timezone): the slot grid, the screens and the SMS all show the same time without conversion.
- Messages: confirmation (`viewing_booked`, with the unit's map link), a same-day reminder from the
  daily jobs (`viewing_reminder`) and cancellation (`viewing_cancelled`, with the reason).
- Staff mark the outcome (viewed / no-show), reschedule, cancel, or save the visitor as a client
  (matched by mobile, so returning visitors aren't duplicated). Staff can also book on the phone,
  outside the website hours.
- The public form works without JavaScript (all days listed); with it, one day at a time.

## Reading IDs and deeds from photos (2026-10-16) — vision §ب "قراءة الصكوك والهويات"

- Client and owner forms: "تعبئة من صورة الهوية" (national ID, iqama, GCC ID, passport) fills the
  four-part Arabic name, ID type and number, nationality, date of birth and place of issue. A
  Hijri-only birth date is converted (Umm al-Qura); Arabic-Indic digits become Western digits.
- Unit form: "تعبئة من صورة الصك" fills plan, plot and area and adds the deed (number, Hijri date,
  issuer) to the deeds list unless it is already there.
- Images or PDFs up to 5 MB go to the AI service (vision) and are not stored; fields are only filled,
  never saved, so staff review first. The model is told to leave unclear fields empty.
- Each reading counts as one assistant question against the plan's monthly AI quota and needs the
  plan feature `ai`, like the other AI tools.

## Importing from Excel (2026-10-16) — vision §ج "استيراد من Excel"

- Settings ← استيراد البيانات, for staff who can change settings. Four steps in order: owners →
  clients → units → running rent contracts, each with a downloadable Arabic template (English
  headers when the panel is in English; both are accepted). Files: .xlsx or UTF-8 CSV, first sheet,
  up to 5,000 rows.
- Every file is previewed first: each row is checked and its problems listed with the Excel row
  number. Importing saves the ready rows, each in its own transaction, and reports the rest.
- No duplicates on re-upload: people are matched by ID number, then mobile; units by name within the
  building. Matches are left as they are unless "update existing" is on. Contracts are never
  overwritten: one already running on the same unit from the same start date is skipped.
- Units create their building, city and district the first time they appear. The owner is found by
  ID number, else by full name, else created from the name in the row.
- Contracts go through the normal contract service, so the payment schedule, unit status and
  numbering match a hand-entered contract. Rules:
  - The term is read from the dates: whole years, or whole months when paid monthly.
  - Hijri dates make a Hijri contract; a Hijri end date one day short is accepted, because
    calendars differ on month lengths.
  - Contracts that have already ended are refused.
  - An Ejar number marks the contract as documented.
- Imported contracts message nobody (a new `notify` flag on `createRent`).
- The "past payments collected" option closes instalments due before today without receipts or owner
  ledger entries, since that money was settled in the old system.

## Owner payouts (2026-10-16) — vision §ب "توزيع المالك تلقائيًا"

- Owners get a transfer account (IBAN, bank, beneficiary name when it isn't the owner's own). The IBAN
  is checked (mod-97; Saudi IBANs must be SA + 22 digits) and the bank is recognised from its code.
  The owners Excel template takes it too.
- المالية ← توزيع مستحقات الملاك. A run lists every owner with a positive net balance up to a date:
  open dues (rent shares) minus open deductions (commission, expenses charged to them, taxes). Owners
  with a valid IBAN are ticked; owners without one are shown with a link to add it and cannot be paid.
- Creating the run issues one payment voucher per owner (transfer, from the chosen office account)
  and settles the entries it pays. Expense charges stay linked to their expense voucher and are only
  marked settled; every settled entry is recorded on the run line, so cancelling reopens exactly those.
- Bank file: Excel (or UTF-8 CSV) with beneficiary, IBAN, bank, SWIFT, amount, reference and purpose,
  for the bank's bulk-transfer upload. Each Saudi bank's portal has its own template; this is the common
  set of columns they ask for. A direct bank API needs an agreement with the bank, which is the
  owner's call.
- Owners are messaged (the existing owner_payout template) only when staff mark the run transferred,
  not when it is prepared. After that it can't be cancelled; until then cancelling deletes the vouchers
  and reverses the bank movement.
- Permissions: owner_payouts.view_any / view / create / transfer / cancel (included in the accountant
  role).

## The public demo office: fenced and self-cleaning (2026-10-16)

- Shared by every visitor, so nothing in it reaches the outside world:
  - Messages are never sent (already the case).
  - Mail, SMS, payment and WhatsApp connection settings are not saved.
  - The subscription can't be cancelled and upgrade requests can't be sent.
  - Office backups, ZATCA onboarding and deleting the three shared accounts are blocked. Their
    sign-in details, admin flag and branch can't be changed either.
- The assistant is limited to 10 questions an hour per visitor (IP) there, so the shared monthly AI
  quota lasts.
- Self-cleaning: any real change by a visitor marks the demo changed (signing in doesn't count). Every
  5 minutes `rowad:demo:refresh` rebuilds it from scratch (about 10 seconds) once it has been changed
  and nobody has used it for 20 minutes. The next visitor always finds it as new, and nobody is reset
  in the middle of a visit.
  - On by default (`DEMO_AUTO_RESET=true`). The nightly rebuild (`DEMO_RESET_DAILY`) can stay on as a
    safety net.
- Every demo panel shows a banner saying so, with a link to sign up a real office.
- Still possible by design: visitors see each other's changes while they overlap. Isolating each
  visitor in their own copy would need one database per visitor, and isn't worth it at this stage.

## AI key from the platform console; platform sign-in page (2026-10-16)

- Platform settings ← الذكاء الاصطناعي: the platform owner enters the Anthropic key and the model
  there, without touching the server.
  - The key is stored encrypted (APP_KEY) in the central settings table. It never goes back to the
    browser: the field stays empty and says a key is saved.
  - A toggle removes the key.
  - "Save and test" sends a 5-token request, so a wrong key or model shows up immediately rather than
    in an office.
  - `.env` (ANTHROPIC_API_KEY / AI_MODEL) stays the fallback, so existing installations keep working.
  - If APP_KEY changes, the stored key can't be decrypted. The system then falls back to `.env` until
    the key is entered again.
- The model field suggests claude-sonnet-5-5 (balanced, recommended), claude-opus-5-5 (highest quality)
  and claude-haiku-5-5 (fastest and cheapest). Any other model ID can be typed.
- The platform console's sign-in page had no side panel (that panel is drawn for office panels only),
  which left an empty half-screen. It now has its own Aqar Nova panel, a heading for the operations
  team, and a note sending office users to their office's link.

## Office website redesign; units for sale on it (2026-10-16)

- The website now lists every published, available unit, for rent and for sale. Until now it showed
  rentals only.
  - Sale units get an asking price (`units.sale_price`, a field shown when the offer is "for sale").
  - The publishing toggle says units disappear automatically once rented or sold.
- Home page:
  - A hero that uses the office's own photo (new setting "صورة الواجهة" in website settings),
    otherwise the featured unit's photo, otherwise a gradient. It shows live counts (available /
    for rent / for sale / cities).
  - A search card with For rent / For sale tabs and labelled fields.
  - Results with type chips and sorting by price. Price filters and sorting compare the asking price
    for sale units and the rent for the others.
  - Unit cards with an offer badge, a featured badge, photo count, location, icon features, and the
    price with its period.
  - A "why us" section, the office's about text, and a contact section with call / WhatsApp / email /
    address cards beside the form.
  - A footer with portal links.
- Unit page:
  - Breadcrumb, a photo gallery with a full-screen viewer, and a specifications grid.
  - A price card that stays in view while scrolling, with book-a-viewing, WhatsApp (pre-filled with
    the unit's name and link) and call buttons, plus the office card.
  - A share button (system share sheet, or copy the link) and similar units of the same offer type.
  - On phones, a bottom action bar with the price, WhatsApp, call and book.
  - Open Graph tags so shared links show the unit's photo.
- The demo office gets two units for sale and a drawn city-skyline hero image.

## AI providers: OpenAI and Gemini beside Claude (2026-10-16)

- Platform settings ← الذكاء الاصطناعي:
  - Choose the provider in use (Claude, OpenAI or Google Gemini).
  - Each provider has its own key (encrypted, never shown again) and model, so all three can be stored
    and switched at any time.
  - `.env` stays the fallback (AI_PROVIDER, ANTHROPIC_API_KEY / OPENAI_API_KEY / GEMINI_API_KEY and
    their models).
  - OPENAI_BASE_URL lets an OpenAI-compatible endpoint (e.g. Azure) be used.
- The assistant code keeps speaking the Anthropic Messages format. Adapters translate requests and
  replies:
  - OpenAI Chat Completions: images and PDFs as data URLs, tools as functions, tool results as "tool"
    messages, `max_completion_tokens`.
  - Gemini generateContent: inline data, function declarations, function responses matched back by
    name, thought signatures carried with function calls, thoughts dropped from answers.
  - Tool calls, document reading and writing help therefore work the same with any provider.
- Suggested models: gpt-5-mini / gpt-5 / gpt-5-nano and gemini-2.5-flash / -pro / -flash-lite. Model names
  change, so any ID can be typed, and "Save and test" checks it at once.

## App shell and command search (Ctrl+K) (2026-10-16)

- Layout follows the owner's reference:
  - A deep-navy sidebar that runs the full height of the screen. The office colour no longer paints
    the whole sidebar; it only tints it, and carries the accents (active item bar, icons, buttons).
  - A "filter the menu" box at the top of the sidebar. It is forgiving of hamza / taa marbuta, and
    Enter opens the first match.
  - The page's breadcrumbs and title sit in the topbar on desktop.
  - The page header became a card with the section's icon (taken from the active menu item), the
    title and the page's actions. A header with nothing but a title is hidden on desktop, since the
    title is already in the topbar.
  - Topbar end: quick "new", the search box with a Ctrl K / ⌘K badge, full screen, notifications,
    the assistant, and the user's name and role beside the avatar (it opens the user menu). The user
    row moved out of the sidebar footer, which now keeps only the plan / office contact.
- The top search box is a command palette (`App\Filament\Support\CommandSearch`):
  - Ctrl+K / ⌘K focuses it from anywhere.
  - "Pages & actions" come first: navigation items by name or section, and "new …" for every resource
    the user may create. "اضافة وحدة" finds "إضافة وحدة"; "جديد" alone lists what can be added.
  - Then records, five per section: units (title, number, deed, building), owners, clients, employees
    (name, ID, mobile…), rent and sale contracts (number, unit, tenant / buyer), receipt and payment
    vouchers, invoices, buildings and viewings. Each result has a one-line summary.
  - Everything respects permissions: a section or action the user can't open never shows.
- Calculator and dark-mode buttons from the reference were left out: the panels are designed for the
  light surface (dark mode is off), and a calculator adds little next to the amounts the system works
  out itself.

## Accounting: chart of accounts, journal, trial balance and statements (2026-10-16)

- Double entry in every office's database: `accounts` (the chart), `journal_entries` and `journal_lines`.
  Money columns are decimal(14,2).
- Starting chart for a real-estate office:
  - Assets: cash, banks (one sub-account per bank account, created automatically), tenants
    receivable, employee advances, input VAT.
  - Liabilities: owners' funds held in trust, output VAT, other taxes, salaries, GOSI.
  - Equity, revenue (rental / sale commissions, management fees, other income) and expenses.
  - The office can add and rename accounts. System accounts and accounts with entries can't be
    deleted; a sub-account's number starts with its heading's number.
- Cash basis, posted automatically. Each document re-posts its entry when it, or something linked to
  it, changes, after its transaction commits. A deleted document loses its entry; the entry keeps its
  number across re-posts.
  - Receipt: cash/bank against output VAT, the tenant's / buyer's commission, re-billed expenses, and
    the rest to the owners' trust account. A receipt unrelated to rent or owners goes to the account
    chosen on it (default: other income).
  - Payment: cash/bank against expenses charged to owners (trust account) or to the tenant
    (receivable), payouts to owners, employee advances, salaries, input VAT, and the rest to the
    account chosen on it (default: general expenses).
    - Salaries post the full cost: salaries are debited with net pay + advances recovered + GOSI.
      Advances are cleared, and GOSI (both shares) becomes a liability.
  - Owner ledger: commission and management fees move from the trust account to revenue; taxes on
    owners go to taxes payable; the office's income tax is an expense.
  - Bank account: its opening balance against capital.
  - Rent and sale money is the owners', so it never shows as office revenue; only commissions and
    fees do.
  - Invoices aren't posted: they document a sale, and the money is posted when its receipt is issued.
  - An installment collected without a receipt moves no money in the books.
- Manual entries (opening balances, accruals, corrections) must balance, use postable accounts and
  have at least two lines. Only manual entries can be edited or deleted; automatic ones follow their
  document.
- Reports, all with a period, printable and limited to the current branch when one is selected:
  - Trial balance: opening / period / closing, headings rolled up; it flags an imbalance.
  - Account ledger: running balance; a heading includes its sub-accounts.
  - Income statement, and balance sheet: unclosed profit is shown under equity.
- `rowad:accounting:rebuild {office?}` re-posts every document (manual entries are kept). The
  migration runs it once, so offices with history get their books on upgrade; the demo rebuilds after
  seeding.
- Permissions: `accounts.*`, `journal_entries.*`, and `accounting.trial_balance | ledger | statements |
  rebuild`. Admins and accountants have them; agents don't.
- Not done yet: closing a year into retained earnings, locking closed periods, and cost centres.
