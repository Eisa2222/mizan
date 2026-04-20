# دليل تثبيت منصة ميزان

> خطوات تشغيل المنصة من الصفر حتى أول login — Windows / Linux / macOS.

---

## 0. التحقق من المتطلبات

قبل البدء تأكد من تثبيت:

```bash
php --version          # 8.3 أو أحدث
composer --version     # 2.5+
node --version         # 22.x LTS
npm --version          # 10+
```

للمتطلبات الاختيارية راجع [REQUIREMENTS.md](REQUIREMENTS.md).

---

## 1. الحصول على الكود

```bash
# من git
git clone <repo-url> mizaan
cd mizaan

# أو من أرشيف
unzip mizaan.zip
cd mizaan
```

---

## 2. إعداد متغيرات البيئة

```bash
cp .env.example .env
```

افتح `.env` وعدّل:

```ini
APP_NAME=ميزان
APP_URL=http://localhost:8000       # عدّل حسب دومينك في الإنتاج
APP_ENV=local                        # local | production
APP_DEBUG=true                       # false في الإنتاج

# قاعدة البيانات — ابدأ بـ SQLite
DB_CONNECTION=sqlite
# للإنتاج استخدم MySQL:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=mizaan
# DB_USERNAME=mizaan_user
# DB_PASSWORD=strong-password-here

# مفتاح AI — إلزامي لتشغيل ميزات المراجعة والمساعد
ANTHROPIC_API_KEY=sk-ant-xxxxxxxxxxxxx
ANTHROPIC_MODEL=claude-sonnet-4-5

# Elasticsearch — اختياري
ELASTICSEARCH_HOST=http://localhost:9200
```

---

## 3. تثبيت الحزم

```bash
# PHP
composer install --no-dev --optimize-autoloader   # للإنتاج
# أو للتطوير:
composer install

# JavaScript
npm install
```

---

## 4. توليد مفتاح التطبيق + قاعدة البيانات

```bash
# إنتاج مفتاح التشفير
php artisan key:generate

# SQLite فقط: إنشاء الملف
touch database/database.sqlite      # Linux/macOS
# Windows: type nul > database\database.sqlite

# تشغيل الهجرات
php artisan migrate --force

# زرع البيانات الأساسية (مستخدم مسؤول + قاعدة معرفة GPC)
php artisan db:seed --force
```

بعد الـ seed سيتوفر **مستخدم سوبر أدمن افتراضي**:

```
Email:    admin@mizaan.local
Password: Admin@123
```

> ⚠️ **غيّر كلمة المرور فوراً بعد أول تسجيل دخول** في الإنتاج.

---

## 5. بناء الأصول + روابط التخزين

```bash
# بناء CSS/JS
npm run build

# للتطوير (watch mode):
# npm run dev

# ربط مجلد التخزين العام (لعرض الملفات المرفوعة)
php artisan storage:link
```

---

## 6. (اختياري) تدريب AI على المراجع

إذا كان لديك مجلد به مراجع قانونية (أنظمة، عقود، كراسات، مذكرات، أحكام):

```bash
php artisan mizaan:train-from-folder "/path/to/legal-references"
```

يقوم بـ:
- المشي recursively في المجلد
- استخراج نصوص PDF/DOCX/XLS
- تصنيف تلقائي حسب اسم المجلد (أنظمة/كراسات/مذكرات/قضاء/عقد)
- حفظ في منظمة "المكتبة المرجعية" مع فهرسة Elasticsearch
- ربط تلقائي مع الـ RAG في مراجعات العقود والمذكرات والمساعد

خيارات:

```bash
# معاينة بدون حفظ
php artisan mizaan:train-from-folder "/path" --dry-run

# حد أقصى 50 ملف
php artisan mizaan:train-from-folder "/path" --limit=50

# تخطي الملفات المستوردة مسبقاً
php artisan mizaan:train-from-folder "/path" --skip-existing

# استيراد لمنظمة محددة
php artisan mizaan:train-from-folder "/path" --org=2
```

---

## 7. تشغيل النظام

### التطوير

```bash
# استخدم composer dev — يشغّل كل شيء معاً
composer dev

# أو يدوياً في 3 terminals:
# Terminal 1 — خادم PHP
php artisan serve --host=127.0.0.1 --port=8000

# Terminal 2 — معالج الـ jobs الخلفية
php artisan queue:work --tries=1 --timeout=600

# Terminal 3 — Vite dev server
npm run dev
```

