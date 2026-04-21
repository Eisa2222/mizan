@extends('super-admin.layouts.app')
@section('title', $coupon->exists ? 'تعديل الكوبون' : 'كوبون جديد')
@section('heading', $coupon->exists ? 'تعديل الكوبون' : 'كوبون جديد')

@php
    $action = $coupon->exists ? route('super-admin.coupons.update', $coupon) : route('super-admin.coupons.store');
@endphp

@section('content')
    <form method="POST" action="{{ $action }}" class="sa-card" style="max-width:760px">
        @csrf
        @if ($coupon->exists) @method('PUT') @endif

        <div style="display:grid;grid-template-columns:2fr 1fr;gap:14px">
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">كود الكوبون</label>
                <div style="display:flex;gap:6px">
                    <input name="code" id="code-input" dir="ltr" required {{ $coupon->exists ? 'readonly' : '' }}
                           value="{{ old('code', $coupon->code) }}"
                           style="flex:1;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:monospace;text-transform:uppercase">
                    @if (! $coupon->exists)
                        <button type="button" class="sa-btn sa-btn-ghost"
                                onclick="fetch('{{ route('super-admin.coupons.generate') }}').then(r=>r.json()).then(j=>document.getElementById('code-input').value=j.code)">
                            توليد تلقائي
                        </button>
                    @endif
                </div>
                @error('code') <div style="color:#c75c5c;font-size:11px;margin-top:2px">{{ $message }}</div> @enderror
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">الاسم الوصفي</label>
                <input name="name" value="{{ old('name', $coupon->name) }}" required
                       style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px">
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">النوع</label>
                <label style="display:block;font-size:13px"><input type="radio" name="type" value="percentage" {{ old('type', $coupon->type) === 'percentage' ? 'checked' : '' }}> نسبة مئوية</label>
                <label style="display:block;font-size:13px"><input type="radio" name="type" value="fixed"      {{ old('type', $coupon->type) === 'fixed'      ? 'checked' : '' }}> مبلغ ثابت (SAR)</label>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">القيمة</label>
                <input name="value" type="number" step="0.01" min="0.01" required value="{{ old('value', $coupon->value) }}"
                       style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
                @error('value') <div style="color:#c75c5c;font-size:11px;margin-top:2px">{{ $message }}</div> @enderror
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px">
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">الحد الأقصى للاستخدامات (فارغ = غير محدود)</label>
                <input name="max_uses" type="number" min="1" value="{{ old('max_uses', $coupon->max_uses) }}"
                       style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">الحد الأدنى للطلب (SAR)</label>
                <input name="min_order_amount" type="number" step="0.01" min="0" value="{{ old('min_order_amount', $coupon->min_order_amount) }}"
                       style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
            </div>
        </div>

        <div style="margin-top:14px">
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">الباقات المطبّقة عليها (فارغ = كل الباقات)</label>
            @foreach ($plans as $p)
                <label style="display:inline-flex;align-items:center;gap:4px;margin-inline-end:12px;font-size:13px">
                    <input type="checkbox" name="applicable_plans[]" value="{{ $p->id }}" {{ in_array($p->id, old('applicable_plans', $coupon->applicable_plans ?? [])) ? 'checked' : '' }}>
                    {{ $p->name }}
                </label>
            @endforeach
        </div>

        <div style="margin-top:14px">
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">دورة الفوترة (فارغ = كليهما)</label>
            @foreach (['monthly'=>'شهري', 'yearly'=>'سنوي'] as $k=>$v)
                <label style="display:inline-flex;align-items:center;gap:4px;margin-inline-end:12px;font-size:13px">
                    <input type="checkbox" name="billing_cycles[]" value="{{ $k }}" {{ in_array($k, old('billing_cycles', $coupon->billing_cycles ?? [])) ? 'checked' : '' }}>
                    {{ $v }}
                </label>
            @endforeach
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px">
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">يبدأ في</label>
                <input name="starts_at" type="date" value="{{ old('starts_at', $coupon->starts_at?->format('Y-m-d')) }}"
                       style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">ينتهي في</label>
                <input name="expires_at" type="date" value="{{ old('expires_at', $coupon->expires_at?->format('Y-m-d')) }}"
                       style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
            </div>
        </div>

        <div style="margin-top:14px">
            <label><input type="checkbox" name="is_active" value="1" {{ old('is_active', $coupon->is_active ?? true) ? 'checked' : '' }}> نشط</label>
        </div>

        <div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end">
            <a href="{{ route('super-admin.coupons.index') }}" class="sa-btn sa-btn-ghost">إلغاء</a>
            <button type="submit" class="sa-btn">{{ $coupon->exists ? 'حفظ' : 'إنشاء' }}</button>
        </div>
    </form>
@endsection
