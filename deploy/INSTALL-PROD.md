# دليل التثبيت — الإنتاج (Production)

> تشغيل منصة ميزان على خادم Linux مع دومين حقيقي + HTTPS.

---

## 1. نظرة عامة

يحتوي مجلد `deploy/` على كل ما تحتاجه لتثبيت ميزان على خادم إنتاج بخطوة واحدة:

| الملف | الاستخدام |
|-------|----------|
| `install.conf.example` | قالب ملف الإعدادات — ينسخ إلى `install.conf` |
| `install.sh` | المثبّت الآلي (يقرأ `install.conf` ويُنفّذ كل شيء) |
| `nginx.conf.template` | قالب Nginx virtual host |
| `apache.conf.template` | قالب Apache virtual host |

---

## 2. متطلبات الخادم

- **نظام التشغيل:** Ubuntu 22.04 LTS / Ubuntu 24.04 LTS (أو Debian 12)
- **ذاكرة:** 4GB كحد أدنى (8GB موصى به مع Ollama محلي)
- **قرص:** 20GB للنظام + مساحة للمستندات + 20GB إذا استخدمت Ollama
- **شبكة:** IP ثابت، المنافذ 80 و443 مفتوحة
- **DNS:** سجل A يشير من الدومين إلى IP الخادم

المثبّت يُثبّت تلقائياً:

```
PHP 8.3 + extensions (mbstring, xml, curl, zip, gd, intl, bcmath)
Composer, Node.js 22 LTS, npm
MySQL 8 (أو PostgreSQL/SQLite حسب الإعدادات)
Nginx (أو Apache)
Poppler (لاستخراج PDF)
Certbot + Let's Encrypt
Tesseract + حزمة العربية (اختياري — لاستخراج OCR من PDF المصوّرة)
```

---

## 3. خطوات التثبيت

### 3.1 انسخ الكود إلى الخادم

```bash
# عبر git
sudo git clone https://github.com/Eisa2222/mizan.git /var/www/mizan
sudo chown -R $USER:$USER /var/www/mizan
cd /var/www/mizan

# أو عبر أرشيف (من package.sh)
scp mizan-v1.0.tar.gz user@server:/tmp/
sudo tar -xzf /tmp/mizan-v1.0.tar.gz -C /var/www/
cd /var/www/mizan
```

### 3.2 حضّر ملف الإعدادات

```bash
cp deploy/install.conf.example deploy/install.conf
vi deploy/install.conf      # أو nano
```

**الحقول الإلزامية:**

| الحقل | القيمة |
|------|-------|
| `DOMAIN` | الدومين بدون https:// — مثل `mizan.gov.sa` |
| `APP_URL` | URL كامل — مثل `https://mizan.gov.sa` |
| `ADMIN_EMAIL` | بريد لتنبيهات Let's Encrypt + المدير |
| `DB_PASSWORD` | كلمة مرور قوية لقاعدة البيانات |
| `INITIAL_ADMIN_PASSWORD` | كلمة مرور المدير الأول |
| `AI_PROVIDER` | `ollama` (محلي) أو `anthropic` (SaaS) |

**أمثلة على AI_PROVIDER:**

```ini
# خيار 1: Ollama محلي (لا يحتاج API key، يعمل offline)
AI_PROVIDER=ollama
OLLAMA_HOST=http://localhost:11434
OLLAMA_MODEL=gemma3:27b

# خيار 2: Anthropic Claude (سحابي، أسرع، يحتاج API key)
AI_PROVIDER=anthropic
ANTHROPIC_API_KEY=sk-ant-xxx
ANTHROPIC_MODEL=claude-sonnet-4-5
```

### 3.3 شغّل المثبّت

```bash
sudo bash deploy/install.sh
```

يستغرق ~5-10 دقائق على خادم عادي. المثبّت:

1. يتحقّق من صحة `install.conf`
2. يُثبّت كل الحزم المطلوبة (apt-get)
3. يُنشئ ملف `.env` من `install.conf`
4. `composer install` + `npm install` + `npm run build`
5. يُنشئ قاعدة البيانات وينفّذ migrations + seeders
6. يُنشئ حساب SuperAdmin بالبيانات المحدّدة
7. يكتب ملف Nginx/Apache vhost بالدومين الخاص بك
8. يطلب شهادة Let's Encrypt SSL تلقائياً
9. يُثبّت Tesseract Arabic pack (إذا `INSTALL_ARABIC_OCR=true`)
10. يُشغّل queue worker كخدمة systemd
11. يُضيف cron entry لـ Laravel scheduler
12. يُفعّل config/route/view cache للإنتاج

