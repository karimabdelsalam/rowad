# دليل التشغيل على السيرفر

دليل تشغيل Aqar Nova على سيرفر Linux واحد (Ubuntu 24.04). المنصة على نطاق رئيسي (مثل `aqarnova.sa` — مثال، يُستبدل بالنطاق الفعلي)، وكل مؤسسة على نطاق فرعي
(`alrowad.aqarnova.sa`) بقاعدة بيانات خاصة بها.

> الأوامر بين `<...>` قيم تختارها أنت. الأوامر تُنفذ داخل مجلد `saas/`.

## 1. المتطلبات

| العنصر | الإصدار |
|---|---|
| PHP | 8.3 أو 8.4 مع: `mbstring intl gd zip curl xml pdo_mysql exif fileinfo` (و`redis` إن استُخدم Redis) |
| قاعدة البيانات | MySQL 8 أو MariaDB 10.11+ |
| خادم الويب | Nginx + PHP-FPM |
| أخرى | Composer 2، Git، Supervisor (لعامل الطوابير)، Certbot |

Node.js غير مطلوب للتشغيل (يُستخدم فقط لسكربت لقطات الشاشة).

## 2. النطاقات وشهادة SSL

في لوحة النطاق أضف:

| النوع | الاسم | القيمة |
|---|---|---|
| A | `aqarnova.sa` | IP السيرفر |
| A | `www` | IP السيرفر |
| A | `*` | IP السيرفر (لكل نطاقات المؤسسات الفرعية) |

الشهادة يجب أن تكون Wildcard (`*.aqarnova.sa`)، وهذه لا تصدر إلا بتحقق DNS:

```bash
sudo certbot certonly --manual --preferred-challenges dns -d aqarnova.sa -d '*.aqarnova.sa'
```

الأفضل استخدام إضافة Certbot الخاصة بمزود النطاق (Cloudflare مثلًا: `python3-certbot-dns-cloudflare`) حتى يتجدد تلقائيًا.

## 3. قاعدة البيانات

كل مؤسسة جديدة تُنشأ لها قاعدة باسم `tenant<uuid>`، لذا يحتاج مستخدم التطبيق صلاحية إنشاء قواعد:

```sql
CREATE DATABASE aqarnova_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'aqarnova'@'localhost' IDENTIFIED BY '<كلمة-مرور-قوية>';
GRANT ALL PRIVILEGES ON `aqarnova_central`.* TO 'aqarnova'@'localhost';
GRANT ALL PRIVILEGES ON `tenant%`.* TO 'aqarnova'@'localhost';
GRANT CREATE ON *.* TO 'aqarnova'@'localhost';
FLUSH PRIVILEGES;
```

## 4. التثبيت

```bash
sudo mkdir -p /var/www && cd /var/www
sudo git clone <رابط-المستودع> rowad && cd rowad/saas
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

عدّل `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://aqarnova.sa

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aqarnova_central
DB_USERNAME=aqarnova
DB_PASSWORD=<كلمة-المرور>

CENTRAL_DOMAINS=aqarnova.sa,www.aqarnova.sa
TENANT_BASE_DOMAIN=aqarnova.sa

# لا تضع الجلسات في قاعدة البيانات: الاتصال الافتراضي يتغير مع كل مؤسسة
SESSION_DRIVER=file          # أو redis
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database    # أو redis
CACHE_STORE=database         # أو redis
LOG_LEVEL=warning

MAIL_MAILER=smtp
MAIL_HOST=<smtp-host>
MAIL_PORT=587
MAIL_USERNAME=<user>
MAIL_PASSWORD=<password>
MAIL_FROM_ADDRESS=noreply@aqarnova.sa

PLATFORM_ADMIN_EMAIL=<بريدك>
PLATFORM_ADMIN_PASSWORD=<كلمة-مرور-قوية>
```

ثم:

```bash
php artisan migrate --force       # قاعدة المنصة
php artisan db:seed --force       # الباقات + مدير المنصة من PLATFORM_ADMIN_*
php artisan filament:assets
php artisan optimize              # config + routes + views + events
php artisan filament:optimize