افتح http://127.0.0.1:8000

### الإنتاج (Nginx + PHP-FPM)

**ملف الـ site** `/etc/nginx/sites-available/mizaan`:

```nginx
server {
    listen 443 ssl http2;
    server_name mizaan.example.com;
    root /var/www/mizaan/public;

    ssl_certificate     /etc/letsencrypt/live/mizaan.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/mizaan.example.com/privkey.pem;

    index index.php;
    charset utf-8;

    client_max_body_size 50M;   # للعقود والكراسات الكبيرة

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 600;  # للعمليات الطويلة
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    gzip on;
    gzip_types text/css application/javascript application/json;
}
```

**إعداد الصلاحيات**:

```bash
sudo chown -R www-data:www-data /var/www/mizaan/storage /var/www/mizaan/bootstrap/cache
sudo chmod -R 775 /var/www/mizaan/storage /var/www/mizaan/bootstrap/cache
```

**إعداد Supervisor لـ queue worker** `/etc/supervisor/conf.d/mizaan-queue.conf`:

```ini
[program:mizaan-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/mizaan/artisan queue:work --tries=1 --timeout=600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/mizaan-queue.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start mizaan-queue:*
```

**تحسينات الإنتاج**:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

## 8. (اختياري) Tesseract OCR لـ PDFs الممسوحة ضوئياً

انظر القسم المخصّص في [README.md](README.md#ocr-setup-optional) للتثبيت على Windows/Linux/macOS.

بعد التثبيت أعد تشغيل الـ queue worker — الـ `ExtractDocumentTextJob` سيكتشف الأدوات تلقائياً.

---

## 9. (اختياري) Elasticsearch

```bash
# Docker (أسرع طريقة)
docker run -d --name mizaan-es \
  -p 9200:9200 \
  -e "discovery.type=single-node" \
  -e "xpack.security.enabled=false" \
  -e "ES_JAVA_OPTS=-Xms2g -Xmx2g" \
  docker.elastic.co/elasticsearch/elasticsearch:8.11.0

# تأكد من التشغيل
curl http://localhost:9200
```

ثم في `.env`: `ELASTICSEARCH_HOST=http://localhost:9200`.

لإعادة فهرسة كل الوثائق الموجودة:

```bash
php artisan tinker
>>> App\Models\LegalDocument::chunk(50, fn($docs) => $docs->each(fn($d) => app(App\Services\ElasticsearchService::class)->reindexDocument($d)));
```

---

## 10. التحقق من صحة التثبيت

```bash
# اختبار سريع
php artisan test --filter=ProfileTest

# قائمة الـ routes
php artisan route:list | grep -E "dashboard|login"

# تأكد من اكتمال الـ seed
php artisan tinker --execute="echo 'Orgs: ' . App\Models\Organization::count() . ' | Users: ' . App\Models\User::count() . ' | GPC: ' . App\Models\GpcArticle::count();"
```

النتيجة المتوقعة:
```
Orgs: 1+ | Users: 1+ | GPC: 120+
```

ثم افتح `http://localhost:8000/login` وسجّل دخول بـ `admin@mizaan.local` / `Admin@123`.

---

## 11. التحديث (بعد pull للكود الجديد)

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo supervisorctl restart mizaan-queue:*
```

---

## استكشاف الأخطاء الشائعة

| الخطأ | السبب المحتمل | الحل |
|------|--------------|------|
| `No application encryption key` | نسيت `php artisan key:generate` | شغّل الأمر |
| `Database file does not exist` | SQLite غير منشأ | `touch database/database.sqlite` |
| `Vite manifest not found` | نسيت `npm run build` | شغّل البناء |
| `ميزة AI غير متاحة` | `ANTHROPIC_API_KEY` فارغ | ضع مفتاح Anthropic في `.env` |
| `SQLSTATE[HY000]: database is locked` | ملف SQLite مقفل | انتقل لـ MySQL للإنتاج |
| Jobs عالقة في queue | `queue:work` لا يعمل | شغّل worker أو supervisor |
| `Error! Bookmark not defined` في نتائج | DOCX معطوب — حالياً يُنظَّف تلقائياً | تأكد من آخر نسخة الكود |

للدعم الإضافي راجع [REQUIREMENTS.md](REQUIREMENTS.md) أو تواصل مع فريق التطوير.
