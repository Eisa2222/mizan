<x-app-layout>
    <div class="mz-screen" x-data="tenderWizard()">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">✨ كراسة جديدة</div>
                <div class="mz-page-sub">أكمل الخطوات الخمس وسيقوم النظام بتوليد كراسة كاملة</div>
            </div>
            <a href="{{ route('tenders.index') }}" class="mz-btn mz-btn-ghost mz-btn-sm">← العودة</a>
        </div>

        {{-- Validation errors --}}
        <template x-if="stepError">
            <div style="background:rgba(220,60,60,.12);border:1px solid rgba(220,60,60,.4);border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:13px;color:#e55">
                <span x-text="stepError"></span>
            </div>
        </template>

        @if ($errors->any())
            <div style="background:rgba(220,60,60,.12);border:1px solid rgba(220,60,60,.4);border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:13px;color:#e55">
                @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        {{-- Step indicator --}}
        <div style="display:flex;gap:8px;margin-bottom:20px">
            <template x-for="i in 5" :key="i">
                <div class="mz-wizard-step"
                     :class="step === i ? 'active' : (step > i ? 'done' : '')"
                     @click="goToStep(i)"
                     style="flex:1;padding:10px 14px;background:var(--card2);border:1px solid var(--borderl);border-radius:8px;text-align:center;font-size:12px;font-weight:700;cursor:pointer;transition:all .2s">
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
                    <label class="mz-flabel">عنوان المشروع <span style="color:var(--red)">*</span></label>
                    <input type="text" name="title" x-model="form.title" required class="mz-inp" placeholder="مثال: تطوير نظام إدارة العقود"
                           :style="submitted1 && !form.title.trim() ? 'border-color:var(--red)' : ''">
                    <template x-if="submitted1 && !form.title.trim()">
                        <div style="font-size:11px;color:var(--red);margin-top:4px">عنوان المشروع مطلوب</div>
                    </template>
                </div>

                <div class="mz-form-group">
                    <label class="mz-flabel">وصف المشروع <span style="font-size:10px;color:var(--mute)">(اختياري)</span></label>
                    <textarea name="description" x-model="form.description" rows="3" class="mz-inp mz-textarea" placeholder="وصف مختصر لطبيعة المشروع وأهدافه"></textarea>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                    <div class="mz-form-group">
                        <label class="mz-flabel">نوع المشروع <span style="color:var(--red)">*</span></label>
                        <select name="type" x-model="form.type" required class="mz-inp">
                            <option value="it">مشروع تقني (IT)</option>
                            <option value="construction">مشروع إنشاءات</option>
                            <option value="consulting">خدمات استشارية</option>
                            <option value="operations">تشغيل وصيانة</option>
                            <option value="legal">خدمات قانونية</option>
                        </select>
                    </div>
                    <div class="mz-form-group">
                        <label class="mz-flabel">المدة الزمنية <span style="font-size:10px;color:var(--mute)">(اختياري)</span></label>
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
                    <label class="mz-flabel">نطاق العمل <span style="color:var(--red)">*</span></label>
                    <textarea name="scope_input" x-model="form.scope_input" rows="8" required class="mz-inp mz-textarea" placeholder="مثال: تطوير نظام لإدارة العقود يشمل تخزين العقود، تتبع الحالة، التذكيرات، وتقارير دورية"
                              :style="submitted2 && form.scope_input.trim().length < 10 ? 'border-color:var(--red)' : ''"></textarea>
                    <template x-if="submitted2 && form.scope_input.trim().length < 10">
                        <div style="font-size:11px;color:var(--red);margin-top:4px">نطاق العمل مطلوب (10 أحرف على الأقل)</div>
                    </template>
                </div>
            </div>

            {{-- Step 3: Deliverables + BOQ --}}
            <div x-show="step === 3">
                <h3 style="margin:0 0 16px;color:var(--gold)">الخطوة 3: المخرجات وجدول الكميات</h3>

                {{-- Deliverables --}}
                <div class="mz-form-group">
                    <label class="mz-flabel">المخرجات والتسليمات <span style="font-size:10px;color:var(--mute)">(اختياري — يمكن للذكاء الاصطناعي اقتراحها)</span></label>
                    <template x-for="(d, i) in form.deliverables" :key="'del-'+i">
                        <div style="display:flex;gap:8px;margin-bottom:8px">
                            <input type="text" :name="'deliverables['+i+']'" x-model="form.deliverables[i]" class="mz-inp" placeholder="مثال: نسخة أولية من النظام">
                            <button type="button" @click="form.deliverables.splice(i, 1)" class="mz-btn mz-btn-ghost mz-btn-sm" style="color:var(--red)">✕</button>
                        </div>
                    </template>
                    <button type="button" @click="form.deliverables.push('')" class="mz-btn mz-btn-ghost mz-btn-sm">+ إضافة مخرج</button>
                </div>

                {{-- BOQ --}}
                <div class="mz-form-group" style="margin-top:24px">
                    <label class="mz-flabel">جدول الكميات والأسعار <span style="font-size:10px;color:var(--mute)">(اختياري — إذا تُرك فارغاً يُولّد تلقائياً من نطاق العمل)</span></label>
                    <p style="font-size:11px;color:var(--mute);margin-bottom:10px">أضف بنود جدول الكميات. كل بند يتضمن: وصف العمل، الوحدة، والكمية. المتنافس يعبئ الأسعار.</p>

                    <div style="overflow-x:auto">
                        <table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:8px">
                            <thead>
                                <tr style="background:var(--card2);border-bottom:1px solid var(--borderl)">
                                    <th style="padding:8px 10px;text-align:right;color:var(--mute);width:40px">م</th>
                                    <th style="padding:8px 10px;text-align:right;color:var(--mute)">وصف البند</th>
                                    <th style="padding:8px 10px;text-align:right;color:var(--mute);width:100px">الوحدة</th>
                                    <th style="padding:8px 10px;text-align:right;color:var(--mute);width:80px">الكمية</th>
                                    <th style="padding:8px 10px;width:40px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(row, i) in form.boq_items" :key="'boq-'+i">
                                    <tr style="border-bottom:1px solid var(--borderl)">
                                        <td style="padding:6px 10px;color:var(--mute)" x-text="i+1"></td>
                                        <td style="padding:4px 4px">
                                            <input type="text" :name="'boq_items['+i+'][description]'" x-model="row.description" class="mz-inp" style="font-size:12px" placeholder="وصف البند">
                                        </td>
                                        <td style="padding:4px 4px">
                                            <select :name="'boq_items['+i+'][unit]'" x-model="row.unit" class="mz-inp" style="font-size:12px">
                                                <optgroup label="عام">
                                                    <option value="مقطوعية">مقطوعية</option>
                                                    <option value="وحدة">وحدة</option>
                                                    <option value="عدد">عدد</option>
                                                    <option value="طقم">طقم</option>
                                                    <option value="مجموعة">مجموعة</option>
                                                </optgroup>
                                                <optgroup label="زمني">
                                                    <option value="شهر">شهر</option>
                                                    <option value="سنة">سنة</option>
                                                    <option value="يوم">يوم</option>
                                                    <option value="ساعة">ساعة</option>
                                                    <option value="وردية">وردية</option>
                                                </optgroup>
                                                <optgroup label="تراخيص وبرمجيات">
                                                    <option value="رخصة">رخصة</option>
                                                    <option value="اشتراك سنوي">اشتراك سنوي</option>
                                                    <option value="اشتراك شهري">اشتراك شهري</option>
                                                    <option value="مستخدم">مستخدم</option>
                                                    <option value="نسخة">نسخة</option>
                                                </optgroup>
                                                <optgroup label="خدمات">
                                                    <option value="خدمة">خدمة</option>
                                                    <option value="مرحلة">مرحلة</option>
                                                    <option value="رحلة">رحلة</option>
                                                    <option value="جلسة">جلسة</option>
                                                    <option value="تقرير">تقرير</option>
                                                    <option value="دورة تدريبية">دورة تدريبية</option>
                                                    <option value="متدرب">متدرب</option>
                                                    <option value="زيارة">زيارة</option>
                                                </optgroup>
                                                <optgroup label="قياس">
                                                    <option value="متر مربع">م²</option>
                                                    <option value="متر طولي">م.ط</option>
                                                    <option value="متر مكعب">م³</option>
                                                    <option value="كيلومتر">كم</option>
                                                    <option value="طن">طن</option>
                                                    <option value="كيلوغرام">كجم</option>
                                                    <option value="لتر">لتر</option>
                                                </optgroup>
                                                <optgroup label="أفراد">
                                                    <option value="شخص">شخص</option>
                                                    <option value="شخص/شهر">شخص/شهر</option>
                                                    <option value="فني">فني</option>
                                                    <option value="مهندس">مهندس</option>
                                                    <option value="استشاري">استشاري</option>
                                                </optgroup>
                                            </select>
                                        </td>
                                        <td style="padding:4px 4px">
                                            <input type="number" :name="'boq_items['+i+'][quantity]'" x-model="row.quantity" class="mz-inp" style="font-size:12px" placeholder="1" min="1">
                                        </td>
                                        <td style="padding:4px 4px;text-align:center">
                                            <button type="button" @click="form.boq_items.splice(i, 1)" class="mz-btn mz-btn-ghost mz-btn-sm" style="color:var(--red);padding:4px">✕</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" @click="form.boq_items.push({description:'', unit:'مقطوعية', quantity:'1'})" class="mz-btn mz-btn-ghost mz-btn-sm">+ إضافة بند</button>
                </div>
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
                    <div style="font-size:12px;color:var(--dim);line-height:1.7;white-space:pre-wrap;margin-bottom:14px" x-text="form.scope_input || '—'"></div>

                    <template x-if="form.boq_items.filter(r => r.description.trim()).length > 0">
                        <div>
                            <div style="font-size:11px;color:var(--mute);margin-bottom:6px">جدول الكميات</div>
                            <div style="font-size:12px;color:var(--dim)">
                                <span x-text="form.boq_items.filter(r => r.description.trim()).length"></span> بند
                            </div>
                        </div>
                    </template>
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
                <button type="button" @click="step--; stepError=''" x-show="step > 1" class="mz-btn mz-btn-ghost">← السابق</button>
                <span x-show="step === 1"></span>
                <button type="button" @click="nextStep()" x-show="step < 5" class="mz-btn mz-btn-gold">التالي →</button>
                <button type="submit" x-show="step === 5" class="mz-btn mz-btn-gold" :disabled="submitting" x-text="submitting ? 'جاري التوليد...' : '✨ توليد الكراسة'"></button>
            </div>
        </form>
    </div>

    <script>
        function tenderWizard() {
            return {
                step: 1,
                stepError: '',
                submitted1: false,
                submitted2: false,
                submitting: false,
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
                    boq_items: [],
                    special_conditions: [],
                },
                validateStep(n) {
                    if (n === 1) {
                        this.submitted1 = true;
                        if (!this.form.title.trim()) return 'عنوان المشروع مطلوب';
                        if (!this.form.type) return 'نوع المشروع مطلوب';
                    }
                    if (n === 2) {
                        this.submitted2 = true;
                        if (this.form.scope_input.trim().length < 10) return 'نطاق العمل مطلوب (10 أحرف على الأقل)';
                    }
                    return '';
                },
                nextStep() {
                    const err = this.validateStep(this.step);
                    if (err) { this.stepError = err; return; }
                    this.stepError = '';
                    this.step++;
                },
                goToStep(target) {
                    if (target <= this.step) {
                        this.stepError = '';
                        this.step = target;
                        return;
                    }
                    for (let s = this.step; s < target; s++) {
                        const err = this.validateStep(s);
                        if (err) { this.stepError = err; this.step = s; return; }
                    }
                    this.stepError = '';
                    this.step = target;
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
                init() {
                    this.$watch('step', () => { this.stepError = ''; });
                    this.$el.closest('form')?.addEventListener('submit', () => { this.submitting = true; });
                },
            };
        }
    </script>
</x-app-layout>
