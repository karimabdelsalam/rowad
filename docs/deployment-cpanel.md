# دليل التشغيل على استضافة cPanel

دليل تشغيل عقار نوفا على `aqarnova.com` في حساب cPanel. المنصة على `aqarnova.com`، وكل مؤسسة على `اسمها.aqarnova.com` بقاعدة بيانات خاصة بها
تُنشأ تلقائيًا عبر واجهة cPanel البرمجية (UAPI).

> في الأوامر: `USER` اسم حساب cPanel، و`<...>` قيم تختارها أنت.

## 0. تأكد من هذه في الاستضافة قبل البدء

| المطلوب | لماذا | أين تجده |
|---|---|---|
| **Terminal / SSH** | لتشغيل `composer` و`php artisan` | Advanced → Terminal |
| **PHP 8.3 أو 8.4** مع `intl gd zip mbstring pdo_mysql fileinfo exif curl` | متطلبات النظام | Software → MultiPHP Manager / Select PHP Version |
| **عدد قواعد MySQL غير محدود** (أو كبير) | **كل مؤسسة = قاعدة بيانات**، وقاعدة للمنصة | Statistics في الصفحة الرئيسية: MySQL® Databases |
| **Cron Jobs** | التذكيرات اليومية وإرسال الرسائل | Advanced → Cron Jobs |
| **Wildcard subdomain** | نطاقات المؤسسات `*.aqarnova.com` | Domains |

إذا كان عدد قواعد البيانات محدودًا (مثلًا 10)، اطلب من الاستضافة رفعه أو انتقل لباقة أعلى أو VPS — بدونه يتوقف التسجيل عند الحد،
ويظهر للمسجّل "تعذر إنشاء حساب المؤسسة" دون أن يُترك أي حساب ناقص.

## 1. رفع الكود

من **Terminal**:

```bash
cd ~
git clone https://github.com/karimabdelsalam/rowad.git aqarnova
cd aqarnova
composer install --no-dev --optimize-autoloader
```

إذا لم يتوفر `composer` على السيرفر: `curl -sS https://getcomposer.org/installer | php` ثم استخدم `php composer.phar install --no-dev --optimize-autoloader`.

> الكود خارج `public_html` عمدًا، فلا يمكن الوصول لملفات الإعداد من المتصفح.

## 2. ربط النطاق بمجلد `public`

النطاق الرئيسي في cPanel يقرأ دائمًا من `public_html`، لذا نجعله اختصارًا لمجلد `public` في المشروع:

```bash
cd ~
mv public_html public_html.old      # احتفظ بالقديم احتياطًا
ln -s ~/aqarnova/public public_html
```

ثم من **Domains → Create A New Domain**:
- النطاق: `*.aqarnova.com`
- **ألغِ** "Share document root" واكتب المجلد: `aqarnova/public`

بهذا تذهب `alrowad.aqarnova.com` وغيرها لنفس التطبيق، وهو يتعرف على المؤسسة من النطاق.

## 3. الـ DNS وشهادة SSL

في **Zone Editor** لـ `aqarnova.com` تأكد من وجود:

| النوع | الاسم | القيمة |
|---|---|---|
| A | `aqarnova.com.` | IP الاستضافة |
| A | `www` | IP الاستضافة |
| A | `*` | IP الاستضافة |

**الشهادة:** `aqarnova.com` و`www` تغطيهما AutoSSL تلقائيًا (SSL/TLS Status → Run AutoSSL).
أما `*.aqarnova.com` فتحتاج شهادة Wildcard، ولها طريقان:

1. **إذا كانت نيمسيرفرات النطاق عند الاستضافة نفسها:** AutoSSL بمزود Let's Encrypt يصدر Wildcard تلقائيًا في أغلب الاستضافات. تحقق من SSL/TLS Status أن `*.aqarnova.com` ظهر بقفل أخضر.
2. **الأسهل والمضمون: Cloudflare (مجاني).** انقل نيمسيرفرات النطاق إلى Cloudflare، أضف السجلات الثلاثة أعلاه مع تفعيل البرتقالي (Proxied)،
   واجعل SSL/TLS على **Full**. Cloudflare يغطي `*.aqarnova.com` مجانًا. ثم أضف في `.env`: `TRUSTED_PROXIES=*`.

## 4. قواعد البيانات ومفتاح cPanel

1. **MySQL® Databases:**
   - أنشئ قاعدة: `USER_aqarnova` (قاعدة المنصة).
   - أنشئ مستخدمًا: `USER_app` بكلمة مرور قوية.
   - أضف المستخدم للقاعدة مع **ALL PRIVILEGES**.
