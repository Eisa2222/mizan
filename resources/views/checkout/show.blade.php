<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>إتمام الاشتراك · {{ config('app.name', 'Mizaan') }}</title>

    @vite(['resources/css/app.css'])
    <script src="https://cdn.moyasar.com/mpf/1.15.0/mpf.js"></script>
    <link href="https://cdn.moyasar.com/mpf/1.15.0/mpf.css" rel="stylesheet">

    <style>
        body { font-family: 'Cairo','Tajawal',sans-serif; background: #f7f8fc; margin: 0; padding: 32px 16px; }
        .co-wrap { max-width: 820px; margin: 0 auto; display: grid; grid-template-columns: 1.3fr 1fr; gap: 22px; }
        @media (max-width: 768px) { .co-wrap { grid-template-columns: 1fr; } }
        .card { background: #fff; border: 1px solid #e3e7f0; border-radius: 14px; padding: 24px; }
        h1 { font-size: 22px; margin: 0 0 18px; color: #0b1220; }
        h2 { font-size: 14px; margin: 0 0 12px; color: #0b1220; font-weight: 700; border-bottom: 1px solid #eef1f6; padding-bottom: 10px; }
        label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #485068; }
        input[type=text], input[type=email], input[type=tel] { width: 100%; padding: 10px 12px; border: 1px solid #d4d9e3; border-radius: 8px; font-family: inherit; box-sizing: border-box; }
        .summary-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; }
        .summary-row.total { border-top: 1px solid #eef1f6; padding-top: 14px; margin-top: 10px; font-size: 18px; font-weight: 700; }
        .coupon-row { display: flex; gap: 8px; margin: 14px 0; }
        .coupon-row input { flex: 1; padding: 8px 12px; border: 1px solid #d4d9e3; border-radius: 8px; font-family: inherit; }
        .btn { padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; border: none; font-family: inherit; font-size: 14px; }
        .btn-primary { background: #c8a94b; color: #0b1220; }
        .btn-ghost { background: #fff; color: #485068; border: 1px solid #d4d9e3; }
        .msg-success { color: #1e6c44; background: #e6f6ee; padding: 10px 12px; border-radius: 6px; font-size: 13px; }
        .msg-error   { color: #933; background: #fbe7e7; padding: 10px 12px; border-radius: 6px; font-size: 13px; }
        .mysr-form { margin-top: 18px; }
    </style>
</head>
<body>
    <div class="co-wrap" x-data="checkout()">
        {{-- ─── Form ─── --}}
        <div class="card">
            <h1>إتمام الاشتراك في {{ $plan->name }}</h1>

            <h2>بيانات الشركة</h2>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:18px">
                <div>
                    <label>اسم الشركة</label>
                    <input type="text" x-model="company_name" required>
                </div>
                <div>
                    <label>اسم صاحب الحساب</label>
                    <input type="text" x-model="owner_name" required>
                </div>
                <div>
                    <label>البريد الإلكتروني</label>
                    <input type="email" x-model="owner_email" dir="ltr" required>
                </div>
                <div>
                    <label>رقم الجوال</label>
                    <input type="tel" x-model="owner_phone" dir="ltr" placeholder="+9665...">
                </div>
            </div>

            <h2>كود خصم (اختياري)</h2>
            <div class="coupon-row">
                <input type="text" placeholder="CODE" x-model="coupon_code" dir="ltr">
                <button type="button" class="btn btn-ghost" @click="applyCoupon()" :disabled="!coupon_code">تطبيق</button>
            </div>
            <div x-show="couponMsg" x-cloak :class="couponValid ? 'msg-success' : 'msg-error'" x-text="couponMsg"></div>

            <h2 style="margin-top:22px">الدفع</h2>
            <div class="mysr-form"></div>
        </div>

        {{-- ─── Order summary ─── --}}
        <div class="card" style="align-self:start;position:sticky;top:16px">
            <h2>ملخص الطلب</h2>
            <div class="summary-row"><span>{{ $plan->name }} ({{ $cycle === 'yearly' ? 'سنوي' : 'شهري' }})</span><span>{{ number_format($amount, 2) }} ر.س</span></div>
            <div class="summary-row" x-show="discount > 0" x-cloak><span>خصم الكوبون</span><span style="color:#1e6c44">- <span x-text="discount.toFixed(2)"></span> ر.س</span></div>
            <div class="summary-row total">
                <span>الإجمالي</span>
                <span><span x-text="finalAmount.toFixed(2)"></span> ر.س</span>
            </div>

            @if ($settings['trial_enabled'] && ! $settings['trial_requires_payment'])
                <div class="msg-success" style="margin-top:14px">🎁 تجربة مجانية {{ $settings['trial_days'] }} يوم — لن يُسحب المبلغ قبل نهاية التجربة.</div>
            @endif

            <div style="margin-top:18px;padding:12px;background:#f5f7fb;border-radius:8px;font-size:12px;color:#6a7289">
                <strong>🔒 دفع آمن عبر Moyasar</strong><br>
                نحن لا نخزّن بيانات بطاقتك. جميع المعاملات مشفّرة بـ TLS.
            </div>
        </div>
    </div>

    <script>
        function checkout() {
            return {
                plan_id: {{ $plan->id }},
                cycle: '{{ $cycle }}',
                amount: {{ $amount }},
                company_name: '', owner_name: '', owner_email: '', owner_phone: '',
                coupon_code: '', couponMsg: '', couponValid: false, discount: 0,

                get finalAmount() { return Math.max(0, this.amount - this.discount); },

                async applyCoupon() {
                    if (!this.coupon_code) return;
                    try {
                        const r = await fetch('{{ route('checkout.apply-coupon') }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                            body: JSON.stringify({
                                code: this.coupon_code, plan_id: this.plan_id,
                                billing_cycle: this.cycle, amount: this.amount
                            })
                        });
                        const j = await r.json();
                        this.couponValid = j.valid;
                        this.couponMsg   = j.message;
                        this.discount    = j.valid ? j.discount : 0;
                        this.mountMoyasar();
                    } catch (e) {
                        this.couponValid = false;
                        this.couponMsg = 'خطأ في الاتصال — حاول مرة أخرى.';
                    }
                },

                mountMoyasar() {
                    document.querySelector('.mysr-form').innerHTML = '';

                    @if (! empty($settings['moyasar_pk']))
                        Moyasar.init({
                            element: '.mysr-form',
                            amount: Math.round(this.finalAmount * 100),
                            currency: 'SAR',
                            description: '{{ addslashes($plan->name) }} - ' + this.cycle,
                            publishable_api_key: '{{ $settings['moyasar_pk'] }}',
                            callback_url: '{{ url('/checkout/callback') }}',
                            methods: @json($settings['moyasar_methods']),
                            metadata: {
                                plan_id: this.plan_id,
                                billing_cycle: this.cycle,
                                company_name: this.company_name,
                                owner_name: this.owner_name,
                                owner_email: this.owner_email,
                                owner_phone: this.owner_phone,
                                coupon_code: this.coupon_code,
                                discount_amount: this.discount,
                            }
                        });
                    @else
                        document.querySelector('.mysr-form').innerHTML = '<div class="msg-error">Moyasar غير معدّ بعد — راجع إعدادات المدير.</div>';
                    @endif
                },

                init() {
                    this.$watch('owner_email', () => this.mountMoyasar());
                    this.mountMoyasar();
                }
            };
        }
    </script>
    <script src="//cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
