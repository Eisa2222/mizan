@extends('super-admin.layouts.app')
@section('title', $plan->exists ? 'تعديل الباقة' : 'باقة جديدة')
@section('heading', $plan->exists ? 'تعديل الباقة' : 'باقة جديدة')

@php
    $action = $plan->exists ? route('super-admin.plans.update', $plan) : route('super-admin.plans.store');
@endphp

@section('content')
    <form method="POST" action="{{ $action }}" class="sa-card" style="max-width:760px">
        @csrf
        @if ($plan->exists) @method('PUT') @endif

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">الاسم</label>
                <input name="name" value="{{ old('name', $plan->name) }}" required class="form-input" style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
                @error('name') <div style="color:#c75c5c;font-size:11px;margin-top:2px">{{ $message }}</div> @enderror
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Slug</label>
                <input name="slug" value="{{ old('slug', $plan->slug) }}" required dir="ltr" class="form-input" style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
                @error('slug') <div style="color:#c75c5c;font-size:11px;margin-top:2px">{{ $message }}</div> @enderror
            </div>
        </div>

        <div style="margin-top:14px">
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">الوصف</label>
            <textarea name="description" rows="2" class="form-input" style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">{{ old('description', $plan->description) }}</textarea>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-top:14px">
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">السعر الشهري (SAR)</label>
                <input name="price_monthly" type="number" step="0.01" min="0" value="{{ old('price_monthly', $plan->price_monthly ?? 0) }}" required class="form-input" style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">السعر السنوي (SAR)</label>
                <input name="price_yearly" type="number" step="0.01" min="0" value="{{ old('price_yearly', $plan->price_yearly ?? 0) }}" required class="form-input" style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">العملة</label>
                <input name="currency" value="{{ old('currency', $plan->currency ?? 'SAR') }}" required dir="ltr" class="form-input" style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-top:14px">
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">أيام التجربة</label>
                <input name="trial_days" type="number" min="0" max="365" value="{{ old('trial_days', $plan->trial_days ?? 14) }}" class="form-input" style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">حد المستخدمين (0=غير محدود)</label>
                <input name="max_users" type="number" min="0" value="{{ old('max_users', $plan->max_users ?? 0) }}" class="form-input" style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">التخزين (GB)</label>
                <input name="max_storage_gb" type="number" min="0" value="{{ old('max_storage_gb', $plan->max_storage_gb ?? 0) }}" class="form-input" style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-top:14px">
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">نص الشارة</label>
                <input name="badge_text" value="{{ old('badge_text', $plan->badge_text) }}" class="form-input" style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">الترتيب</label>
                <input name="sort_order" type="number" min="0" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" class="form-input" style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
            </div>
            <div style="padding-top:20px">
                <label><input type="checkbox" name="is_active" value="1" {{ old('is_active', $plan->is_active ?? true) ? 'checked' : '' }}> نشط</label><br>
                <label><input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $plan->is_featured) ? 'checked' : '' }}> مميّز</label>
            </div>
        </div>

        <div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end">
            <a href="{{ route('super-admin.plans.index') }}" class="sa-btn sa-btn-ghost">إلغاء</a>
            <button type="submit" class="sa-btn">{{ $plan->exists ? 'حفظ التعديلات' : 'إنشاء' }}</button>
        </div>
    </form>
@endsection
