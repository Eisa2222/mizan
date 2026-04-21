# متطلبات النظام التقنية — منصة ميزان

> دليل شامل للمتطلبات البرمجية والعتادية لتشغيل منصة ميزان للبحث القانوني.

---

## 1. المتطلبات الأساسية (إلزامية)

| المكوّن | الإصدار الأدنى | الإصدار الموصى | الغرض |
|---------|--------------|---------------|-------|
| **PHP** | 8.3 | 8.3.30+ | لغة التشغيل الأساسية |
| **Composer** | 2.5 | آخر إصدار | إدارة حزم PHP |
| **Node.js** | 22.x LTS | 22.x LTS | بناء الأصول (Vite) |
| **npm** | 10.x | آخر إصدار | تثبيت حزم JS |
| **قاعدة بيانات** | — | — | SQLite 3.35+ (تطوير) **أو** MySQL 8 / PostgreSQL 14+ (إنتاج) |
| **خادم ويب** | — | — | php-fpm + Nginx 1.22+ (إنتاج) أو `php artisan serve` (تطوير) |
| **ذاكرة RAM** | 2 GB | 4 GB+ | استخراج النصوص + فهرسة + AI |
| **تخزين** | 5 GB | 20 GB+ | ملفات مرفوعة + فهرس ES + قاعدة البيانات |

### إضافات PHP المطلوبة

```
ext-mbstring      (للنص العربي والـ UTF-8)
ext-intl          (للتنسيق الدولي والـ NFKC)
ext-pdo_sqlite    (للـ SQLite)
ext-pdo_mysql     (للـ MySQL — إنتاج)
ext-gd            (لمعالجة الصور)
ext-zip           (لاستخراج DOCX + صادرات Excel)
ext-curl          (لاتصالات HTTP)
ext-openssl       (للتشفير)
ext-tokenizer     (Laravel)
ext-xml           (Laravel)
ext-fileinfo      (Laravel)
```

---

## 2. المتطلبات الاختيارية (تفعّل ميزات إضافية)

| المكوّن | متى يُحتاج | بدون تثبيت |
|---------|-----------|-----------|
| **Tesseract OCR 5.x + ara.traineddata** | لاستخراج النصوص من PDFs الممسوحة ضوئياً والصور | الرفع يعمل لكن يظهر تنبيه "OCR غير متاح" |
| **Poppler (pdftotext + pdftoppm)** | لتحسين استخراج PDFs والـ OCR | الاعتماد على `smalot/pdfparser` فقط |
| **Elasticsearch 8.x** | للبحث الدلالي السريع عبر آلاف الوثائق | الاعتماد على بحث قاعدة البيانات (أبطأ) |
| **Redis 7.x** | لجلسات + كاش + queue عالي الأداء | الاعتماد على `database` driver |
| **FFmpeg** | مستقبلاً — لتحويل الصوتيات إلى نصوص | — |

### مفاتيح API الخارجية

| الخدمة | الغرض | حالة التطبيق |
|--------|-------|--------------|
| `ANTHROPIC_API_KEY` | محرّك AI (Claude Sonnet 4.5) لمراجعة العقود + المذكرات + الكراسات + المساعد | **موصى بقوة** — بدونه كل ميزات AI تعرض رسالة "غير متاح" |

---

## 3. المتطلبات للبيئات المختلفة

### بيئة التطوير (Development)

```
- Laragon 6.0 (Windows) — يأتي بـ Apache + PHP + MySQL جاهزة
- أو XAMPP / Valet
- SQLite (افتراضي في .env.example)
- `php artisan serve` على 127.0.0.1:8000
```

### بيئة الاختبار (Staging)

```
- Ubuntu 22.04 LTS
- Nginx 1.22
- PHP 8.3-FPM
- MySQL 8 أو PostgreSQL 14
- Elasticsearch 8 (مُوصى)
- Tesseract + Poppler
```

### بيئة الإنتاج (Production)

```
- Ubuntu 22.04 LTS / Debian 12
- Nginx 1.24 + HTTP/2 + TLS 1.3
- PHP 8.3-FPM (opcache مفعّل)
- MySQL 8.0 (replication مع slave للقراءة)
- Elasticsearch 8.10+ (cluster من 3 عقد للـ HA)
- Redis 7 (للجلسات + queue)
- Supervisor لإدارة queue workers
- Let's Encrypt TLS
- Cloudflare للـ CDN (اختياري)
- Backup يومي (`mysqldump` + `rsync` للمرفقات)
```

---

## 4. الحزم البرمجية (من composer.json + package.json)

### حزم PHP الرئيسية (composer)

- `laravel/framework ^13.0` — الإطار الأساسي
- `laravel/tinker ^3.0` — REPL تطوير
- `elasticsearch/elasticsearch ^8.0` — عميل ES
- `smalot/pdfparser ^2.12` — استخراج PDF (fallback)
- `thiagoalessio/tesseract_ocr ^2.13` — ربط Tesseract
- `mpdf/mpdf ^8.3` — توليد PDF للتصدير
- `phpoffice/phpword ^1.4` — قراءة/كتابة Word
- `phpoffice/phpspreadsheet ^5.6` — قراءة/كتابة Excel
- `spatie/browsershot ^5.2` — screenshots + PDF من HTML (يحتاج Node)

### حزم JS الرئيسية (npm)

- `alpinejs ^3.4` — تفاعل خفيف (dropdowns, toasts, filters)
- `tailwindcss ^3.1` + `@tailwindcss/vite` — CSS utilities
- `vite ^8` — bundler + dev server
- `axios` — HTTP client
- `laravel-vite-plugin ^3` — تكامل Laravel/Vite

---

## 5. موارد التشغيل الموصى بها (per instance)

| المقياس | تطوير | إنتاج صغير (10 مستخدمين) | إنتاج كبير (200+ مستخدم) |
|---------|-------|-------------------------|-------------------------|
| CPU | 2 vCPU | 4 vCPU | 8 vCPU |
| RAM | 4 GB | 8 GB | 16 GB+ |
| Disk | 20 GB SSD | 100 GB SSD | 500 GB SSD (NVMe) |
| DB RAM | — | 2 GB MySQL buffer | 8 GB MySQL/PG |
| ES RAM | — | 4 GB JVM heap | 8-16 GB JVM heap |

---

## 6. الأمان والامتثال

- HTTPS إلزامي في الإنتاج (TLS 1.2+)
- `APP_DEBUG=false` في الإنتاج
- `SESSION_ENCRYPT=true` في الإنتاج
- Rate limiting على endpoints الـ AI لحماية الميزانية
- Backup مشفّر + retention 30 يوم على الأقل
- ضبط صلاحيات النظام:
  - `storage/` → `775` + owner www-data
  - `bootstrap/cache/` → `775` + owner www-data
  - `.env` → `640`

---

## 7. حدود التطبيق

| الحد | القيمة | المصدر |
|------|-------|--------|
| حجم PDF للرفع | 20 MB | `FormRequest` (قابل للتعديل) |
| حجم PDF للاستخراج الكامل | 30 MB | `TextExtractorService` |
| طول النص الذي يُرسَل لـ AI | 80,000 حرف | `ReviewContractJob` |
| Timeout الاستخراج + OCR | 600 ثانية | `ExtractDocumentTextJob` |
| Timeout مراجعة العقد | 300 ثانية | `ReviewContractJob` |
| حجم Excel للاستخراج | 500 KB | `TextExtractorService` (يوسّع إلى MB إذا لزم) |
