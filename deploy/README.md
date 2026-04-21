# deploy/ — ملفات تثبيت الإنتاج

هذا المجلد يحتوي كل ما يلزم لنشر منصة ميزان على خادم إنتاج مع دومين خاص.

## الملفات

| الملف | الغرض |
|-------|------|
| [`install.conf.example`](install.conf.example) | قالب ملف الإعدادات — انسخه إلى `install.conf` وعدّله |
| [`install.sh`](install.sh) | المثبّت الآلي — يقرأ `install.conf` ويُنفّذ كل شيء (~5-10 دقائق) |
| [`nginx.conf.template`](nginx.conf.template) | قالب Nginx مع placeholder للدومين + رؤوس الأمان الكاملة |
| [`apache.conf.template`](apache.conf.template) | قالب Apache (بديل لـ Nginx) |
| [`package.sh`](package.sh) | يبني أرشيف `mizan-vX.tar.gz` للنقل |
| [`INSTALL-PROD.md`](INSTALL-PROD.md) | **الدليل الكامل للتثبيت** |

## خطوات سريعة

```bash
# 1) حضّر الإعدادات
cp deploy/install.conf.example deploy/install.conf
vi deploy/install.conf       # عبّئ DOMAIN + DB_PASSWORD + INITIAL_ADMIN_*

# 2) شغّل المثبّت
sudo bash deploy/install.sh

# 3) افتح المتصفح
https://YOUR_DOMAIN.sa/login
```

لخطوات مفصّلة + استكشاف الأخطاء، اقرأ [INSTALL-PROD.md](INSTALL-PROD.md).

## ما الذي يُنفّذه المثبّت آلياً؟

- ✅ يُثبّت PHP 8.3 + Node 22 + MySQL + Nginx/Apache + Certbot (عبر apt)
- ✅ يُنشئ `.env` من `install.conf` (مع SESSION_SECURE_COOKIE=true، APP_DEBUG=false، إلخ)
- ✅ `composer install --no-dev` + `npm run build`
- ✅ `php artisan migrate + db:seed`
- ✅ ينشئ حساب SuperAdmin بالبيانات المُحدّدة
- ✅ يكتب Nginx/Apache vhost بالدومين الخاص بك
- ✅ يطلب شهادة Let's Encrypt HTTPS (اختياري)
- ✅ ينزّل Tesseract Arabic pack (اختياري — لاستخراج OCR من PDF المصوّرة)
- ✅ يُشغّل queue worker كخدمة systemd
- ✅ يُضيف cron entry لـ Laravel scheduler
- ✅ يُشغّل `config:cache + route:cache + view:cache` للإنتاج

## الأمان

الإعدادات الافتراضية تُطبّق:

- APP_DEBUG=false مع guard في `AppServiceProvider` (يفشل startup إذا كانت true في production)
- رؤوس الأمان الكاملة عبر `SecurityHeaders` middleware + web server
- X-Powered-By مُزال
- Session cookies: encrypt + httpOnly + secure + SameSite=lax
- `.env` chmod 640
- CSP يمنع Google Fonts (الخطوط self-hosted)
- `/build/manifest.json` محجوب
- `/up` health endpoint محجوب