2. **Security → Manage API Tokens → Create:** الاسم `aqarnova-tenancy`، وانسخ المفتاح (يظهر مرة واحدة).
   النظام يستخدمه فقط لإنشاء قاعدة كل مؤسسة جديدة ومنح `USER_app` صلاحيتها، وحذفها عند حذف المؤسسة.
   إذا كانت الاستضافة تعطّل هذه الميزة (صفحة API Tokens غير موجودة)، فلا حاجة للمفتاح: اجعل `.env` يحتوي
   `CPANEL_UAPI_DRIVER=cli` فيستدعي النظام أمر `uapi` المحلي الذي يعمل بصلاحية الحساب نفسه.

## 5. ملف `.env`

```bash
cd ~/aqarnova
cp .env.example .env
php artisan key:generate
```

عدّل `.env` (من File Manager أو `nano .env`):

```dotenv
APP_NAME="Aqar Nova"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://aqarnova.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=USER_aqarnova
DB_USERNAME=USER_app
DB_PASSWORD=<كلمة-مرور-المستخدم>

CENTRAL_DOMAINS=aqarnova.com,www.aqarnova.com
TENANT_BASE_DOMAIN=aqarnova.com

# قواعد المؤسسات عبر cPanel
TENANCY_DB_MANAGER=cpanel
TENANCY_DB_PREFIX=USER_t
CPANEL_USER=USER
CPANEL_API_TOKEN=<المفتاح-من-الخطوة-4>
# إذا كانت الاستضافة تعطّل "Manage API Tokens": استخدم أمر uapi المحلي بدل المفتاح
# CPANEL_UAPI_DRIVER=cli

# لا Supervisor على cPanel: الطابور يعمل من الـ cron كل دقيقة
QUEUE_WORKER=scheduler
QUEUE_CONNECTION=database
SESSION_DRIVER=file
CACHE_STORE=file
SESSION_SECURE_COOKIE=true
LOG_LEVEL=warning
# إذا كانت الاستضافة تعطّل "Cron Jobs": رمز رابط المجدول الخارجي (انظر قسم 7)
# CRON_TOKEN=<رمز-عشوائي-طويل>

# البريد: أنشئ noreply@aqarnova.com من Email Accounts
MAIL_MAILER=smtp
MAIL_HOST=mail.aqarnova.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=noreply@aqarnova.com
MAIL_PASSWORD=<كلمة-مرور-البريد>
MAIL_FROM_ADDRESS=noreply@aqarnova.com

PLATFORM_ADMIN_EMAIL=<بريدك>
PLATFORM_ADMIN_PASSWORD=<كلمة-مرور-قوية>

# المساعد الذكي
ANTHROPIC_API_KEY=<مفتاح>
AI_MODEL=<رقم-النموذج>

# فقط إذا استخدمت Cloudflare
# TRUSTED_PROXIES=*
```

> `TENANCY_DB_PREFIX` يجب أن يبدأ باسم الحساب وشرطة سفلية (`USER_`)، وهذه قاعدة cPanel لأسماء القواعد.

## 6. التثبيت

```bash
cd ~/aqarnova
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan filament:assets
php artisan optimize
php artisan filament:optimize
```

إذا ظهر أن `php` إصدار قديم في الـ Terminal، استخدم المسار الكامل لإصدار 8.3، مثلًا `/opt/cpanel/ea-php83/root/usr/bin/php artisan ...`
(أو `/usr/local/bin/ea-php83`).

بعدها:
- افتح `https://aqarnova.com` — الصفحة الرئيسية.
- ادخل `https://aqarnova.com/platform` ببريد وكلمة مرور `PLATFORM_ADMIN_*`، ثم احذف `PLATFORM_ADMIN_PASSWORD` من `.env`.
- جرّب التسجيل من الصفحة الرئيسية؛ يجب أن تظهر قاعدة جديدة `USER_t...` في MySQL® Databases.

### الحساب التجريبي وبيانات التواصل

```bash
php artisan rowad:demo
```

ينشئ `demo.aqarnova.com` ببيانات تجريبية، ويظهر قسم "جرّب النظام الآن" في الصفحة الرئيسية بأزرار دخول بضغطة واحدة:

| الحساب | البريد | كلمة المرور |
|---|---|---|
| لوحة المؤسسة | `admin@demo.test` | `demo-password` |
| بوابة المالك | `owner@demo.test` | `demo-password` |
| بوابة المستأجر | `client@demo.test` | `demo-password` |