sudo chown -R www-data:www-data storage bootstrap/cache
```

بعد أول تشغيل يمكن حذف `PLATFORM_ADMIN_PASSWORD` من `.env`.

## 5. Nginx

`/etc/nginx/sites-available/rowad`:

```nginx
server {
    listen 80;
    server_name aqarnova.sa *.aqarnova.sa;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name aqarnova.sa *.aqarnova.sa;
    root /var/www/rowad/saas/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/aqarnova.sa/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/aqarnova.sa/privkey.pem;

    client_max_body_size 50M;   # سحابة الملفات تقبل حتى 50 ميجا

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known) { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/rowad /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

في `php.ini` (FPM): `upload_max_filesize = 50M` و`post_max_size = 55M` و`memory_limit = 512M`.

## 6. عامل الطوابير والمهام المجدولة

الرسائل (SMS والبريد والتنبيهات) تُرسل عبر الطوابير، والتنبيهات اليومية عبر الجدولة.

`/etc/supervisor/conf.d/aqarnova-worker.conf`:

```ini
[program:aqarnova-worker]
command=php /var/www/rowad/saas/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=www-data
numprocs=2
process_name=%(program_name)s_%(process_num)02d
autostart=true
autorestart=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/www/rowad/saas/storage/logs/worker.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
```

الجدولة (`sudo crontab -u www-data -e`):

```cron
* * * * * cd /var/www/rowad/saas && php artisan schedule:run >> /dev/null 2>&1
```

ما يعمل تلقائيًا: `rowad:daily` الساعة 3 فجرًا (تذكير الدفعات والمتأخرات، تنبيه انتهاء هويات الموظفين والرخص، تحرير وحدات العقود المنتهية، رسوم الإدارة)،
و`rowad:usage` كل ساعة (استهلاك كل مؤسسة من الوحدات والمستخدمين والتخزين).

## 7. التحديث

```bash
cd /var/www/rowad && git pull
cd saas
composer install --no-dev --optimize-autoloader
php artisan down
php artisan migrate --force              # قاعدة المنصة
php artisan tenants:migrate --force      # كل قواعد المؤسسات
php artisan optimize && php artisan filament:optimize
php artisan queue:restart
php artisan up
```

## 8. النسخ الاحتياطي

كل مؤسسة تستطيع تنزيل نسخة كاملة من بياناتها من الإعدادات. للمنصة كلها، نسخة يومية لكل القواعد وملفات المؤسسات:

`/usr/local/bin/aqarnova-backup.sh`:

```bash
#!/bin/bash
set -euo pipefail
DEST=/var/backups/aqarnova/$(date +%F)
mkdir -p "$DEST"
for db in $(mysql -N -e "SHOW DATABASES" | grep -E '^(aqarnova_central|tenant)'); do
  mysqldump --single-transaction --routines "$db" | gzip > "$DEST/$db.sql.gz"
done
tar -czf "$DEST/files.tar.gz" -C /var/www/rowad/saas storage --exclude='storage/framework' --exclude='storage/logs'
find /var/backups/aqarnova -maxdepth 1 -type d -mtime +14 -exec rm -rf {} +
# انسخ $DEST إلى مكان خارج السيرفر (S3 أو سيرفر آخر) — يحدده المالك
```

```cron
30 2 * * * /usr/local/bin/aqarnova-backup.sh
```

## 9. النطاق الخاص لمؤسسة (باقة المؤسسات)

1. المؤسسة تضيف في لوحة نطاقها سجل `CNAME` من `alrowad.com.sa` (أو `www`) إلى `aqarnova.sa`، أو سجل `A` إلى IP السيرفر للنطاق الجذري.
2. من لوحة المنصة: المؤسسات ← تعديل المؤسسة ← النطاقات ← "إضافة نطاق خاص".
3. على السيرفر، شهادة للنطاق وكتلة Nginx له (نفس إعداد القسم 5 مع تغيير الاسم والشهادة):

```bash
sudo certbot certonly --nginx -d alrowad.com.sa -d www.alrowad.com.sa
sudo cp /etc/nginx/sites-available/rowad /etc/nginx/sites-available/alrowad.com.sa
# عدّل server_name و ssl_certificate في الملف الجديد ثم:
sudo ln -s /etc/nginx/sites-available/alrowad.com.sa /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

يبقى النطاق الفرعي على المنصة يعمل دائمًا ولا يمكن حذفه (روابط البوابات والرسائل ورموز QR تعتمد عليه).

## 10. نقل مؤسسة من نظام رواد السابق

1. أنشئ المؤسسة من لوحة المنصة (`/platform`) أو من صفحة التسجيل.
2. استورد قاعدة القديم في MySQL على السيرفر (قاعدة مؤقتة)، وانسخ مجلد `media` القديم.
3. نفّذ:

```bash
LEGACY_DB_PASSWORD=<كلمة-مرور-القاعدة-القديمة> php artisan rowad:import-legacy <alrowad.aqarnova.sa-or-tenant-id> \
  --host=127.0.0.1 --database=<legacy_db> --username=<user> --media=/path/to/legacy/media
```

- يرفض الاستيراد إن كانت المؤسسة فيها بيانات، إلا مع `--force`.
- قوالب الطباعة القديمة تُحفظ كنسخ `legacy_*` وتبقى التصاميم الحديثة هي المستخدمة؛ أضف `--legacy-templates` لاستخدام قوالب المكتب كما هي.
- كلمات مرور المستخدمين القديمة (MD5) تعمل عند أول دخول وتُحوَّل تلقائيًا لتشفير آمن.
- راجع الأعداد في نهاية الأمر (وحدات، عقود، مجموع الإيجارات) مع النظام القديم قبل الاعتماد، ثم احذف القاعدة المؤقتة.

## 11. قائمة التحقق قبل الإطلاق

- [ ] `APP_DEBUG=false` و`APP_ENV=production`.
- [ ] شهادة Wildcard تعمل على `aqarnova.sa` وأي نطاق فرعي.
- [ ] `php artisan about` يظهر الكاش مفعّلًا، و`queue:work` يعمل تحت Supervisor.
- [ ] جرّب التسجيل من الصفحة الرئيسية ثم الدخول للوحة المؤسسة الجديدة.
- [ ] ضبط مزود SMS والبريد من إعدادات كل مؤسسة.
- [ ] النسخ الاحتياطي اليومي يعمل ويُنقل خارج السيرفر.
- [ ] **تغيير كلمة مرور قاعدة النظام القديم** التي كانت مكشوفة في ملفات الإعداد القديمة.
- [ ] جدار ناري: افتح 80 و443 و22 فقط، وMySQL على `127.0.0.1`.
