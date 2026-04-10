<x-app-layout>
    <div class="mz-screen" x-data="tenderWizard()">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">✨ كراسة جديدة</div>
                <div class="mz-page-sub">أكمل الخطوات الخمس وسيقوم النظام بتوليد كراسة كاملة</div>
            </div>
            <a href="{{ route('tenders.index') }}" class="mz-btn mz-btn-ghost mz-btn-sm">← العودة</a>
        </div>

        {{-- Step indicator --}}
        <div style="display:flex;gap:8px;margin-bottom:20px">
            <template x-for="i in 5" :key="i">
                <div class="mz-wizard-step"
                     :class="step === i ? 'active' : (step > i ? 'done' : '')"
                     style="flex:1;padding:10px 14px;background:var(--card2);border:1px solid var(--borderl);border-radius:8px;text-align:center;font-size:12px;font-weight:700">
                    <span x-text="['البيانات الأساسية','نطاق العمل','المخرجات','الشروط والمعايير','المراجعة'][i-1]"></span>
                </div>
            </template>
        </div>

        <form method="POST" action="{{ route('tenders.store') }}" class="mz-card" style="padding:24px">
            @csrf

            {{-- Step 1: Project Info --}}
            <div x-show="step === 1">
                <h3 style="margin:0 0 16px;color:var(--gold)">الخطوة 1: البيانات الأساسية</h3>

                <div class="mz-form-group">
                    <label class="mz-flabel">عنوان المشروع *</label>
                    <input type="text" name="title" x-model="form.title" required class="mz-inp" placeholder="مثال: تطوير نظام إدارة العقود">
                </div>

                <div class="mz-form-group">
                    <label class="mz-flabel">وصف المشروع</label>
                    <textarea name="description" x-model="form.description" rows="3" class="mz-inp mz-textarea" placeholder="وصف مختصر لطبيعة المشروع وأهدافه"></textarea>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                    <div class="mz-form-group">
                        <label class="mz-flabel">نوع المشروع *</label>
                        <select name="type" x-model="form.type" required class="mz-inp">
                            <option value="it">مشروع تقني (IT)</option>
                            <option value="construction">مشروع إنشاءات</option>
                            <option value="consulting">خدمات استشارية</option>
                            <option value="operations">تشغيل وصيانة</option>
                            <option value="legal">خدمات قانونية</option>
                        </select>
                    </div>
                    <div class="mz-form-group">
                        <label class="mz-flabel">المدة الزمنية</label>
                        <input type="text" name="duration" x-model="form.duration" class="mz-inp" placeholder="مثال: 6 أشهر، سنة، 18 شهراً">
                    </div>
                </div>
            </div>

            {{-- Step 2: Scope --}}
            <div x-show="step === 2">
                <h3 style="margin:0 0 16px;color:var(--gold)">الخطوة 2: نطاق العمل</h3>
                <p style="font-size:12px;color:var(--mute);margin-bottom:14px">
                    💡 اكتب وصفاً لنطاق العمل ولو كان مختصراً. سيقوم الذكاء الاصطناعي بتوسيعه إلى مهام تفصيلية.
                </p>
                <div class="mz-form-group">
                    <label class="mz-flabel">نطاق العمل *</label>
                    <textarea name="scope_input" x-model="form.scope_input" rows="8" required class="mz-inp mz-textarea" placeholder="مثال: تطوير نظام لإدارة العقود يشمل تخزين العقود، تتبع الحالة، التذكيرات، وتقارير دورية"></textarea>
                </div>
            </div>

            {{-- Step 3: Deliverables --}}
            <div x-show="step === 3">
                <h3 style="margin:0 0 16px;color:var(--gold)">الخطوة 3: المخرجات والتسليمات</h3>
                <p style="font-size:12px;color:var(--mute);margin-bottom:14px">أضف المخرجات الرئيسية المتوقعة من المشروع (اختياري — يمكن للذكاء الاصطناعي اقتراحها).</p>

                <template x-for="(d, i) in form.deliverables" :key="i">
                    <div style="display:flex;gap:8px;margin-bottom:8px">
                        <input type="text" :name="'deliverables['+i+']'" x-model="form.deliverables[i]" class="mz-inp" placeholder="مثال: نسخة أولية من النظام">
                        <button type="button" @click="form.deliverables.splice(i, 1)" class="mz-btn mz-btn-ghost mz-btn-sm" style="color:var(--red)">✕</button>
                    </div>
                </template>
                <button type="button" @click="form.deliverables.push('')" class="mz-btn mz-btn-ghost mz-btn-sm">+ إضافة مخرج</button>
            </div>

            {{-- Step 4: Conditions --}}
            <div x-show="step === 4">
                <h3 style="margin:0 0 16px;color:var(--gold)">الخطوة 4: معايير التقييم والشروط</h3>

                <div class="mz-form-group">
                    <label class="mz-flabel">معايير التقييم (مع الأوزان)</label>
                    <p style="font-size:11px;color:var(--mute);margin-bottom:8px">مثال: العرض الفني 60%، العرض المالي 30%، الخبرات السابقة 10%</p>
                    <template x-for="(c, i) in form.evaluation_criteria" :key="i">
                        <div style="display:flex;gap:8px;margin-bottom:8px">
                            <input type="text" :name="'evaluation_criteria['+i+'][criterion]'" x-model="c.criterion" class="mz-inp" style="flex:2" placeholder="اسم المعيار">
                            <input type="number" :name="'evaluation_criteria['+i+'][weight]'" x-model="c.weight" class="mz-inp" style="flex:1" placeholder="الوزن %">
                            <button type="button" @click="form.evaluation_criteria.splice(i,1)" class="mz-btn mz-btn-ghost mz-btn-sm" style="color:var(--red)">✕</button>
                        </div>
                    </template>
                    <button type="button" @click="form.evaluation_criteria.push({criterion:'',weight:''})" class="mz-btn mz-btn-ghost mz-btn-sm">+ إضافة معيار</button>
                </div>

                <div class="mz-form-group" style="margin-top:18px">
                    <label class="mz-flabel">شروط خاصة</label>
                    <template x-for="(c, i) in form.special_conditions" :key="i">
                        <div style="display:flex;gap:8px;margin-bottom:8px">
                            <input type="text" :name="'special_conditions['+i+']'" x-model="form.special_conditions[i]" class="mz-inp" placeholder="مثال: التواجد في مقر الجهة">
                            <button type="button" @click="form.special_conditions.splice(i,1)" class="mz-btn mz-btn-ghost mz-btn-sm" style="color:var(--red)">✕</button>
                        </div>
                    </template>
                    <button type="button" @click="form.special_conditions.push('')" class="mz-btn mz-btn-ghost mz-btn-sm">+ إضافة شرط</button>
                </div>
            </div>

            {{-- Step 5: Review --}}
            <div x-show="step === 5">
                <h3 style="margin:0 0 16px;color:var(--gold)">الخطوة 5: مراجعة وتوليد</h3>

                <div style="background:var(--card2);border:1px solid var(--borderl);border-radius:10px;padding:16px;margin-bottom:14px">
                    <div style="font-size:11px;color:var(--mute);margin-bottom:6px">العنوان</div>
                    <div style="font-size:14px;color:var(--cream);font-weight:700;margin-bottom:14px" x-text="form.title || '—'"></div>

                    <div style="font-size:11px;color:var(--mute);margin-bottom:6px">النوع</div>
                    <div style="font-size:13px;color:var(--cream);margin-bottom:14px" x-text="typeLabel(form.type)"></div>

                    <div style="font-size:11px;color:var(--mute);margin-bottom:6px">المدة</div>
                    <div style="font-size:13px;color:var(--cream);margin-bottom:14px" x-text="form.duration || '—'"></div>

                    <div style="font-size:11px;color:var(--mute);margin-bottom:6px">نطاق العمل</div>
                    <div style="font-size:12px;color:var(--dim);line-height:1.7;white-space:pre-wrap" x-text="form.scope_input || '—'"></div>
                </div>

                <div style="background:rgba(200,169,75,.08);border:1px solid rgba(200,169,75,.3);border-radius:10px;padding:14px 16px;font-size:12px;color:var(--cream);line-height:1.7">
                    💡 عند الضغط على "توليد الكراسة" سيقوم الذكاء الاصطناعي بـ:
                    <ul style="padding-right:18px;margin:6px 0 0">
                        <li>توسيع نطاق العمل إلى مهام تفصيلية</li>
                        <li>اختيار القالب المناسب لنوع المشروع</li>
                        <li>توليد جميع أقسام الكراسة (10 أقسام)</li>
                        <li>إضافة البنود القانونية القياسية (SLA، الغرامات، الضمان، إلخ)</li>
                    </ul>
                </div>
            </div>

            {{-- Navigation --}}
            <div style="display:flex;justify-content:space-between;margin-top:24px;padding-top:18px;border-top:1px solid var(--borderl)">
                <button type="button" @click="step--" x-show="step > 1" class="mz-btn mz-btn-ghost">← السابق</button>
                <span x-show="step === 1"></span>
                <button type="button" @click="step++" x-show="step < 5" class="mz-btn mz-btn-gold">التالي ←</button>
                <button type="submit" x-show="step === 5" class="mz-btn mz-btn-gold">✨ توليد الكراسة</button>
            </div>
        </form>
    </div>

    <script>
        function tenderWizard() {
            return {
                step: 1,
                form: {
                    title: '',
                    description: '',
                    type: 'it',
                    duration: '',
                    scope_input: '',
                    deliverables: [''],
                    evaluation_criteria: [
                        { criterion: 'العرض الفني', weight: '60' },
                        { criterion: 'العرض المالي', weight: '30' },
                        { criterion: 'الخبرات السابقة', weight: '10' },
                    ],
                    special_conditions: [],
                },
                typeLabel(t) {
                    return {
                        it: 'مشروع تقني',
                        construction: 'مشروع إنشاءات',
                        consulting: 'خدمات استشارية',
                        operations: 'تشغيل وصيانة',
                        legal: 'خدمات قانونية',
                    }[t] || t;
                },
            };
        }
    </script>
</x-app-layout>