**المثبّت idempotent** — يمكن إعادة تشغيله بأمان.

---

## 4. ما بعد التثبيت

### 4.1 اختبر HTTPS

```bash
curl -I https://YOUR_DOMAIN.sa
# يجب أن يرجع 200 OK مع Strict-Transport-Security
```

### 4.2 سجّل دخول أول مرة

1. افتح `https://YOUR_DOMAIN.sa/login`
2. استخدم `INITIAL_ADMIN_EMAIL` + `INITIAL_ADMIN_PASSWORD` من `install.conf`
3. **أول شيء:** انتقل إلى `/profile` وغيّر كلمة المرور

### 4.3 تحقّق من رؤوس الأمان

عبر موقع خارجي:
```
https://securityheaders.com/?q=YOUR_DOMAIN.sa
```

يجب أن تحصل على تقييم **A+** مع:
- Strict-Transport-Security ✓
- X-Frame-Options: DENY ✓
- X-Content-Type-Options: nosniff ✓
- Content-Security-Policy ✓
- Referrer-Policy ✓
- Permissions-Policy ✓

### 4.4 (اختياري) درّب الـ AI على مكتبتك المرجعية

```bash
cd /var/www/mizan
php artisan mizaan:train-from-folder /path/to/legal-pdfs
php artisan mizaan:import-rulings /path/to/judicial-rulings-txt
```

---

## 5. الصيانة

| المهمة | الأمر |
|-------|------|
| تحديث الكود | `cd /var/www/mizan && git pull && composer install --no-dev && npm run build && php artisan migrate --force && php artisan config:cache && systemctl reload mizan-queue` |
| عرض logs التطبيق | `tail -f /var/www/mizan/storage/logs/laravel.log` |
| عرض logs Nginx | `tail -f /var/log/nginx/mizan.error.log` |
| عرض queue | `journalctl -u mizan-queue -f` |
| إعادة تشغيل queue | `systemctl restart mizan-queue` |
| تجديد شهادة SSL | `certbot renew` (تلقائي عبر cron) |

---

## 6. استكشاف الأخطاء

### الموقع يرجع 500

```bash
# 1. تحقّق من الصلاحيات
sudo chown -R www-data:www-data /var/www/mizan/storage /var/www/mizan/bootstrap/cache
sudo chmod -R 775 /var/www/mizan/storage /var/www/mizan/bootstrap/cache

# 2. امسح الـ cache
cd /var/www/mizan
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# 3. شغّل مؤقتاً APP_DEBUG=true لرؤية الخطأ (ثم أعده false)
```

### Nginx/Apache لا يبدأ

```bash
nginx -t           # لـ Nginx
apache2ctl configtest   # لـ Apache
journalctl -xeu nginx
```

### Let's Encrypt فشل

```bash
# تأكد من فتح المنافذ 80 و443
# تأكد من DNS يُشير إلى IP الخادم
dig +short YOUR_DOMAIN.sa
# أعد الطلب يدوياً
certbot --nginx -d YOUR_DOMAIN.sa
```

### لا يمكن الوصول لـ Ollama

```bash
# تحقّق من تشغيل Ollama
curl http://localhost:11434/api/tags
# شغّل Ollama كخدمة
systemctl status ollama
# جرّب نموذج أصغر إذا RAM محدود
ollama pull gemma3:9b
```

---

## 7. الأمان (NCA + OWASP)

المثبّت يُطبّق تلقائياً:

- ✓ APP_DEBUG=false في الإنتاج (`AppServiceProvider` يفشل startup إذا كانت true)
- ✓ X-Powered-By مُزال
- ✓ رؤوس الأمان كاملة (CSP/HSTS/X-Frame-Options/...)
- ✓ Session cookies: encrypted + httpOnly + secure + SameSite=lax
- ✓ CSRF + rate limiting افتراضياً (Laravel)
- ✓ SQL injection ومحمي (Eloquent + parameterised queries)
- ✓ لا توجد secrets في الكود (كلها في `.env`)
- ✓ PDPL: الخطوط self-hosted (لا Google Fonts)
- ✓ `.env` chmod 640 (قراءة المالك فقط)

**ما عليك فعله يدوياً:**

- [ ] فعّل جدار حماية: `ufw allow 80,443/tcp && ufw enable`
- [ ] غيّر المنفذ الافتراضي لـ SSH عن 22
- [ ] فعّل `fail2ban` لتقييد brute force
- [ ] اعمل backups دورية لقاعدة البيانات (`mysqldump` في cron)
- [ ] راقب Sentry/Grafana لتتبع الأخطاء
- [ ] راجع `php artisan route:list` بشكل دوري للتأكد من عدم تسريب endpoints
