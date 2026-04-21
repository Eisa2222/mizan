@extends('super-admin.layouts.app')
@section('title', 'الكوبونات')
@section('heading', 'الكوبونات')

@section('content')
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;gap:10px;flex-wrap:wrap">
        <form method="GET" style="display:flex;gap:10px;flex:1;min-width:300px">
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="ابحث بالكود أو الاسم..." class="form-input" style="flex:1;padding:8px 12px;border:1px solid #d4d9e3;border-radius:8px;font-family:inherit">
            <select name="type" class="form-input" style="padding:8px 12px;border:1px solid #d4d9e3;border-radius:8px;font-family:inherit">
                <option value="">كل الأنواع</option>
                <option value="percentage" {{ ($filters['type'] ?? '') === 'percentage' ? 'selected' : '' }}>نسبة</option>
                <option value="fixed"      {{ ($filters['type'] ?? '') === 'fixed'      ? 'selected' : '' }}>مبلغ ثابت</option>
            </select>
            <select name="status" class="form-input" style="padding:8px 12px;border:1px solid #d4d9e3;border-radius:8px;font-family:inherit">
                <option value="">كل الحالات</option>
                <option value="active"    {{ ($filters['status'] ?? '') === 'active'    ? 'selected' : '' }}>نشط</option>
                <option value="expired"   {{ ($filters['status'] ?? '') === 'expired'   ? 'selected' : '' }}>منتهي</option>
                <option value="exhausted" {{ ($filters['status'] ?? '') === 'exhausted' ? 'selected' : '' }}>مستنفد</option>
            </select>
            <button class="sa-btn">تصفية</button>
        </form>
        <a href="{{ route('super-admin.coupons.create') }}" class="sa-btn">+ كوبون جديد</a>
    </div>

    <div class="sa-card" style="padding:0;overflow:hidden">
        <table class="sa-table">
            <thead><tr><th>الكود</th><th>الاسم</th><th>النوع</th><th>القيمة</th><th>الاستخدامات</th><th>ينتهي</th><th>الحالة</th><th></th></tr></thead>
            <tbody>
                @forelse ($coupons as $c)
                    <tr>
                        <td>
                            <code style="background:#f5f7fb;padding:3px 8px;border-radius:4px;font-family:monospace" dir="ltr">{{ $c->code }}</code>
                            <button onclick="navigator.clipboard.writeText('{{ $c->code }}');this.textContent='✓';setTimeout(()=>this.textContent='نسخ',1500)"
                                    style="background:none;border:none;color:#8a91a4;cursor:pointer;font-size:10px">نسخ</button>
                        </td>
                        <td>{{ $c->name }}</td>
                        <td>{{ $c->type === 'percentage' ? 'نسبة' : 'ثابت' }}</td>
                        <td>{{ $c->type === 'percentage' ? $c->value.'%' : number_format($c->value, 2).' SAR' }}</td>
                        <td>{{ $c->uses_count }}{{ $c->max_uses ? ' / '.$c->max_uses : ' / ∞' }}</td>
                        <td>{{ $c->expires_at?->format('Y-m-d') ?? '—' }}</td>
                        <td>
                            @if (! $c->is_active) <span class="sa-badge sa-badge-grey">موقوف</span>
                            @elseif ($c->isExpired()) <span class="sa-badge sa-badge-red">منتهي</span>
                            @elseif ($c->hasReachedMaxUses()) <span class="sa-badge sa-badge-amber">مستنفد</span>
                            @else <span class="sa-badge sa-badge-green">نشط</span>
                            @endif
                        </td>
                        <td style="display:flex;gap:4px">
                            <a href="{{ route('super-admin.coupons.show', $c) }}" class="sa-btn sa-btn-ghost" style="padding:4px 10px">عرض</a>
                            <a href="{{ route('super-admin.coupons.edit', $c) }}" class="sa-btn sa-btn-ghost" style="padding:4px 10px">تعديل</a>
                            <form method="POST" action="{{ route('super-admin.coupons.toggle', $c) }}">@csrf
                                <button class="sa-btn sa-btn-ghost" style="padding:4px 10px">{{ $c->is_active ? 'إيقاف' : 'تفعيل' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;padding:32px;color:#8a91a4">لا توجد كوبونات.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $coupons->links() }}</div>
@endsection
