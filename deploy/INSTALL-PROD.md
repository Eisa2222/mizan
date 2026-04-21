# دليل التثبيت — الإنتاج (Production)

> تشغيل منصة ميزان على خادم Linux مع دومين حقيقي + HTTPS.
>
> **نموذج SaaS متعدّد المستأجرين:** دومين مركزي (مثل `mizaan.sa`) يستضيف صفحة الهبوط + Checkout + SuperAdmin، وكل مستأجر يحصل على subdomain خاص (`acme.mizaan.sa`) بقاعدة بيانات مستقلة.

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

---

## 8. إعداد SaaS (بعد تثبيت القاعدة)

بعد أن ينتهي `install.sh`، تُشغَّل هذه الخطوات لتفعيل منصّة SaaS الكاملة:

### 8.1 Wildcard DNS للـ subdomains

كل مستأجر يحصل على subdomain مستقل (`acme.mizaan.sa`، `beta.mizaan.sa`، ...). في إعدادات DNS:

```
Type   Name   Value
A      *      <IP الخادم>
A      @      <IP الخادم>
```

### 8.2 Wildcard SSL

```bash
# استخدم DNS-01 challenge (أسهل مع wildcard)
sudo certbot certonly --manual --preferred-challenges=dns \
    -d "mizaan.sa" -d "*.mizaan.sa"
```

حدِّث `deploy/nginx.conf.template` ليستخدم شهادة wildcard، ثم:

```bash
sudo cp deploy/nginx.conf.template /etc/nginx/sites-available/mizan
sudo sed -i 's/__DOMAIN__/mizaan.sa/g' /etc/nginx/sites-available/mizan
sudo nginx -t && sudo systemctl reload nginx
```

### 8.3 تعيين central domains

في `.env`:

```ini
CENTRAL_DOMAINS=mizaan.sa,www.mizaan.sa
```

أي طلب لأي subdomain آخر سيُعامل كـ tenant تلقائياً.

### 8.4 seeder البيانات الأولية

```bash
php artisan db:seed --class=SaasInitialSeeder
```

يُنشئ تلقائياً:
- 3 باقات (الأساسية 299 / الاحترافية 899 / المؤسسية 2,999)
- 6 مميزات لصفحة الهبوط
- 6 أسئلة شائعة
- إعدادات افتراضية (trial 14 يوم، hero copy، mail=log)
- حساب SuperAdmin (`sa@mizaan.local` / `Admin@123`) — **غيّره فوراً**

### 8.5 إعدادات Moyasar من لوحة SuperAdmin

```
https://mizaan.sa/super-admin/login
```

اذهب إلى **الإعدادات > Moyasar** وعبّئ:
- `moyasar_publishable_key` (pk_live_...)
- `moyasar_secret_key` (sk_live_...) — يُحفظ مشفّراً
- `moyasar_webhook_secret`
- فعّل `moyasar_test_mode=false` للإنتاج

اضغط "اختبار الاتصال" للتحقق من صحة المفاتيح.

### 8.6 تسجيل Webhook في Moyasar Dashboard

في [dashboard.moyasar.com](https://dashboard.moyasar.com):
- Webhooks → New
- URL: `https://mizaan.sa/webhooks/moyasar`
- Events: `payment_paid`, `payment_failed`, `payment_refunded`
- Secret: نفس `moyasar_webhook_secret` الذي حفظته

### 8.7 تحويل المؤسسات الحالية (إن وُجدت)

إذا ترقّيت من نسخة single-tenant قديمة:

```bash
# معاينة
php artisan saas:convert-org-to-tenant 1 --plan=pro --cycle=yearly --dry-run

# تنفيذ
php artisan saas:convert-org-to-tenant 1 --plan=pro --cycle=yearly
```

### 8.8 Queue worker + Scheduler (مُعدّان تلقائياً بواسطة `install.sh`)

```bash
systemctl status mizan-queue       # queue worker (CreateTenantJob، mail، OCR)
crontab -l                         # cron entry: schedule:run كل دقيقة
```

تحقّق من تشغيل المهام اليومية:

```bash
php artisan schedule:list
# يجب أن يظهر:
#   0 0 * * * saas:check-trial-expiry
#   0 8 * * * saas:send-trial-warnings
```

### 8.9 اختبار التدفّق الكامل

1. زُر `https://mizaan.sa` — يجب رؤية الصفحة الرئيسية مع الباقات
2. اختر باقة + اضغط "ابدأ الآن"
3. عبّئ بيانات الشركة + اختبر الدفع ببطاقة Moyasar الاختبارية:
   ```
   4111 1111 1111 1111    exp: 01/39    cvc: 123
   ```
4. تحقّق من الوصول إلى `/checkout/success`
5. تحقّق من استلام بريد الترحيب (أو راجع `storage/logs/laravel.log` لو `mail_driver=log`)
6. اضغط رابط تهيئة كلمة المرور في البريد
7. يجب أن يفتح subdomain المستأجر + يسجّل دخولك

### 8.10 Runbook الصيانة اليومية

| المهمة | الأمر |
|-------|------|
| تحقّق من queue | `systemctl status mizan-queue` |
| logs الـ queue | `journalctl -u mizan-queue -f` |
| logs التطبيق | `tail -f /var/www/mizan/storage/logs/laravel.log` |
| logs المدفوعات (Moyasar) | `grep moyasar /var/www/mizan/storage/logs/laravel.log` |
| فشل webhook | راجع `/super-admin/payments` + لوحة Moyasar |
| تمديد يدوي لمستأجر | `/super-admin/tenants/{id}` → زر "تمديد" |

---

## 9. مرجع بنية SaaS

```
CENTRAL DB (mizaan_central)           TENANT DB (tenant_<uuid>)
─────────────────────────             ─────────────────────────
tenants + domains                     users + password_reset_tokens
super_admins                          legal_documents + chunks + versions
plans + plan_features                 tasks + comments + assignments
subscriptions, payments               folders + members + documents
coupons + coupon_uses                 tenders + sections + clauses + reviews
landing_features + faqs               annotations + discussions + replies
system_settings                       watchlists, article_updates
gpc_knowledge        ← shared         document_relations
distilled_knowledge  ← shared         ai_conversations + ai_messages
                                      app_notifications
                                      spatie permission tables
                                      organizations (1 row per tenant)
```

كل tenant DB معزول فيزيائياً — لا يمكن للاستعلام عبر tenant context الوصول لأي tenant آخر.

