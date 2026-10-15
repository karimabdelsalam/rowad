# عقار نوفا — Aqar Nova

نظام سحابي لإدارة الأملاك للمنشآت العقارية في السعودية: الوحدات والعقود والتحصيل وحسابات الملاك والفوترة الإلكترونية،
مع بوابات للملاك والمستأجرين، وموقع عام لكل مؤسسة، ومساعد ذكاء اصطناعي. يعمل على `aqarnova.com`، وكل مؤسسة على `اسمها.aqarnova.com`
بقاعدة بيانات مستقلة.

## الفروع

| الفرع | المحتوى |
|---|---|
| `main` | النظام الجديد (Laravel 13 + Filament، متعدد المؤسسات) |
| `old` | النظام القديم (رواد، PHP + Smarty) بعد تحديثه لـ PHP 8.4 — للمرجعية فقط |

## التوثيق

- [التشغيل على cPanel](docs/deployment-cpanel.md) · [التشغيل على VPS](docs/deployment.md)
- [واجهة تطبيق الجوال (API)](docs/api.md)
- [القرارات التقنية](docs/decisions.md) · [قائمة مزايا النظام القديم](docs/feature-inventory.md)

## التشغيل محليًا

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate && php artisan db:seed
php artisan rowad:demo          # مؤسسة تجريبية: demo.localhost
php artisan serve
php artisan test                # الاختبارات
```

بعد التثبيت على السيرفر: `php artisan rowad:doctor` يفحص الجاهزية ويذكر ما ينقص.

---

برمجة وتطوير [بلانوفا](https://planova.com.eg)