الحساب التجريبي لا يرسل رسائل حقيقية، ولا يمكن تغيير بريد أو كلمة مرور حساباته. أضف في `.env`:

```dotenv
DEMO_RESET_DAILY=true                  # يُعاد بناؤه كل ليلة 4 فجرًا
PLATFORM_CONTACT_EMAIL=info@aqarnova.com
PLATFORM_CONTACT_PHONE=<رقم-الشركة>
PLATFORM_WHATSAPP=9665XXXXXXXX
```

## 7. المهام المجدولة (Cron Jobs)

في **Cron Jobs** أضف مهمة واحدة كل دقيقة (Once Per Minute):

```
cd ~/aqarnova && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

(استبدل مسار `php` بمسار إصدار 8.3 إن لزم.) هذه المهمة وحدها تشغّل:
إرسال الرسائل والبريد من الطابور كل دقيقة، التذكيرات اليومية 3 فجرًا، تذكيرات وانتهاء الاشتراكات، وقياس استهلاك المؤسسات كل ساعة.

**إذا كانت الاستضافة تعطّل ميزة Cron Jobs:** استخدم مجدولًا خارجيًا مجانيًا مثل cron-job.org يطلب كل دقيقة
`https://aqarnova.com/system-cron/<CRON_TOKEN>` بعد تعيين `CRON_TOKEN` (رمز عشوائي طويل) في `.env`. الرابط يشغّل الـ scheduler نفسه.

## 8. إعدادات PHP

**MultiPHP INI Editor** للنطاق:

| الإعداد | القيمة |
|---|---|
| `memory_limit` | `512M` |
| `upload_max_filesize` | `50M` |
| `post_max_size` | `55M` |
| `max_execution_time` | `180` (إجابات المساعد الذكي قد تأخذ أكثر من دقيقة) |

## 8.5 فحص الجاهزية

بعد التثبيت وبعد كل تحديث:

```bash
cd ~/aqarnova && php artisan rowad:doctor
```

يعرض جدولًا بكل فحص: ✓ جاهز، ! تنبيه، ✗ يجب إصلاحه — مع ما يجب عمله بالضبط (إعدادات `.env`، مفتاح cPanel وحصة قواعد البيانات،
تحديثات القواعد، الـ Cron، الطابور، البريد، الصلاحيات، الذكاء الاصطناعي، الحساب التجريبي). لا تطلق قبل أن تختفي كل علامات ✗.

## 9. النسخ الاحتياطي

- نسخ cPanel (أو JetBackup إن وُجد) تشمل كل قواعد `USER_*` ومجلد المشروع — تأكد أنها مفعّلة يوميًا وتُحفظ خارج السيرفر.
- كل مؤسسة تستطيع تنزيل نسخة من بياناتها من إعداداتها.

## 10. التحديث لاحقًا

```bash
cd ~/aqarnova && git pull
composer install --no-dev --optimize-autoloader
php artisan down
php artisan migrate --force
php artisan tenants:migrate --force
php artisan optimize && php artisan filament:optimize
php artisan up
```

## 11. نقل مؤسسة من نظام رواد السابق

1. أنشئ المؤسسة من `/platform` أو التسجيل.
2. أنشئ قاعدة مؤقتة `USER_legacy` وأضف لها `USER_app`، واستورد ملف القاعدة القديمة من **phpMyAdmin**، وارفع مجلد `media` القديم.
3. من Terminal:

```bash
cd ~/aqarnova
LEGACY_DB_PASSWORD=<كلمة-مرور-USER_app> php artisan rowad:import-legacy alrowad.aqarnova.com \
  --host=localhost --database=USER_legacy --username=USER_app --media=/home/USER/legacy-media
```

4. راجع الأعداد مع النظام القديم ثم احذف `USER_legacy`.

## قائمة التحقق

- [ ] `php artisan rowad:doctor` بدون أي ✗.
- [ ] `APP_DEBUG=false`، والصفحة الرئيسية تفتح على `https://aqarnova.com`.
- [ ] `https://test.aqarnova.com` يفتح بقفل أخضر (Wildcard SSL) ويعرض صفحة "لا توجد مؤسسة بهذا الرابط".
- [ ] التسجيل ينشئ قاعدة `USER_t...` جديدة ويدخل لوحة المؤسسة.
- [ ] الـ Cron يعمل: بعد دقيقة من إرسال رسالة تجريبية تظهر "مرسلة".
- [ ] النسخ الاحتياطي اليومي مفعّل ويُحفظ خارج السيرفر.
- [ ] **تغيير كلمة مرور قاعدة النظام القديم** التي كانت مكشوفة في ملفاته.
